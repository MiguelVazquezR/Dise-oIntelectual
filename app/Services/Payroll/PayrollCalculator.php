<?php

namespace App\Services\Payroll;

use App\Enums\AdjustmentStatus;
use App\Enums\EmployeeStatus;
use App\Enums\IncidentStatus;
use App\Enums\PayrollConceptCalculation;
use App\Enums\PayrollConceptType;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollRunStatus;
use App\Models\AttendanceIncident;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\EmployeePayrollAdjustment;
use App\Models\Holiday;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSchedule;
use App\Models\User;
use App\Services\Attendance\AttendanceDayResult;
use App\Services\Attendance\AttendancePeriodSummary;
use App\Services\Payroll\Exceptions\PayrollNothingToCalculateException;
use App\Services\Payroll\Exceptions\PayrollPeriodClosedException;
use App\Services\Payroll\Exceptions\PayrollRecalculationRequiresForceException;
use App\Services\Payroll\Exceptions\PayrollStateException;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cálculo de la pre-nómina por periodo, sucursal y colaborador (OLA 4-A).
 *
 * Reglas internas (no fiscales, D-14); todo el dinero se opera en centavos:
 *
 * - `base_amount` = sueldo del colaborador × (días naturales del periodo ÷ días
 *   naturales nominales de su frecuencia de pago). Ej.: sueldo mensual
 *   32000.00 en un periodo quincenal de 15 días = 16000.00.
 * - `overtime_amount` = (minutos extra del motor de asistencias ÷ 60) × tarifa
 *   horaria × `overtime_factor`. Tarifa horaria = base ÷ (días laborables
 *   nominales × horas por día).
 * - `absence_deduction` = días con estatus `absence` (falta injustificada sin
 *   incidencia aprobada) × tarifa diaria × `absence_factor`.
 * - `late_minutes` es solo informativo: no genera descuento automático.
 * - Ajustes aprobados del periodo (y los de periodo nulo aprobados dentro del
 *   periodo) suman a `bonus_amount` (percepciones) o a `adjustment_amount`
 *   (deducciones/penalizaciones). Cada uno deja un concepto trazable con
 *   `source_type`/`source_id`.
 * - `net_amount` = base + horas extra + bonos − faltas − penalizaciones.
 *
 * Idempotencia: el recálculo reemplaza items y conceptos de cada corrida
 * dentro de una transacción; nunca duplica. Un periodo `closed` no se toca
 * (409) y uno `reviewed` exige `force` explícito.
 */
final class PayrollCalculator
{
    public const CONCEPT_BASE = 'SUELDO_BASE';

    public const CONCEPT_OVERTIME = 'HORAS_EXTRA';

    public const CONCEPT_ABSENCE = 'FALTA_INJUSTIFICADA';

    /** Origen de los conceptos derivados del motor de asistencias. */
    private const SOURCE_ATTENDANCE = 'attendance_period_summary';

    public function __construct(
        private readonly AttendancePeriodSummary $attendance,
        private readonly PayrollRules $rules,
        private readonly PayrollAuditRecorder $auditor,
    ) {}

    public function calculate(
        PayrollPeriod $period,
        User $actor,
        bool $force = false,
        ?string $note = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): PayrollCalculationOutcome {
        if (! $actor->hasPermission('payroll.calculate')) {
            throw new AuthorizationException('No tienes permiso para calcular la pre-nómina.');
        }

        if ($period->status === PayrollPeriodStatus::Closed) {
            throw new PayrollPeriodClosedException('El periodo está cerrado: no se puede recalcular.');
        }

        if ($period->status === PayrollPeriodStatus::Reviewed && ! $force) {
            throw new PayrollRecalculationRequiresForceException('El periodo ya fue revisado; confirma el recálculo para reemplazar los importes.');
        }

        $period->loadMissing('schedule');
        $schedule = $period->schedule;

        $start = CarbonImmutable::parse($period->start_date->toDateString())->startOfDay();
        $end = CarbonImmutable::parse($period->end_date->toDateString())->startOfDay();

        if ($end->lessThan($start)) {
            throw new PayrollStateException('El periodo tiene fechas inválidas: la fecha final precede a la inicial.');
        }

        $employees = $this->eligibleEmployees($actor, $start, $end);

        if ($employees->isEmpty()) {
            throw new PayrollNothingToCalculateException('No hay colaboradores activos con compensación vigente en tus sucursales; no se generó ninguna corrida.');
        }

        $attendanceByEmployee = $this->groupAttendanceByEmployee($employees, $start, $end);
        $adjustmentsByEmployee = $this->approvedAdjustments($period, $employees)->groupBy('employee_id');
        $before = $this->auditor->snapshot($period);
        $wasRecalculated = $period->status !== PayrollPeriodStatus::Open;
        $now = CarbonImmutable::now();

        $earningsCents = 0;
        $deductionsCents = 0;
        $itemsCount = 0;
        $runsCount = 0;

        DB::transaction(function () use (
            $period,
            $schedule,
            $actor,
            $employees,
            $attendanceByEmployee,
            $adjustmentsByEmployee,
            $start,
            $end,
            $now,
            $force,
            $note,
            &$earningsCents,
            &$deductionsCents,
            &$itemsCount,
            &$runsCount,
        ): void {
            $this->removeStaleRuns($period, $actor, $employees);

            $branches = $employees->groupBy('branch_id');

            foreach ($branches as $branchEmployees) {
                $branch = $branchEmployees->first()?->branch;

                if ($branch === null) {
                    continue;
                }

                $run = PayrollRun::query()->updateOrCreate(
                    [
                        'payroll_period_id' => $period->id,
                        'branch_id' => $branch->id,
                    ],
                    [
                        'status' => PayrollRunStatus::Calculated,
                        'calculated_at' => $now,
                        'calculated_by' => $actor->id,
                        'reviewed_at' => null,
                        'reviewed_by' => null,
                        'closed_at' => null,
                        'closed_by' => null,
                    ],
                );

                $run->items()->delete();

                $runEarnings = 0;
                $runDeductions = 0;
                $itemSummaries = [];

                foreach ($branchEmployees as $employee) {
                    $compensation = $this->compensationFor($employee, $start);

                    if (! $compensation instanceof EmployeeCompensation) {
                        continue;
                    }

                    $built = $this->buildItem(
                        $employee,
                        $compensation,
                        $schedule,
                        $start,
                        $end,
                        $attendanceByEmployee[$employee->id] ?? [],
                        $adjustmentsByEmployee->get($employee->id, collect()),
                        $note,
                    );

                    $item = PayrollItem::query()->create([
                        'payroll_run_id' => $run->id,
                        'employee_id' => $employee->id,
                        ...$built['attributes'],
                    ]);

                    if ($built['concepts'] !== []) {
                        $item->concepts()->createMany($built['concepts']);
                    }

                    foreach ($built['adjustment_ids'] as $adjustmentId) {
                        EmployeePayrollAdjustment::query()
                            ->whereKey($adjustmentId)
                            ->update(['applied_payroll_item_id' => $item->id]);
                    }

                    $runEarnings += $built['earnings'];
                    $runDeductions += $built['deductions'];
                    $itemsCount++;
                    $itemSummaries[] = $built['summary'];
                }

                $snapshotDetails = [
                    'branch_name' => $branch->name,
                    'calculated_at' => $now->toIso8601String(),
                    'calculated_by' => $actor->id,
                    'forced' => $force,
                    'note' => $note,
                    'items' => $itemSummaries,
                ];

                $run->update([
                    'total_earnings' => PayrollMoney::toDecimal($runEarnings),
                    'total_deductions' => PayrollMoney::toDecimal($runDeductions),
                    'total_net' => PayrollMoney::toDecimal($runEarnings - $runDeductions),
                    'rules_snapshot' => $this->rulesSnapshot($period, $schedule, $branch->id, $snapshotDetails, $start, $end),
                ]);

                $earningsCents += $runEarnings;
                $deductionsCents += $runDeductions;
                $runsCount++;
            }

            $period->update([
                'status' => PayrollPeriodStatus::Calculated,
            ]);
        });

        $outcome = new PayrollCalculationOutcome(
            runs: $runsCount,
            items: $itemsCount,
            earningsCents: $earningsCents,
            deductionsCents: $deductionsCents,
            recalculated: $wasRecalculated,
            forced: $force,
            note: $note,
        );

        $this->auditor->calculated($period->refresh(), $actor, [
            ...$this->auditor->snapshot($period),
            'outcome' => $outcome->toArray(),
            'forced' => $force,
            'note' => $note,
        ], $before, $ipAddress, $userAgent);

        return $outcome;
    }

    /**
     * Colaboradores activos con compensación vigente al inicio del periodo y
     * visibles para el actor (`BranchAccess`, D-02).
     *
     * @return Collection<int, Employee>
     */
    private function eligibleEmployees(User $actor, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $query = Employee::query()
            ->with(['branch', 'compensations'])
            ->where('status', EmployeeStatus::Active)
            ->whereDate('hire_date', '<=', $end->toDateString())
            ->where(function (Builder $inner) use ($start): void {
                $inner->whereNull('termination_date')
                    ->orWhereDate('termination_date', '>=', $start->toDateString());
            });

        $branchIds = $actor->allowedBranchIds();

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }

        return $query
            ->orderBy('employee_number')
            ->get()
            ->filter(fn (Employee $employee): bool => $this->compensationFor($employee, $start) instanceof EmployeeCompensation)
            ->values();
    }

    /**
     * Compensación vigente en la fecha indicada (la de `effective_from` más reciente).
     */
    private function compensationFor(Employee $employee, CarbonImmutable $date): ?EmployeeCompensation
    {
        $dateString = $date->toDateString();

        $compensation = $employee->compensations
            ->filter(fn (EmployeeCompensation $candidate): bool => $candidate->effective_from->toDateString() <= $dateString
                && ($candidate->effective_to === null || $candidate->effective_to->toDateString() >= $dateString))
            ->sortByDesc(fn (EmployeeCompensation $candidate): string => $candidate->effective_from->toDateString().'|'.str_pad((string) $candidate->id, 10, '0', STR_PAD_LEFT))
            ->first();

        return $compensation instanceof EmployeeCompensation ? $compensation : null;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array<int, AttendanceDayResult>>
     */
    private function groupAttendanceByEmployee(Collection $employees, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $grouped = [];

        foreach ($this->attendance->daysFor($employees, $start, $end) as $dayResult) {
            $grouped[$dayResult->employeeId][] = $dayResult;
        }

        return $grouped;
    }

    /**
     * Ajustes aprobados que aplican al periodo: los ligados al periodo y los de
     * periodo nulo aprobados dentro del periodo (asignación manual).
     *
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, EmployeePayrollAdjustment>
     */
    private function approvedAdjustments(PayrollPeriod $period, Collection $employees): Collection
    {
        return EmployeePayrollAdjustment::query()
            ->with('concept')
            ->whereIn('employee_id', $employees->pluck('id')->all())
            ->where('status', AdjustmentStatus::Approved)
            ->where(function (Builder $query) use ($period): void {
                $query->where('payroll_period_id', $period->id)
                    ->orWhere(function (Builder $inner) use ($period): void {
                        $inner->whereNull('payroll_period_id')
                            ->where(function (Builder $nested) use ($period): void {
                                $nested->whereNull('approved_at')
                                    ->orWhereDate('approved_at', '<=', $period->end_date->toDateString());
                            });
                    });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Elimina corridas del periodo que ya no corresponden a una sucursal visible
     * con colaboradores elegibles (evita importes obsoletos al recalcular).
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function removeStaleRuns(PayrollPeriod $period, User $actor, Collection $employees): void
    {
        $branchIds = $actor->allowedBranchIds();
        $targets = $employees->pluck('branch_id')->map(fn (int|string $id): int => (int) $id)->unique()->values()->all();

        $query = PayrollRun::query()->where('payroll_period_id', $period->id);

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }

        $query->whereNotIn('branch_id', $targets);

        foreach ($query->get() as $staleRun) {
            $staleRun->delete();
        }
    }

    /**
     * Calcula un colaborador y devuelve atributos del item, conceptos y totales.
     *
     * @param  array<int, AttendanceDayResult>  $dayResults
     * @param  Collection<int, EmployeePayrollAdjustment>  $adjustments
     * @return array{
     *     attributes: array<string, mixed>,
     *     concepts: array<int, array<string, mixed>>,
     *     adjustment_ids: array<int, int>,
     *     earnings: int,
     *     deductions: int,
     *     summary: array<string, mixed>
     * }
     */
    private function buildItem(
        Employee $employee,
        EmployeeCompensation $compensation,
        PayrollSchedule $schedule,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $dayResults,
        Collection $adjustments,
        ?string $note,
    ): array {
        $periodDays = (int) $start->diffInDays($end) + 1;
        $salaryCents = PayrollMoney::toCents((string) $compensation->base_salary);
        $nominalDays = $this->rules->nominalDaysFor($compensation->pay_frequency);
        $baseCents = PayrollMoney::roundDivide($salaryCents * $periodDays, $nominalDays);

        $workDays = $this->rules->workDaysFor($schedule->frequency);
        $hoursPerDay = $this->rules->hoursPerDay();

        $overtimeMinutes = 0;
        $lateMinutes = 0;
        $absenceDays = 0;
        $workedMinutes = 0;
        $evaluatedDays = 0;

        foreach ($dayResults as $day) {
            $overtimeMinutes += $day->overtimeMinutes;
            $lateMinutes += $day->lateMinutes;
            $workedMinutes += $day->workedMinutes;
            $evaluatedDays++;

            if ($day->status === AttendanceDayResult::STATUS_ABSENCE) {
                $absenceDays++;
            }
        }

        $overtimeCents = PayrollMoney::roundDivide(
            $overtimeMinutes * $baseCents * $this->rules->overtimeFactorBasisPoints(),
            60 * $workDays * $hoursPerDay * 10000,
        );

        $absenceCents = PayrollMoney::roundDivide(
            $absenceDays * $baseCents * $this->rules->absenceFactorBasisPoints(),
            $workDays * 10000,
        );

        $concepts = [
            [
                'code' => self::CONCEPT_BASE,
                'name' => 'Sueldo del periodo',
                'type' => PayrollConceptType::Earning,
                'quantity' => '1.00',
                'unit_amount' => PayrollMoney::toDecimal($baseCents),
                'amount' => PayrollMoney::toDecimal($baseCents),
                'rule_applied' => sprintf(
                    'Sueldo %s × %d/%d días naturales (frecuencia %s)',
                    PayrollMoney::toDecimal($salaryCents),
                    $periodDays,
                    $nominalDays,
                    $compensation->pay_frequency->value,
                ),
                'source_type' => EmployeeCompensation::class,
                'source_id' => $compensation->id,
            ],
        ];

        if ($overtimeMinutes > 0) {
            $concepts[] = [
                'code' => self::CONCEPT_OVERTIME,
                'name' => 'Horas extra (propuesta interna)',
                'type' => PayrollConceptType::Earning,
                'quantity' => PayrollMoney::toDecimal(PayrollMoney::roundDivide($overtimeMinutes * 100, 60)),
                'unit_amount' => PayrollMoney::toDecimal(PayrollMoney::roundDivide(
                    $baseCents * $this->rules->overtimeFactorBasisPoints(),
                    $workDays * $hoursPerDay * 10000,
                )),
                'amount' => PayrollMoney::toDecimal($overtimeCents),
                'rule_applied' => sprintf(
                    '%d min extra × tarifa horaria × factor %s (sin multiplicadores legales)',
                    $overtimeMinutes,
                    $this->factorLabel($this->rules->overtimeFactor()),
                ),
                'source_type' => self::SOURCE_ATTENDANCE,
                'source_id' => null,
            ];
        }

        if ($absenceDays > 0) {
            $concepts[] = [
                'code' => self::CONCEPT_ABSENCE,
                'name' => 'Descuento por falta injustificada',
                'type' => PayrollConceptType::Deduction,
                'quantity' => number_format($absenceDays, 2, '.', ''),
                'unit_amount' => PayrollMoney::toDecimal(PayrollMoney::roundDivide(
                    $baseCents * $this->rules->absenceFactorBasisPoints(),
                    $workDays * 10000,
                )),
                'amount' => PayrollMoney::toDecimal($absenceCents),
                'rule_applied' => sprintf(
                    '%d falta(s) × tarifa diaria × factor %s (sin reglas de ley)',
                    $absenceDays,
                    $this->factorLabel($this->rules->absenceFactor()),
                ),
                'source_type' => self::SOURCE_ATTENDANCE,
                'source_id' => null,
            ];
        }

        $bonusCents = 0;
        $penaltyCents = 0;
        $adjustmentIds = [];

        foreach ($adjustments as $adjustment) {
            $concept = $adjustment->concept;

            $unitCents = PayrollMoney::toCents((string) $adjustment->amount);
            $quantityHundredths = PayrollMoney::toCents((string) $adjustment->quantity);
            $amountCents = $concept->calculation === PayrollConceptCalculation::PerQuantity
                ? PayrollMoney::roundDivide($unitCents * $quantityHundredths, 100)
                : $unitCents;

            $isDeduction = $concept->type === PayrollConceptType::Deduction;

            if ($isDeduction) {
                $penaltyCents += $amountCents;
            } else {
                $bonusCents += $amountCents;
            }

            $concepts[] = [
                'code' => $concept->code,
                'name' => $concept->name,
                'type' => $concept->type,
                'quantity' => PayrollMoney::toDecimal($quantityHundredths),
                'unit_amount' => PayrollMoney::toDecimal($unitCents),
                'amount' => PayrollMoney::toDecimal($amountCents),
                'rule_applied' => sprintf(
                    'Ajuste aprobado (%s)%s',
                    $concept->calculation === PayrollConceptCalculation::PerQuantity ? 'monto × cantidad' : 'monto fijo',
                    $adjustment->is_sensitive ? '; concepto sensible revisado' : '',
                ),
                'source_type' => EmployeePayrollAdjustment::class,
                'source_id' => $adjustment->id,
            ];

            $adjustmentIds[] = $adjustment->id;
        }

        $earnings = $baseCents + $overtimeCents + $bonusCents;
        $deductions = $absenceCents + $penaltyCents;

        return [
            'attributes' => [
                'base_amount' => PayrollMoney::toDecimal($baseCents),
                'overtime_amount' => PayrollMoney::toDecimal($overtimeCents),
                'bonus_amount' => PayrollMoney::toDecimal($bonusCents),
                'adjustment_amount' => PayrollMoney::toDecimal($penaltyCents),
                'absence_deduction' => PayrollMoney::toDecimal($absenceCents),
                'late_minutes' => $lateMinutes,
                'absence_days' => number_format($absenceDays, 2, '.', ''),
                'overtime_minutes' => $overtimeMinutes,
                'net_amount' => PayrollMoney::toDecimal($earnings - $deductions),
                'snapshot' => [
                    'employee_number' => $employee->employee_number,
                    'compensation' => [
                        'id' => $compensation->id,
                        'base_salary' => (string) $compensation->base_salary,
                        'pay_frequency' => $compensation->pay_frequency->value,
                        'effective_from' => $compensation->effective_from->toDateString(),
                    ],
                    'period' => [
                        'start_date' => $start->toDateString(),
                        'end_date' => $end->toDateString(),
                        'natural_days' => $periodDays,
                        'schedule_frequency' => $schedule->frequency->value,
                    ],
                    'attendance' => [
                        'evaluated_days' => $evaluatedDays,
                        'worked_minutes' => $workedMinutes,
                        'overtime_minutes' => $overtimeMinutes,
                        'late_minutes' => $lateMinutes,
                        'absence_days' => $absenceDays,
                    ],
                    'rates' => [
                        'work_days' => $workDays,
                        'hours_per_day' => $hoursPerDay,
                        'daily' => PayrollMoney::toDecimal(PayrollMoney::roundDivide($baseCents, $workDays)),
                        'hourly' => PayrollMoney::toDecimal(PayrollMoney::roundDivide($baseCents, $workDays * $hoursPerDay)),
                    ],
                    'adjustment_ids' => $adjustmentIds,
                    'rules_version' => $this->rules->version(),
                    'note' => $note,
                ],
            ],
            'concepts' => $concepts,
            'adjustment_ids' => $adjustmentIds,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'summary' => [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'overtime_minutes' => $overtimeMinutes,
                'late_minutes' => $lateMinutes,
                'absence_days' => $absenceDays,
                'adjustment_ids' => $adjustmentIds,
            ],
        ];
    }

    /**
     * Instantánea de reglas, festivos e incidencias usadas en la corrida.
     *
     * @param  array<string, mixed>  $batchDetails
     * @return array<string, mixed>
     */
    private function rulesSnapshot(
        PayrollPeriod $period,
        PayrollSchedule $schedule,
        int $branchId,
        array $batchDetails,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $holidays = Holiday::query()
            ->forBranch($branchId)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderBy('date')
            ->get(['id', 'name', 'date', 'type'])
            ->map(fn (Holiday $holiday): array => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'date' => $holiday->date->toDateString(),
                'type' => $holiday->type->value,
            ])
            ->values()
            ->all();

        $incidents = AttendanceIncident::query()
            ->where('branch_id', $branchId)
            ->where('status', IncidentStatus::Approved)
            ->overlapping($start->toDateString(), $end->toDateString())
            ->orderBy('id')
            ->get(['id', 'employee_id', 'type', 'start_date', 'end_date'])
            ->map(fn (AttendanceIncident $incident): array => [
                'id' => $incident->id,
                'employee_id' => $incident->employee_id,
                'type' => $incident->type->value,
                'start_date' => $incident->start_date->toDateString(),
                'end_date' => $incident->end_date->toDateString(),
            ])
            ->values()
            ->all();

        return [
            ...$this->rules->snapshot(),
            'period' => [
                'id' => $period->id,
                'payroll_schedule_id' => $schedule->id,
                'schedule_frequency' => $schedule->frequency->value,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'natural_days' => (int) $start->diffInDays($end) + 1,
                'payment_date' => $period->payment_date->toDateString(),
            ],
            'branch_id' => $branchId,
            ...$batchDetails,
            'holidays' => $holidays,
            'incidents' => $incidents,
        ];
    }

    private function factorLabel(float $factor): string
    {
        return rtrim(rtrim(number_format($factor, 2, '.', ''), '0'), '.') ?: '0';
    }
}
