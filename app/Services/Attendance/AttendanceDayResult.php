<?php

namespace App\Services\Attendance;

use Carbon\CarbonImmutable;

/**
 * Resultado del cálculo de un día de jornada (contrato §7.3).
 *
 * Los estados posibles se documentan en las constantes `STATUS_*`.
 */
final class AttendanceDayResult
{
    public const STATUS_COMPLETE = 'complete';

    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUS_ABSENCE = 'absence';

    public const STATUS_REST_DAY = 'rest_day';

    public const STATUS_HOLIDAY = 'holiday';

    public const STATUS_VACATION = 'vacation';

    public const STATUS_INCIDENT = 'incident';

    /** Texto de la regla propuesta para horas extra (sin multiplicadores legales). */
    public const OVERTIME_RULE = 'Horas extra propuestas: minutos trabajados − minutos programados (propuesta interna, sin multiplicadores legales)';

    /**
     * @param  array<int, array{id: int, type: string, status: string}>  $incidents
     */
    public function __construct(
        public readonly int $employeeId,
        public readonly int $branchId,
        public readonly string $workDate,
        public readonly ?string $scheduledStart,
        public readonly ?string $scheduledEnd,
        public readonly int $lateToleranceMinutes,
        public readonly bool $isRestDay,
        public readonly ?CarbonImmutable $clockIn,
        public readonly ?CarbonImmutable $breakStart,
        public readonly ?CarbonImmutable $breakEnd,
        public readonly ?CarbonImmutable $clockOut,
        public readonly int $workedMinutes,
        public readonly int $breakMinutes,
        public readonly int $lateMinutes,
        public readonly int $overtimeMinutes,
        public readonly string $status,
        public readonly ?string $ruleApplied = null,
        public readonly array $incidents = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $withIncidents = true): array
    {
        $payload = [
            'work_date' => $this->workDate,
            'scheduled' => [
                'start' => $this->scheduledStart,
                'end' => $this->scheduledEnd,
                'tolerance_minutes' => $this->lateToleranceMinutes,
                'is_rest_day' => $this->isRestDay,
            ],
            'clock_in' => self::iso($this->clockIn),
            'break_start' => self::iso($this->breakStart),
            'break_end' => self::iso($this->breakEnd),
            'clock_out' => self::iso($this->clockOut),
            'worked_minutes' => $this->workedMinutes,
            'break_minutes' => $this->breakMinutes,
            'late_minutes' => $this->lateMinutes,
            'overtime_minutes' => $this->overtimeMinutes,
            'status' => $this->status,
            'rule_applied' => $this->ruleApplied,
        ];

        if ($withIncidents) {
            $payload['incidents'] = $this->incidents;
        }

        return $payload;
    }

    private static function iso(?CarbonImmutable $moment): ?string
    {
        return $moment?->utc()->toIso8601String();
    }
}
