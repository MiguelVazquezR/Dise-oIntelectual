<?php

namespace App\Services\Attendance;

use App\Models\Branch;
use App\Models\BranchSchedule;
use App\Models\BranchScheduleDay;
use App\Models\BranchScheduleSegment;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Resuelve el horario vigente de una sucursal para una `work_date`.
 *
 * Reglas:
 * - Vigencia por `effective_from`/`effective_to`; si hay varios vigentes se usa
 *   el de `effective_from` más reciente.
 * - La fecha se interpreta en la zona horaria de la sucursal (D-08).
 * - Un segmento con `end_time <= start_time` cruza medianoche (D-09).
 * - Sin horario vigente o día sin segmentos: el día se considera descanso.
 *
 * Los horarios de cada sucursal se cargan una sola vez por instancia para
 * evitar N+1 al calcular rangos completos.
 */
final class ScheduleWindowResolver
{
    /**
     * @var array<int, Collection<int, BranchSchedule>>
     */
    private array $scheduleCache = [];

    public function forEmployeeDate(Employee $employee, CarbonImmutable $workDate): ScheduleWindow
    {
        $employee->loadMissing('branch');

        return $this->forBranchDate($employee->branch, $workDate);
    }

    public function forBranchDate(Branch $branch, CarbonImmutable $workDate): ScheduleWindow
    {
        $timezone = $branch->timezone;
        $localDate = CarbonImmutable::parse($workDate->toDateString(), $timezone)->startOfDay();
        $weekday = (int) $localDate->dayOfWeekIso;
        $schedule = $this->resolveSchedule($branch->id, $localDate);

        if ($schedule === null) {
            return new ScheduleWindow($timezone, $localDate, $weekday, true, 0, [], []);
        }

        /** @var BranchScheduleDay|null $day */
        $day = $schedule->days->first(
            fn (BranchScheduleDay $candidate): bool => (int) $candidate->weekday->value === $weekday,
        );

        if ($day === null) {
            return new ScheduleWindow(
                timezone: $timezone,
                workDate: $localDate,
                weekday: $weekday,
                isRestDay: true,
                lateToleranceMinutes: 0,
                workSegments: [],
                breakSegments: [],
                scheduleId: $schedule->id,
                scheduleName: $schedule->name,
            );
        }

        if ($day->is_rest_day || $day->segments->isEmpty()) {
            return new ScheduleWindow(
                timezone: $timezone,
                workDate: $localDate,
                weekday: $weekday,
                isRestDay: true,
                lateToleranceMinutes: $day->late_tolerance_minutes,
                workSegments: [],
                breakSegments: [],
                scheduleId: $schedule->id,
                scheduleName: $schedule->name,
            );
        }

        $workSegments = [];
        $breakSegments = [];

        foreach ($day->segments as $segment) {
            $start = $this->moment($localDate, $segment->start_time);
            $end = $this->moment($localDate, $segment->end_time);

            if (! $end->greaterThan($start)) {
                $end = $end->addDay();
            }

            $interval = ['start' => $start, 'end' => $end];

            if ($segment->kind === BranchScheduleSegment::KIND_WORK) {
                $workSegments[] = $interval;
            } else {
                $breakSegments[] = $interval;
            }
        }

        return new ScheduleWindow(
            timezone: $timezone,
            workDate: $localDate,
            weekday: $weekday,
            isRestDay: $workSegments === [],
            lateToleranceMinutes: $day->late_tolerance_minutes,
            workSegments: $workSegments,
            breakSegments: $breakSegments,
            scheduleId: $schedule->id,
            scheduleName: $schedule->name,
        );
    }

    private function resolveSchedule(int $branchId, CarbonImmutable $date): ?BranchSchedule
    {
        $dateString = $date->toDateString();

        return $this->schedulesFor($branchId)->first(
            fn (BranchSchedule $schedule): bool => $schedule->effective_from->toDateString() <= $dateString
                && ($schedule->effective_to === null || $schedule->effective_to->toDateString() >= $dateString),
        );
    }

    /**
     * @return Collection<int, BranchSchedule>
     */
    private function schedulesFor(int $branchId): Collection
    {
        return $this->scheduleCache[$branchId] ??= BranchSchedule::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->with(['days.segments'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();
    }

    private function moment(CarbonImmutable $date, string $time): CarbonImmutable
    {
        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $date->setTime($hours, $minutes, 0);
    }
}
