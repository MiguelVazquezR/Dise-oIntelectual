<?php

namespace App\Services\Employees;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;

/**
 * Bitácora de cambios administrativos del colaborador (`audit_logs`).
 *
 * Guarda solo identificadores y metadatos del cambio: nunca el PIN, su hash ni
 * datos personales sensibles.
 */
final class EmployeeAuditRecorder
{
    /** Acción registrada al asignar o cambiar el PIN del checador. */
    public const PIN_UPDATED = 'employee.pin.updated';

    public function pinUpdated(
        Employee $employee,
        ?User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => self::PIN_UPDATED,
            'auditable_type' => $employee->getMorphClass(),
            'auditable_id' => $employee->id,
            'before' => null,
            'after' => ['employee_id' => $employee->id],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }
}
