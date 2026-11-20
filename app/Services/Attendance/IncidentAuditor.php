<?php

namespace App\Services\Attendance;

use App\Models\AttendanceIncident;
use App\Models\AuditLog;
use App\Models\User;

/**
 * Registra en `audit_logs` los cambios de incidencias.
 *
 * Guarda solo identificadores y valores de negocio: nunca evidencia, notas
 * libres ni datos personales sensibles.
 */
final class IncidentAuditor
{
    public function created(AttendanceIncident $incident, ?User $actor, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->record('incident.created', $incident, $actor, null, $this->snapshot($incident), $ipAddress, $userAgent);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function updated(AttendanceIncident $incident, array $before, ?User $actor, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $after = $this->snapshot($incident);

        if ($before === $after) {
            return;
        }

        $this->record('incident.updated', $incident, $actor, $before, $after, $ipAddress, $userAgent);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function decided(AttendanceIncident $incident, array $before, ?User $actor, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->record('incident.decided', $incident, $actor, $before, $this->snapshot($incident), $ipAddress, $userAgent);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(AttendanceIncident $incident): array
    {
        return [
            'employee_id' => $incident->employee_id,
            'branch_id' => $incident->branch_id,
            'type' => $incident->type->value,
            'start_date' => $incident->start_date->toDateString(),
            'end_date' => $incident->end_date->toDateString(),
            'quantity' => (string) $incident->quantity,
            'status' => $incident->status->value,
            'decided_by' => $incident->decided_by,
            'decided_at' => $incident->decided_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function record(
        string $action,
        AttendanceIncident $incident,
        ?User $actor,
        ?array $before,
        ?array $after,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $incident->getMorphClass(),
            'auditable_id' => $incident->id,
            'before' => $before,
            'after' => $after,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }
}
