<?php

namespace App\Support\Schedules;

use App\Enums\PayrollRunStatus;
use App\Models\PayrollRun;

/**
 * Regla de vigencia de horarios (§7.1): un horario no puede entrar en vigor
 * antes del inicio del último periodo de pre-nómina cerrado de la sucursal.
 *
 * La regla se calcula en tiempo de ejecución porque la tabla de periodos es
 * operativa (no hay valor fijo que sembrar).
 */
final class ScheduleEffectiveGuard
{
    /**
     * Fecha mínima permitida (`Y-m-d`) o `null` si la sucursal no tiene
     * periodos cerrados.
     */
    public static function earliestEffectiveFrom(int $branchId): ?string
    {
        $start = PayrollRun::query()
            ->where('payroll_runs.branch_id', $branchId)
            ->where('payroll_runs.status', PayrollRunStatus::Closed->value)
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.payroll_period_id')
            ->max('payroll_periods.start_date');

        return $start === null ? null : (string) $start;
    }
}
