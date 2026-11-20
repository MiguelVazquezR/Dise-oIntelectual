<?php

namespace App\Services\Payroll;

use App\Models\AuditLog;
use App\Models\EmployeePayrollAdjustment;
use App\Models\PayrollConcept;
use App\Models\PayrollPeriod;
use App\Models\User;

/**
 * Bitácora de la pre-nómina (`audit_logs`).
 *
 * Registra cálculo, revisión, cierre, alta y decisión de ajustes y cambios al
 * catálogo de conceptos. Solo guarda identificadores y valores de negocio:
 * nunca motivos libres con datos personales, PIN, biometría ni secretos.
 */
final class PayrollAuditRecorder
{
    /**
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>|null  $before
     */
    public function calculated(
        PayrollPeriod $period,
        ?User $actor,
        array $after,
        ?array $before = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->record('payroll.period.calculated', $period, $actor, $before, $after, $ipAddress, $userAgent);
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    public function reviewed(
        PayrollPeriod $period,
        ?User $actor,
        ?array $before,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->record('payroll.period.reviewed', $period, $actor, $before, $this->snapshot($period), $ipAddress, $userAgent);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    public function closed(
        PayrollPeriod $period,
        ?User $actor,
        ?array $before,
        array $after,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->record('payroll.period.closed', $period, $actor, $before, $after, $ipAddress, $userAgent);
    }

    public function adjustmentCreated(
        EmployeePayrollAdjustment $adjustment,
        ?User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->record('payroll.adjustment.created', $adjustment, $actor, null, $this->adjustmentSnapshot($adjustment), $ipAddress, $userAgent);
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    public function adjustmentDecided(
        EmployeePayrollAdjustment $adjustment,
        ?User $actor,
        ?array $before,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->record('payroll.adjustment.decided', $adjustment, $actor, $before, $this->adjustmentSnapshot($adjustment), $ipAddress, $userAgent);
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    public function conceptChanged(
        string $action,
        PayrollConcept $concept,
        ?User $actor,
        ?array $before = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->record($action, $concept, $actor, $before, $this->conceptSnapshot($concept), $ipAddress, $userAgent);
    }

    /**
     * Estado del periodo (sin importes, que ya viven en las corridas).
     *
     * @return array<string, mixed>
     */
    public function snapshot(PayrollPeriod $period): array
    {
        return [
            'payroll_period_id' => $period->id,
            'payroll_schedule_id' => $period->payroll_schedule_id,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'payment_date' => $period->payment_date->toDateString(),
            'status' => $period->status->value,
            'closed_at' => $period->closed_at?->toIso8601String(),
            'closed_by' => $period->closed_by,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adjustmentSnapshot(EmployeePayrollAdjustment $adjustment): array
    {
        return [
            'employee_id' => $adjustment->employee_id,
            'payroll_period_id' => $adjustment->payroll_period_id,
            'payroll_concept_id' => $adjustment->payroll_concept_id,
            'amount' => (string) $adjustment->amount,
            'quantity' => (string) $adjustment->quantity,
            'status' => $adjustment->status->value,
            'is_sensitive' => $adjustment->is_sensitive,
            'approved_by' => $adjustment->approved_by,
            'approved_at' => $adjustment->approved_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function conceptSnapshot(PayrollConcept $concept): array
    {
        return [
            'payroll_concept_id' => $concept->id,
            'code' => $concept->code,
            'type' => $concept->type->value,
            'calculation' => $concept->calculation->value,
            'default_amount' => $concept->default_amount !== null ? (string) $concept->default_amount : null,
            'is_sensitive' => $concept->is_sensitive,
            'is_active' => $concept->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function record(
        string $action,
        PayrollPeriod|EmployeePayrollAdjustment|PayrollConcept $auditable,
        ?User $actor,
        ?array $before,
        ?array $after,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }
}
