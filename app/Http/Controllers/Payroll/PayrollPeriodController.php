<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\PayrollPeriodStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Payroll\Concerns\HandlesPayrollState;
use App\Http\Requests\Payroll\ClosePayrollPeriodRequest;
use App\Http\Requests\Payroll\IndexPayrollPeriodRequest;
use App\Http\Requests\Payroll\StorePayrollPeriodRequest;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSchedule;
use App\Models\User;
use App\Services\Payroll\Exceptions\PayrollStateException;
use App\Services\Payroll\PayrollCloser;
use App\Services\Payroll\PayrollMoney;
use App\Services\Payroll\PayrollRules;
use App\Support\Pagination\PaginatedPayload;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Periodos de pre-nómina (§7.4).
 *
 * - Listado y detalle filtrados por las sucursales visibles del actor: los
 *   periodos se acotan a las corridas de esas sucursales y los importes que se
 *   muestran son solo los de esas corridas.
 * - `review` y `close` delegan en `PayrollCloser`, que vuelve a comprobar los
 *   permisos (`payroll.review`, `payroll.close`).
 * - Un periodo cerrado responde 409 y no se modifica.
 */
class PayrollPeriodController extends Controller
{
    use HandlesPayrollState;

    public function __construct(
        private readonly PayrollCloser $closer,
        private readonly PayrollRules $rules,
    ) {}

    public function index(IndexPayrollPeriodRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);
        $branchIds = $user->allowedBranchIds();

        $query = PayrollPeriod::query()
            ->with(['schedule:id,name,frequency'])
            ->where(function (Builder $scope) use ($branchIds): void {
                $scope->doesntHave('runs');

                if ($branchIds === null) {
                    $scope->orWhereHas('runs');
                } elseif ($branchIds !== []) {
                    $scope->orWhereHas('runs', fn (Builder $runs) => $runs->whereIn('branch_id', $branchIds));
                }
            })
            ->when($filters['schedule_id'] ?? null, fn (Builder $inner, int|string $scheduleId) => $inner->where('payroll_schedule_id', (int) $scheduleId))
            ->when($filters['status'] ?? null, fn (Builder $inner, string $status) => $inner->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $inner, string $from) => $inner->whereDate('end_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $inner, string $to) => $inner->whereDate('start_date', '<=', $to))
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        $periods = $query->paginate($perPage)->withQueryString();
        $runsByPeriod = $this->scopedRuns($periods->pluck('id')->all(), $branchIds);

        $periods->through(fn (PayrollPeriod $period): array => $this->row($period, $runsByPeriod));

        return Inertia::render('payroll/Index', [
            'filters' => [
                'schedule_id' => isset($filters['schedule_id']) ? (int) $filters['schedule_id'] : null,
                'status' => $filters['status'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'per_page' => $perPage,
            ],
            'periods' => PaginatedPayload::from($periods),
            'options' => [
                'schedules' => PayrollSchedule::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'frequency'])
                    ->map(fn (PayrollSchedule $schedule): array => [
                        'id' => $schedule->id,
                        'name' => $schedule->name,
                        'frequency' => $schedule->frequency->value,
                    ])
                    ->values()
                    ->all(),
            ],
            'can' => $this->can($user),
            'notice' => $this->rules->notice(),
        ]);
    }

    public function store(StorePayrollPeriodRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $period = DB::transaction(fn (): PayrollPeriod => PayrollPeriod::query()->create([
            'payroll_schedule_id' => (int) $data['payroll_schedule_id'],
            'start_date' => CarbonImmutable::parse((string) $data['start_date'])->startOfDay(),
            'end_date' => CarbonImmutable::parse((string) $data['end_date'])->startOfDay(),
            'payment_date' => CarbonImmutable::parse((string) $data['payment_date'])->startOfDay(),
            'status' => PayrollPeriodStatus::Open,
        ]));

        return to_route('payroll.periods.show', $period)
            ->with('success', 'Periodo de pre-nómina creado. Captúralo y calcula la pre-nómina cuando esté listo.');
    }

    public function show(Request $request, PayrollPeriod $period): Response
    {
        /** @var User $user */
        $user = $request->user();
        $branchIds = $user->allowedBranchIds();
        $period->loadMissing('schedule:id,name,frequency');

        $runs = PayrollRun::query()
            ->where('payroll_period_id', $period->id)
            ->with([
                'branch:id,name',
                'items.employee:id,employee_number,first_name,last_name,second_last_name,position',
            ])
            ->withCount('items')
            ->when($branchIds !== null, fn (Builder $inner) => $inner->whereIn('branch_id', $branchIds))
            ->orderBy('branch_id')
            ->get();

        if ($runs->isEmpty() && $period->runs()->exists()) {
            abort(403, 'El periodo pertenece a sucursales que no puedes consultar.');
        }

        $totals = $this->totalsFromRuns($runs);
        $items = $runs
            ->flatMap(fn (PayrollRun $run) => $run->items->map(fn (PayrollItem $item): array => $this->itemRow($item, $run)))
            ->sortBy('employee.employee_number')
            ->values()
            ->all();

        return Inertia::render('payroll/Show', [
            'period' => [
                'id' => $period->id,
                'schedule' => [
                    'id' => $period->schedule->id,
                    'name' => $period->schedule->name,
                    'frequency' => $period->schedule->frequency->value,
                ],
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'payment_date' => $period->payment_date->toDateString(),
                'status' => $period->status->value,
                'runs_count' => $runs->count(),
                'items_count' => (int) $runs->sum('items_count'),
                'closed_at' => $period->closed_at?->toIso8601String(),
                'closed_by' => $period->closed_by,
            ],
            'runs' => $runs->map(fn (PayrollRun $run): array => [
                'id' => $run->id,
                'branch' => ['id' => $run->branch_id, 'name' => $run->branch->name],
                'status' => $run->status->value,
                'items_count' => (int) $run->items_count,
                'totals' => [
                    'earnings' => (string) $run->total_earnings,
                    'deductions' => (string) $run->total_deductions,
                    'net' => (string) $run->total_net,
                ],
                'calculated_at' => $run->calculated_at?->toIso8601String(),
                'reviewed_at' => $run->reviewed_at?->toIso8601String(),
                'closed_at' => $run->closed_at?->toIso8601String(),
            ])->values()->all(),
            'totals' => $totals,
            'items' => $items,
            'closed_snapshot_at' => $period->closed_at?->toIso8601String(),
            'can' => $this->can($user),
            'notice' => $this->rules->notice(),
        ]);
    }

    public function review(Request $request, PayrollPeriod $period): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensurePeriodWithinBranches($period, $user);

        try {
            $this->closer->review($period, $user, $request->ip(), $request->userAgent());
        } catch (PayrollStateException $exception) {
            return $this->payrollStateError($exception);
        }

        return back()->with('success', 'Periodo revisado. Los importes quedan listos para el cierre.');
    }

    public function close(ClosePayrollPeriodRequest $request, PayrollPeriod $period): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensurePeriodWithinBranches($period, $user);

        try {
            $this->closer->close($period, $user, $request->ip(), $request->userAgent());
        } catch (PayrollStateException $exception) {
            return $this->payrollStateError($exception);
        }

        return back()->with('success', 'Periodo cerrado. Los importes quedaron congelados y el periodo ya no es recalculable.');
    }

    /**
     * Revisar o cerrar congela los importes de todas las corridas del periodo:
     * un actor con sucursales acotadas no puede hacerlo si el periodo incluye
     * corridas de sucursales que no administra (D-02).
     */
    private function ensurePeriodWithinBranches(PayrollPeriod $period, User $user): void
    {
        $branchIds = $user->allowedBranchIds();

        if ($branchIds === null) {
            return;
        }

        $foreignRuns = $period->runs()
            ->when(
                $branchIds === [],
                fn (Builder $query) => $query,
                fn (Builder $query) => $query->whereNotIn('branch_id', $branchIds),
            )
            ->exists();

        abort_if(
            $foreignRuns,
            403,
            'El periodo incluye corridas de sucursales que no puedes administrar.',
        );
    }

    /**
     * Corridas del periodo agrupadas por periodo, ya filtradas por sucursal.
     *
     * @param  array<int, int>  $periodIds
     * @param  array<int, int>|null  $branchIds
     * @return array<int, array<int, PayrollRun>>
     */
    private function scopedRuns(array $periodIds, ?array $branchIds): array
    {
        if ($periodIds === []) {
            return [];
        }

        $runs = PayrollRun::query()
            ->whereIn('payroll_period_id', $periodIds)
            ->when($branchIds !== null, fn (Builder $inner) => $inner->whereIn('branch_id', $branchIds))
            ->get(['id', 'payroll_period_id', 'branch_id', 'status', 'total_earnings', 'total_deductions', 'total_net']);

        $grouped = [];

        foreach ($runs as $run) {
            $grouped[(int) $run->payroll_period_id][] = $run;
        }

        return $grouped;
    }

    /**
     * @param  array<int, array<int, PayrollRun>>  $runsByPeriod
     * @return array<string, mixed>
     */
    private function row(PayrollPeriod $period, array $runsByPeriod): array
    {
        $runs = $runsByPeriod[$period->id] ?? [];

        return [
            'id' => $period->id,
            'schedule' => [
                'id' => $period->schedule->id,
                'name' => $period->schedule->name,
                'frequency' => $period->schedule->frequency->value,
            ],
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'payment_date' => $period->payment_date->toDateString(),
            'status' => $period->status->value,
            'runs_count' => count($runs),
            'totals' => $this->totalsFromRuns(collect($runs)),
        ];
    }

    /**
     * Fila del colaborador dentro del detalle del periodo.
     *
     * Prop adicional (`items`) que permite enlazar el recibo imprimible
     * (`payroll.receipt`); el contrato §7.4 solo exige `runs`, por lo que el
     * frontend la trata como opcional.
     *
     * @return array<string, mixed>
     */
    private function itemRow(PayrollItem $item, PayrollRun $run): array
    {
        $base = PayrollMoney::toCents((string) $item->base_amount);
        $overtime = PayrollMoney::toCents((string) $item->overtime_amount);
        $bonus = PayrollMoney::toCents((string) $item->bonus_amount);
        $absence = PayrollMoney::toCents((string) $item->absence_deduction);
        $penalty = PayrollMoney::toCents((string) $item->adjustment_amount);

        return [
            'id' => $item->id,
            'employee' => [
                'id' => $item->employee_id,
                'full_name' => $item->employee->fullName(),
                'employee_number' => $item->employee->employee_number,
                'position' => $item->employee->position,
            ],
            'branch' => ['id' => $run->branch_id, 'name' => $run->branch->name],
            'net_amount' => (string) $item->net_amount,
            'totals' => [
                'earnings' => PayrollMoney::toDecimal($base + $overtime + $bonus),
                'deductions' => PayrollMoney::toDecimal($absence + $penalty),
                'net' => PayrollMoney::toDecimal($base + $overtime + $bonus - $absence - $penalty),
            ],
        ];
    }

    /**
     * @param  Collection<int, PayrollRun>  $runs
     * @return array{earnings: string, deductions: string, net: string}
     */
    private function totalsFromRuns(Collection $runs): array
    {
        $earnings = 0;
        $deductions = 0;

        foreach ($runs as $run) {
            $earnings += PayrollMoney::toCents((string) $run->total_earnings);
            $deductions += PayrollMoney::toCents((string) $run->total_deductions);
        }

        return [
            'earnings' => PayrollMoney::toDecimal($earnings),
            'deductions' => PayrollMoney::toDecimal($deductions),
            'net' => PayrollMoney::toDecimal($earnings - $deductions),
        ];
    }

    /**
     * @return array{calculate: bool, review: bool, close: bool}
     */
    private function can(User $user): array
    {
        return [
            'calculate' => $user->hasPermission('payroll.calculate'),
            'review' => $user->hasPermission('payroll.review'),
            'close' => $user->hasPermission('payroll.close'),
        ];
    }
}
