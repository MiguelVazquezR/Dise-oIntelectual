<?php

namespace App\Http\Controllers;

use App\Enums\HolidayType;
use App\Http\Requests\Holidays\IndexHolidayRequest;
use App\Http\Requests\Holidays\StoreHolidayRequest;
use App\Models\Holiday;
use App\Models\User;
use App\Services\Holidays\HolidayAuditRecorder;
use App\Services\Holidays\HolidayGenerator;
use App\Services\Holidays\HolidayLockGuard;
use App\Services\Holidays\LegalHolidayRules;
use App\Support\BranchAccess;
use App\Support\Branches\BranchOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendario de días festivos (LFT art. 74) con años protegidos.
 *
 * Contrato de props en ARCHITECTURE.md §7.3: `filters`, `holidays` agrupado por
 * mes, `summary`, `options`, `can` y `legal_notice`.
 */
class HolidayController extends Controller
{
    /**
     * Etiquetas de mes para agrupar el calendario.
     *
     * @var array<int, string>
     */
    private const MONTH_LABELS = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    public function __construct(
        private readonly HolidayGenerator $generator,
        private readonly HolidayLockGuard $lockGuard,
        private readonly HolidayAuditRecorder $audit,
    ) {}

    /**
     * Listado de festivos del año seleccionado.
     */
    public function index(IndexHolidayRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();
        $year = (int) ($filters['year'] ?? CarbonImmutable::now()->year);
        $branchFilter = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $visibleBranchIds = $this->visibleBranchIds($user, $branchFilter);
        $locked = $this->lockGuard->isLocked($year);

        $holidays = Holiday::query()
            ->with('branch:id,name')
            ->forYear($year)
            ->where(function (Builder $query) use ($visibleBranchIds): void {
                $query->whereNull('branch_id');

                if ($visibleBranchIds !== null && $visibleBranchIds !== []) {
                    $query->orWhereIn('branch_id', $visibleBranchIds);
                }
            })
            ->when(
                $filters['type'] ?? null,
                fn (Builder $query, string $type) => $query->where('type', $type),
            )
            ->orderBy('date')
            ->orderBy('name')
            ->get();

        $summary = [
            'mandatory' => $holidays->where('type', HolidayType::Mandatory)->count(),
            'company' => $holidays->where('type', HolidayType::Company)->count(),
            'total' => $holidays->count(),
        ];

        $groups = $holidays
            ->groupBy(fn (Holiday $holiday): int => (int) $holiday->date->month)
            ->map(fn ($rows, int $month): array => [
                'month' => $month,
                'label' => $this->monthLabel($month),
                'holidays' => $rows->map(fn (Holiday $holiday): array => $this->row($holiday, $locked))->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('holidays/Index', [
            'filters' => [
                'year' => $year,
                'branch_id' => $branchFilter,
                'type' => $filters['type'] ?? null,
            ],
            'holidays' => $groups,
            'summary' => $summary,
            'options' => [
                'years' => $this->yearOptions(),
                'branches' => BranchOptions::forUser($user),
                'types' => $this->typeOptions(),
            ],
            'can' => [
                'manage' => $user->hasPermission('holidays.manage'),
            ],
            'legal_notice' => LegalHolidayRules::validationNotice(),
            'year_locked' => $locked,
        ]);
    }

    /**
     * Genera el calendario legal del año de forma idempotente.
     */
    public function generate(Request $request, int $year): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $allowClosedYear = $this->allowsClosedYearOverride($request, $user);

        if ($this->lockGuard->isLocked($year) && ! $allowClosedYear) {
            return to_route('holidays.index', ['year' => $year])
                ->with('error', $this->lockGuard->lockedMessage($year));
        }

        $result = $this->generator->forYear($year, $allowClosedYear, $user->id);

        $this->audit->record('holiday.generated', null, $user, null, [
            'year' => $year,
            'created' => $result['created'],
            'refreshed' => $result['refreshed'],
            'skipped' => $result['skipped'],
        ]);

        $message = $result['created'] === 0
            ? "El calendario {$year} ya estaba completo: {$result['skipped']} festivos coinciden con la regla legal."
            : "Calendario {$year} generado: {$result['created']} festivos nuevos y {$result['skipped']} ya existentes.";

        return to_route('holidays.index', ['year' => $year])->with('success', $message);
    }

    /**
     * Registra un festivo de empresa (por sucursal opcional) o uno obligatorio
     * con su base legal.
     */
    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $date = CarbonImmutable::parse($data['date'])->startOfDay();
        $year = (int) $date->year;
        $allowClosedYear = $this->allowsClosedYearOverride($request, $user);

        if ($this->lockGuard->isLocked($year) && ! $allowClosedYear) {
            return to_route('holidays.index', ['year' => $year])
                ->with('error', $this->lockGuard->lockedMessage($year));
        }

        $type = HolidayType::from($data['type']);

        $holiday = Holiday::query()->create([
            'name' => $data['name'],
            'date' => $date->toDateString(),
            'type' => $type->value,
            'branch_id' => $type === HolidayType::Company ? ($data['branch_id'] ?? null) : null,
            'legal_basis' => $data['legal_basis'] ?? null,
            'source_url' => $data['source_url'] ?? ($type === HolidayType::Mandatory ? LegalHolidayRules::sourceUrl() : null),
            'source_checked_at' => null,
            'created_by' => $user->id,
        ]);

        $this->audit->record('holiday.created', $holiday, $user, null, $this->row($holiday, false));

        return to_route('holidays.index', ['year' => $year])
            ->with('success', "Festivo «{$holiday->name}» registrado correctamente.");
    }

    /**
     * Elimina un festivo; bloqueado en años cerrados o con pre-nómina cerrada.
     */
    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $year = (int) $holiday->date->year;
        $allowClosedYear = $this->allowsClosedYearOverride($request, $user);

        if ($holiday->branch_id !== null) {
            abort_unless(BranchAccess::allows($user, (int) $holiday->branch_id), 403);
        }

        if ($this->lockGuard->isLocked($year) && ! $allowClosedYear) {
            return to_route('holidays.index', ['year' => $year])
                ->with('error', $this->lockGuard->lockedMessage($year));
        }

        if ($this->lockGuard->hasClosedPayroll($holiday->date)) {
            return to_route('holidays.index', ['year' => $year])
                ->with('error', 'No se puede eliminar el festivo: su fecha pertenece a un periodo de pre-nómina cerrado.');
        }

        $before = $this->row($holiday, false);
        $name = $holiday->name;

        DB::transaction(function () use ($holiday): void {
            $holiday->delete();
        });

        $this->audit->record('holiday.deleted', $holiday, $user, $before, null);

        return to_route('holidays.index', ['year' => $year])
            ->with('success', "Festivo «{$name}» eliminado correctamente.");
    }

    /**
     * Sucursales cuyos festivos puede ver el usuario, ya acotadas por el filtro.
     *
     * @return array<int, int>|null `null` = sin filtro de sucursal
     */
    private function visibleBranchIds(User $user, ?int $branchFilter): ?array
    {
        $allowed = $user->allowedBranchIds();

        if ($branchFilter === null) {
            return $allowed;
        }

        abort_unless($allowed === null || in_array($branchFilter, $allowed, true), 403);

        return [$branchFilter];
    }

    /**
     * El coordinador (permiso `data.all_branches`) puede forzar años cerrados.
     */
    private function allowsClosedYearOverride(Request $request, User $user): bool
    {
        return $request->boolean('force')
            && $user->hasPermission(User::ALL_BRANCHES_PERMISSION);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Holiday $holiday, bool $locked): array
    {
        return [
            'id' => $holiday->id,
            'name' => $holiday->name,
            'date' => $holiday->date->toDateString(),
            'type' => $holiday->type->value,
            'type_label' => $holiday->type->label(),
            'branch_id' => $holiday->branch_id,
            'branch' => $holiday->branch === null ? null : [
                'id' => $holiday->branch->id,
                'name' => $holiday->branch->name,
            ],
            'legal_basis' => $holiday->legal_basis,
            'source_url' => $holiday->source_url,
            'source_checked_at' => $holiday->source_checked_at?->toIso8601String(),
            'is_locked' => $locked,
        ];
    }

    private function monthLabel(int $month): string
    {
        return self::MONTH_LABELS[$month] ?? (string) $month;
    }

    /**
     * @return array<int, int>
     */
    private function yearOptions(): array
    {
        $current = (int) CarbonImmutable::now()->year;

        return array_reverse(range($current - 3, $current + 1));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (HolidayType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            HolidayType::cases(),
        );
    }
}
