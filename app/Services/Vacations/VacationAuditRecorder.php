<?php

namespace App\Services\Vacations;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\VacationPeriod;
use App\Models\VacationRequest;

/**
 * Bitácora de cambios de vacaciones (`audit_logs`).
 */
final class VacationAuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        VacationRequest|VacationPeriod|null $subject = null,
        ?User $actor = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
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
