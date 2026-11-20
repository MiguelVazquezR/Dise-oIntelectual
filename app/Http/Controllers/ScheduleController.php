<?php

namespace App\Http\Controllers;

use App\Enums\Weekday;
use App\Http\Requests\Schedules\IndexScheduleRequest;
use App\Http\Requests\Schedules\StoreScheduleRequest;
use App\Http\Requests\Schedules\UpdateScheduleRequest;
use App\Models\BranchSchedule;
use App\Models\BranchScheduleDay;
use App\Models\BranchScheduleSegment;
use App\Models\User;
use App\Support\BranchAccess;
use App\Support\Branches\BranchOptions;
use App\Support\Pagination\PaginatedPayload;
use App\Support\Schedules\ScheduleTree;
use App\Support\Schedules\TimeSegments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    /**
     * Listado de horarios visibles para el usuario.
     */
    public function index(IndexScheduleRequest $request): Response
    {
        $this->authorize('viewAny', BranchSchedule::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();

        $schedules = $this->filteredQuery($request)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (BranchSchedule $schedule): array => $this->listRow($schedule));

        return Inertia::render('schedules/Index', [
            'filters' => [
                'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
                'search' => $filters['search'] ?? null,
            ],
            'schedules' => PaginatedPayload::from($schedules),
            'options' => [
                'branches' => BranchOptions::forUser($user),
            ],
            'can' => [
                'manage' => $user->hasPermission('schedules.manage'),
            ],
        ]);
    }

    /**
     * Formulario de alta de horario.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', BranchSchedule::class);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('schedules/Create', [
            'schedule' => null,
            'options' => [
                'branches' => BranchOptions::forUser($user),
            ],
            'weekdays' => $this->weekdayOptions(),
            'can' => [
                'manage' => $user->hasPermission('schedules.manage'),
            ],
        ]);
    }

    /**
     * Crea un horario con sus días y segmentos.
     */
    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        $this->authorize('create', BranchSchedule::class);

        $data = $request->validated();

        $schedule = DB::transaction(function () use ($request, $data): BranchSchedule {
            $schedule = BranchSchedule::query()->create([
                'branch_id' => $data['branch_id'],
                'name' => $data['name'],
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            ScheduleTree::replace($schedule, $data['days']);

            return $schedule;
        });

        return to_route('schedules.index')
            ->with('success', "Horario «{$schedule->name}» creado correctamente.");
    }

    /**
     * Formulario de edición de horario.
     */
    public function edit(Request $request, BranchSchedule $schedule): Response
    {
        $this->authorize('update', $schedule);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('schedules/Edit', [
            'schedule' => $this->schedulePayload($schedule),
            'options' => [
                'branches' => BranchOptions::forUser($user),
            ],
            'weekdays' => $this->weekdayOptions(),
            'can' => [
                'manage' => $user->hasPermission('schedules.manage'),
            ],
        ]);
    }

    /**
     * Reemplaza los datos del horario y su árbol de días/segmentos.
     */
    public function update(UpdateScheduleRequest $request, BranchSchedule $schedule): RedirectResponse
    {
        $this->authorize('update', $schedule);

        $data = $request->validated();

        DB::transaction(function () use ($request, $schedule, $data): void {
            $schedule->update([
                'branch_id' => $data['branch_id'],
                'name' => $data['name'],
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'is_active' => $request->boolean('is_active', $schedule->is_active),
            ]);

            ScheduleTree::replace($schedule, $data['days']);
        });

        return to_route('schedules.index')
            ->with('success', "Horario «{$schedule->name}» actualizado correctamente.");
    }

    /**
     * Elimina un horario y su árbol de días/segmentos.
     */
    public function destroy(BranchSchedule $schedule): RedirectResponse
    {
        $this->authorize('delete', $schedule);

        $name = $schedule->name;

        DB::transaction(function () use ($schedule): void {
            ScheduleTree::purge($schedule);
            $schedule->delete();
        });

        return to_route('schedules.index')
            ->with('success', "Horario «{$name}» eliminado correctamente.");
    }

    /**
     * @return Builder<BranchSchedule>
     */
    private function filteredQuery(IndexScheduleRequest $request): Builder
    {
        $filters = $request->validated();

        return BranchAccess::scope(
            BranchSchedule::query()
                ->with('branch:id,name')
                ->withCount('days'),
        )
            ->when(
                $filters['branch_id'] ?? null,
                fn (Builder $query, int|string $branchId) => $query->where('branch_id', (int) $branchId),
            )
            ->when(
                $filters['search'] ?? null,
                function (Builder $query, string $search): void {
                    $query->where(function (Builder $inner) use ($search): void {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhereHas('branch', function (Builder $branch) use ($search): void {
                                $branch->where('name', 'like', "%{$search}%")
                                    ->orWhere('code', 'like', "%{$search}%");
                            });
                    });
                },
            )
            ->orderByDesc('effective_from')
            ->orderBy('name');
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(BranchSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'branch' => [
                'id' => $schedule->branch->id,
                'name' => $schedule->branch->name,
            ],
            'effective_from' => $schedule->effective_from->toDateString(),
            'effective_to' => $schedule->effective_to?->toDateString(),
            'is_active' => $schedule->is_active,
            'days_count' => (int) ($schedule->days_count ?? 0),
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function weekdayOptions(): array
    {
        return array_map(
            fn (Weekday $weekday): array => [
                'value' => $weekday->value,
                'label' => $weekday->label(),
            ],
            Weekday::cases(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulePayload(BranchSchedule $schedule): array
    {
        $schedule->loadMissing(['days.segments']);

        $days = [];

        foreach (Weekday::cases() as $weekday) {
            /** @var BranchScheduleDay|null $day */
            $day = $schedule->days->first(
                fn (BranchScheduleDay $candidate): bool => $candidate->weekday->value === $weekday->value,
            );

            $days[] = [
                'weekday' => $weekday->value,
                'is_rest_day' => $day->is_rest_day ?? true,
                'late_tolerance_minutes' => $day->late_tolerance_minutes ?? 10,
                'notes' => $day?->notes,
                'segments' => $day instanceof BranchScheduleDay
                    ? $day->segments->map(fn (BranchScheduleSegment $segment): array => [
                        'start_time' => TimeSegments::toHhMm($segment->start_time),
                        'end_time' => TimeSegments::toHhMm($segment->end_time),
                        'kind' => $segment->kind,
                        'sort_order' => $segment->sort_order,
                    ])->values()->all()
                    : [],
            ];
        }

        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'branch_id' => $schedule->branch_id,
            'effective_from' => $schedule->effective_from->toDateString(),
            'effective_to' => $schedule->effective_to?->toDateString(),
            'is_active' => $schedule->is_active,
            'days' => $days,
        ];
    }
}
