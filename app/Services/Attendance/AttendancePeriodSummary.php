<?php

namespace App\Services\Attendance;

use App\Enums\EmployeeStatus;
use App\Models\AttendanceEvent;
use App\Models\AttendanceIncident;
use App\Models\Employee;
use App\Models\Holiday;
use App\Support\BranchAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Agrega la jornada de un rango de fechas por colaborador y sucursal.
 *
 * Carga en bloque eventos, incidencias y festivos del rango para no repetir
 * consultas por cada día, y delega el cálculo en `AttendanceDayCalculator`.
 * Toda consulta de colaboradores pasa por `BranchAccess` (D-02).
 */
final class AttendancePeriodSummary
{
    /** Zona usada solo para el rango por defecto cuando no se filtra por sucursal. */
    public const DEFAULT_TIMEZONE = 'America/Mexico_City';

    /** Días por defecto del listado cuando no se envían fechas. */
    public const DEFAULT_DAYS = 15;

    /** Máximo de días que se pueden calcular en una sola consulta. */
    public const MAX_DAYS = 62;

    public function __construct(
        private readonly AttendanceDayCalculator $calculator,
        private readonly ScheduleWindowResolver $resolver,
    ) {}

    /**
     * Colaboradores visibles con filtros del listado.
     *
     * Solo se listan colaboradores activos: las bajas y los inactivos no se
     * evalúan como faltas en el listado operativo.
     *
     * @return Builder<Employee>
     */
    public function employeesQuery(?int $branchId = null, ?int $employeeId = null, ?string $search = null): Builder
    {
        return BranchAccess::scope(Employee::query()->with('branch:id,name,timezone'))
            ->where('status', EmployeeStatus::Active)
            ->when($branchId, fn (Builder $query, int $id) => $query->where('branch_id', $id))
            ->when($employeeId, fn (Builder $query, int $id) => $query->where('id', $id))
            ->when(
                $search,
                function (Builder $query, string $term): void {
                    $query->where(function (Builder $inner) use ($term): void {
                        $inner->where('employee_number', 'like', "%{$term}%")
                            ->orWhere('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('second_last_name', 'like', "%{$term}%");
                    });
                },
            )
            ->orderBy('employee_number');
    }

    /**
     * Calcula la jornada de cada colaborador para cada día del rango.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, AttendanceDayResult>
     */
    public function daysFor(Collection $employees, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $employeeIds = $employees->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $branchIds = $employees->pluck('branch_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();

        $events = AttendanceEvent::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('work_date', '>=', $fromDate)
            ->whereDate('work_date', '<=', $toDate)
            ->orderBy('occurred_at')
            ->get(['id', 'employee_id', 'type', 'occurred_at', 'work_date', 'validation_status'])
            ->groupBy(
                fn (AttendanceEvent $event): string => $event->employee_id.'|'.$event->work_date->toDateString(),
            );

        $incidents = AttendanceIncident::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('start_date', '<=', $toDate)
            ->whereDate('end_date', '>=', $fromDate)
            ->orderBy('id')
            ->get(['id', 'employee_id', 'type', 'start_date', 'end_date', 'status'])
            ->groupBy('employee_id');

        $holidays = Holiday::query()
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->where(function (Builder $query) use ($branchIds): void {
                $query->whereNull('branch_id');

                if ($branchIds !== []) {
                    $query->orWhereIn('branch_id', $branchIds);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'date', 'branch_id']);

        /** @var array<string, array<int, string>> $holidayNames */
        $holidayNames = [];

        foreach ($holidays as $holiday) {
            $key = ($holiday->branch_id ?? 0).'|'.$holiday->date->toDateString();
            $holidayNames[$key][] = $holiday->name;
        }

        $dates = [];

        for ($cursor = $from->startOfDay(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            $dates[] = $cursor->toDateString();
        }

        $results = [];

        foreach ($employees as $employee) {
            $employee->loadMissing('branch');
            $branch = $employee->branch;
            $employeeIncidents = $incidents->get($employee->id, collect());

            foreach ($dates as $dateString) {
                $workDate = CarbonImmutable::parse($dateString, $branch->timezone);
                $window = $this->resolver->forBranchDate($branch, $workDate);

                $dayEvents = $events->get($employee->id.'|'.$dateString, collect())->all();

                $dayIncidents = $employeeIncidents
                    ->filter(
                        fn (AttendanceIncident $incident): bool => $incident->start_date->toDateString() <= $dateString
                            && $incident->end_date->toDateString() >= $dateString,
                    )
                    ->values()
                    ->all();

                $results[] = $this->calculator->evaluate(
                    $employee,
                    $workDate,
                    $window,
                    $dayEvents,
                    $dayIncidents,
                    array_merge(
                        $holidayNames['0|'.$dateString] ?? [],
                        $holidayNames[$employee->branch_id.'|'.$dateString] ?? [],
                    ),
                );
            }
        }

        return $results;
    }

    /**
     * Filas del contrato §7.3 para el listado de asistencias.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $employees, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->rowsFrom($employees, $this->daysFor($employees, $from, $to));
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  array<int, AttendanceDayResult>  $results
     * @return array<int, array<string, mixed>>
     */
    public function rowsFrom(Collection $employees, array $results): array
    {
        $indexed = $employees->keyBy('id');
        $rows = [];

        foreach ($results as $result) {
            $employee = $indexed->get($result->employeeId);

            if ($employee instanceof Employee) {
                $rows[] = AttendanceRowPresenter::fromResult($result, $employee);
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, AttendanceDayResult>  $results
     * @return array{employees: int, late_count: int, absence_count: int, overtime_minutes: int, worked_minutes: int}
     */
    public function summarize(array $results): array
    {
        $employeeIds = [];
        $lateCount = 0;
        $absenceCount = 0;
        $overtimeMinutes = 0;
        $workedMinutes = 0;

        foreach ($results as $result) {
            $employeeIds[$result->employeeId] = true;

            if ($result->lateMinutes > 0) {
                $lateCount++;
            }

            if ($result->status === AttendanceDayResult::STATUS_ABSENCE) {
                $absenceCount++;
            }

            $overtimeMinutes += $result->overtimeMinutes;
            $workedMinutes += $result->workedMinutes;
        }

        return [
            'employees' => count($employeeIds),
            'late_count' => $lateCount,
            'absence_count' => $absenceCount,
            'overtime_minutes' => $overtimeMinutes,
            'worked_minutes' => $workedMinutes,
        ];
    }

    /**
     * Totales de la ficha de un colaborador.
     *
     * @param  array<int, AttendanceDayResult>  $results
     * @return array{worked_minutes: int, late_minutes: int, overtime_minutes: int, absences: int, vacations: int}
     */
    public function totals(array $results): array
    {
        $totals = [
            'worked_minutes' => 0,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
            'absences' => 0,
            'vacations' => 0,
        ];

        foreach ($results as $result) {
            $totals['worked_minutes'] += $result->workedMinutes;
            $totals['late_minutes'] += $result->lateMinutes;
            $totals['overtime_minutes'] += $result->overtimeMinutes;

            if ($result->status === AttendanceDayResult::STATUS_ABSENCE) {
                $totals['absences']++;
            }

            if ($result->status === AttendanceDayResult::STATUS_VACATION) {
                $totals['vacations']++;
            }
        }

        return $totals;
    }
}
