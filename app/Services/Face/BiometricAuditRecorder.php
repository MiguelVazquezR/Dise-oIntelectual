<?php

namespace App\Services\Face;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;

/**
 * Bitácora del consentimiento y del enrolamiento biométrico (`audit_logs`).
 *
 * Solo se guardan metadatos (estado, proveedor, versión del consentimiento y
 * si fue simulado). Nunca se escriben imágenes, identificadores de rostro,
 * PIN ni secretos.
 */
final class BiometricAuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        Employee $employee,
        ?User $actor = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => Employee::class,
            'auditable_id' => $employee->id,
            'before' => $before,
            'after' => $after,
            'ip_address' => $this->ipAddress(),
            'user_agent' => $this->userAgent(),
        ]);
    }

    private function ipAddress(): ?string
    {
        return app()->bound('request') ? request()->ip() : null;
    }

    private function userAgent(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $agent = request()->userAgent();

        return is_string($agent) ? mb_substr($agent, 0, 255) : null;
    }
}
