<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceStatus;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\AttendanceEvent;
use App\Models\AttendanceIncident;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\CarbonImmutable;

/**
 * Motor determinista de jornada (retardos, descansos, faltas y horas extra).
 *
 * No aplica reglas fiscales ni multiplicadores legales: solo minutos y una
 * propuesta de horas extra marcada con `rule_applied`.
 *
 * Reglas de cálculo:
 * - Retardo = entrada − inicio programado − tolerancia del día (nunca negativo).
 * - Tiempo trabajado = salida − entrada − descansos reales; si no hay eventos
 *   de descanso completos se usa el descanso programado del horario (S-06).
 * - Horas extra = minutos trabajados − minutos programados, solo si positivas y
 *   con la jornada completa.
 * - Exclusiones, en orden: día de descanso → festivo (global o de la sucursal)
 *   → incidencia aprobada → falta. Si hay marcajes de entrada, el día se
 *   evalúa como jornada (`complete`/`incomplete`) y la incidencia queda
 *   registrada en la fila.
 */
final class AttendanceDayCalculator
{
    public function __construct(
        private readonly ScheduleWindowResolver $resolver,
        private readonly ?CarbonImmutable $now = null,
    ) {}

    /**
     * Calcula un día consultando sus propios datos (uso puntual y pruebas).
     */
    public function calculate(Employee $employee, CarbonImmutable $workDate): AttendanceDayResult
    {
        $employee->loadMissing('branch');

        $window = $this->resolver->forEmployeeDate($employee, $workDate);
        $dateString = $window->workDate->toDateString();

        $events = AttendanceEvent::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $dateString)
            ->orderBy('occurred_at')
            ->get()
            ->all();

        $incidents = AttendanceIncident::query()
            ->where('employee_id', $employee->id)
            ->whereDate('start_date', '<=', $dateString)
            ->whereDate('end_date', '>=', $dateString)
            ->orderBy('id')
            ->get()
            ->all();

        $holidayNames = Holiday::query()
            ->whereDate('date', $dateString)
            ->forBranch($employee->branch_id)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $this->evaluate($employee, $workDate, $window, $events, $incidents, $holidayNames);
    }

    /**
     * Evalúa un día con datos ya cargados (permite calcular rangos sin N+1).
     *
     * @param  array<int, AttendanceEvent>  $events
     * @param  array<int, AttendanceIncident>  $incidents
     * @param  array<int, string>  $holidayNames
     */
    public function evaluate(
        Employee $employee,
        CarbonImmutable $workDate,
        ScheduleWindow $window,
        array $events,
        array $incidents,
        array $holidayNames = [],
    ): AttendanceDayResult {
        $validEvents = array_values(array_filter(
            $events,
            fn (AttendanceEvent $event): bool => $event->validation_status !== AttendanceStatus::Rejected,
        ));

        usort($validEvents, fn (AttendanceEvent $left, AttendanceEvent $right): int => $left->occurred_at <=> $right->occurred_at);

        $clockIn = null;
        $clockOut = null;
        $breakStart = null;
        $breakEnd = null;
        $pendingBreakStart = null;
        $breakPairs = [];

        foreach ($validEvents as $event) {
            $moment = $event->occurred_at->toImmutable();

            switch ($event->type) {
                case AttendanceEventType::ClockIn:
                    $clockIn ??= $moment;
                    break;
                case AttendanceEventType::BreakStart:
                    $breakStart ??= $moment;
                    $pendingBreakStart ??= $moment;
                    break;
                case AttendanceEventType::BreakEnd:
                    $breakEnd = $moment;

                    if ($pendingBreakStart !== null && $moment->greaterThan($pendingBreakStart)) {
                        $breakPairs[] = [$pendingBreakStart, $moment];
                        $pendingBreakStart = null;
                    }
                    break;
                case AttendanceEventType::ClockOut:
                    $clockOut = $moment;
                    break;
            }
        }

        if ($clockOut !== null && $clockIn !== null && $clockOut->lessThan($clockIn)) {
            $clockOut = null;
        }

        $presence = $clockIn !== null;
        $spanMinutes = 0;

        if ($clockIn !== null) {
            $spanMinutes = self::minutesBetween($clockIn, $clockOut ?? $this->now());
        }

        $realBreakMinutes = 0;

        foreach ($breakPairs as [$start, $end]) {
            $realBreakMinutes += self::minutesBetween($start, $end);
        }

        $breakMinutes = $breakPairs !== []
            ? $realBreakMinutes
            : ($presence ? $window->scheduledBreakMinutes() : 0);

        $workedMinutes = max(0, $spanMinutes - $breakMinutes);

        $scheduledStart = $window->scheduledStart();
        $lateMinutes = 0;

        if ($clockIn !== null && $scheduledStart !== null) {
            $lateRaw = (int) round($scheduledStart->diffInMinutes($clockIn, false));
            $lateMinutes = max(0, $lateRaw - $window->lateToleranceMinutes);
        }

        $status = $this->resolveStatus($window, $clockIn, $clockOut, $incidents, $holidayNames);

        $overtimeMinutes = 0;
        $ruleApplied = null;

        if ($status === AttendanceDayResult::STATUS_COMPLETE) {
            $overtimeMinutes = max(0, $workedMinutes - $window->scheduledMinutes());
            $ruleApplied = $overtimeMinutes > 0 ? AttendanceDayResult::OVERTIME_RULE : null;
        }

        return new AttendanceDayResult(
            employeeId: $employee->id,
            branchId: $employee->branch_id,
            workDate: $window->workDate->toDateString(),
            scheduledStart: $scheduledStart?->format('H:i'),
            scheduledEnd: $window->scheduledEnd()?->format('H:i'),
            lateToleranceMinutes: $window->lateToleranceMinutes,
            isRestDay: ! $window->isWorkingDay(),
            clockIn: $clockIn,
            breakStart: $breakStart,
            breakEnd: $breakEnd,
            clockOut: $clockOut,
            workedMinutes: $workedMinutes,
            breakMinutes: $breakMinutes,
            lateMinutes: $lateMinutes,
            overtimeMinutes: $overtimeMinutes,
            status: $status,
            ruleApplied: $ruleApplied,
            incidents: $this->incidentRows($incidents),
        );
    }

    /**
     * @param  array<int, AttendanceIncident>  $incidents
     * @param  array<int, string>  $holidayNames
     */
    private function resolveStatus(
        ScheduleWindow $window,
        ?CarbonImmutable $clockIn,
        ?CarbonImmutable $clockOut,
        array $incidents,
        array $holidayNames,
    ): string {
        if ($clockIn !== null) {
            return $clockOut !== null
                ? AttendanceDayResult::STATUS_COMPLETE
                : AttendanceDayResult::STATUS_INCOMPLETE;
        }

        if (! $window->isWorkingDay()) {
            return AttendanceDayResult::STATUS_REST_DAY;
        }

        if ($holidayNames !== []) {
            return AttendanceDayResult::STATUS_HOLIDAY;
        }

        $approved = array_values(array_filter(
            $incidents,
            fn (AttendanceIncident $incident): bool => $incident->status === IncidentStatus::Approved,
        ));

        return $this->exclusionStatus($approved) ?? AttendanceDayResult::STATUS_ABSENCE;
    }

    /**
     * @param  array<int, AttendanceIncident>  $approvedIncidents
     */
    private function exclusionStatus(array $approvedIncidents): ?string
    {
        foreach ($approvedIncidents as $incident) {
            $status = match ($incident->type) {
                IncidentType::Vacation => AttendanceDayResult::STATUS_VACATION,
                IncidentType::JustifiedAbsence,
                IncidentType::SickLeave,
                IncidentType::PaidLeave,
                IncidentType::UnpaidLeave => AttendanceDayResult::STATUS_INCIDENT,
                default => null,
            };

            if ($status !== null) {
                return $status;
            }
        }

        return null;
    }

    /**
     * @param  array<int, AttendanceIncident>  $incidents
     * @return array<int, array{id: int, type: string, status: string}>
     */
    private function incidentRows(array $incidents): array
    {
        $rows = array_map(
            fn (AttendanceIncident $incident): array => [
                'id' => $incident->id,
                'type' => $incident->type->value,
                'status' => $incident->status->value,
            ],
            array_values($incidents),
        );

        usort($rows, fn (array $left, array $right): int => $left['id'] <=> $right['id']);

        return $rows;
    }

    private function now(): CarbonImmutable
    {
        return $this->now ?? CarbonImmutable::now();
    }

    private static function minutesBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return max(0, (int) round($start->diffInMinutes($end)));
    }
}
