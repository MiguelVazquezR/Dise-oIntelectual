<?php

namespace App\Services\Holidays;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\User;

/**
 * Bitácora de cambios del calendario de festivos (`audit_logs`).
 */
final class HolidayAuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        ?Holiday $holiday = null,
        ?User $actor = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $holiday instanceof Holiday ? Holiday::class : null,
            'auditable_id' => $holiday?->id,
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
