<?php

namespace App\Services\Payroll;

use App\Enums\PayFrequency;
use App\Enums\PayrollScheduleFrequency;

/**
 * Lectura tipada y acotada de `config/payroll.php`.
 *
 * Concentra los parámetros internos de la pre-nómina (factores, días
 * nominales, avisos) para que la calculadora no dependa de claves de
 * configuración dispersas. No contiene reglas fiscales: la aplicación no
 * calcula ISR, IMSS ni INFONAVIT (D-14).
 */
final class PayrollRules
{
    public function currency(): string
    {
        return (string) config('payroll.currency', 'MXN');
    }

    public function version(): string
    {
        return (string) config('payroll.rules_version', 'payroll-rules-1');
    }

    /**
     * Factor de horas extra en puntos base (1.0 => 10000).
     */
    public function overtimeFactorBasisPoints(): int
    {
        return $this->basisPoints('payroll.overtime_factor');
    }

    /**
     * Factor de descuento por falta injustificada en puntos base (1.0 => 10000).
     */
    public function absenceFactorBasisPoints(): int
    {
        return $this->basisPoints('payroll.absence_factor');
    }

    public function overtimeFactor(): float
    {
        return (float) config('payroll.overtime_factor', 1.0);
    }

    public function absenceFactor(): float
    {
        return (float) config('payroll.absence_factor', 1.0);
    }

    public function lateRounding(): string
    {
        return (string) config('payroll.late_rounding', 'none');
    }

    /**
     * Días laborables nominales de un periodo según su frecuencia de pago.
     */
    public function workDaysFor(PayrollScheduleFrequency|PayFrequency|string $frequency): int
    {
        $value = $frequency instanceof PayrollScheduleFrequency || $frequency instanceof PayFrequency
            ? $frequency->value
            : $frequency;

        /** @var array<string, int> $workDays */
        $workDays = (array) config('payroll.default_work_days', []);

        return max(1, (int) ($workDays[$value] ?? $workDays['biweekly'] ?? 12));
    }

    /**
     * Días naturales nominales de la frecuencia de pago del colaborador.
     */
    public function nominalDaysFor(PayFrequency|string $frequency): int
    {
        $value = $frequency instanceof PayFrequency ? $frequency->value : $frequency;

        /** @var array<string, int> $days */
        $days = (array) config('payroll.frequency_days', []);

        return max(1, (int) ($days[$value] ?? $days['monthly'] ?? 30));
    }

    public function hoursPerDay(): int
    {
        return max(1, (int) config('payroll.hours_per_day', 8));
    }

    public function notice(): string
    {
        return (string) config('payroll.notice', 'Pre-nómina preliminar de control interno; no constituye un cálculo fiscal definitivo.');
    }

    public function receiptNotice(): string
    {
        return (string) config('payroll.receipt_notice', 'Documento preliminar; no constituye CFDI ni cálculo fiscal definitivo.');
    }

    /**
     * Parámetros vigentes, tal como se guardan en `payroll_runs.rules_snapshot`.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'rules_version' => $this->version(),
            'currency' => $this->currency(),
            'factors' => [
                'overtime' => $this->overtimeFactor(),
                'absence' => $this->absenceFactor(),
            ],
            'late_rounding' => $this->lateRounding(),
            'default_work_days' => (array) config('payroll.default_work_days', []),
            'frequency_days' => (array) config('payroll.frequency_days', []),
            'hours_per_day' => $this->hoursPerDay(),
            'notice' => $this->notice(),
        ];
    }

    private function basisPoints(string $key): int
    {
        return (int) round(((float) config($key, 1.0)) * 10000);
    }
}
