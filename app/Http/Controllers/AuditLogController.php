<?php

namespace App\Http\Controllers;

use App\Http\Requests\Aws\IndexAuditLogRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Attendance\AttendancePeriodSummary;
use App\Support\Pagination\PaginatedPayload;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bitácora de auditoría (`/audit`, §7.4).
 *
 * Solo se exponen metadatos: acción, recurso afectado, actor, IP y fecha. Los
 * payloads `before`/`after` no se envían a la vista para no filtrar PIN,
 * biometría ni secretos.
 */
class AuditLogController extends Controller
{
    private const ACTIONS_LIMIT = 100;

    private const USERS_LIMIT = 200;

    public function index(IndexAuditLogRequest $request): Response
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 25);
        $timezone = AttendancePeriodSummary::DEFAULT_TIMEZONE;

        $logs = AuditLog::query()
            ->select(['id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'ip_address', 'created_at'])
            ->with('user:id,name')
            ->when(
                isset($filters['action']) && $filters['action'] !== '',
                fn (Builder $query): Builder => $query->where('action', (string) $filters['action']),
            )
            ->when(
                isset($filters['user_id']),
                fn (Builder $query): Builder => $query->where('user_id', (int) $filters['user_id']),
            )
            ->when(
                isset($filters['from']),
                fn (Builder $query): Builder => $query->where(
                    'created_at',
                    '>=',
                    CarbonImmutable::parse((string) $filters['from'], $timezone)->startOfDay()->utc(),
                ),
            )
            ->when(
                isset($filters['to']),
                fn (Builder $query): Builder => $query->where(
                    'created_at',
                    '<=',
                    CarbonImmutable::parse((string) $filters['to'], $timezone)->endOfDay()->utc(),
                ),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'created_at' => $log->created_at?->utc()->toIso8601String(),
                'user' => $log->user instanceof User
                    ? ['id' => $log->user->id, 'name' => $log->user->name]
                    : null,
                'action' => $log->action,
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'ip_address' => $log->ip_address,
            ]);

        return Inertia::render('audit/Index', [
            'filters' => [
                'action' => $filters['action'] ?? null,
                'user_id' => isset($filters['user_id']) ? (int) $filters['user_id'] : null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'per_page' => $perPage,
            ],
            'logs' => PaginatedPayload::from($logs),
            'options' => [
                'actions' => $this->actionOptions(),
                'users' => $this->userOptions(),
            ],
            'can' => [
                'view' => true,
                'export' => false,
            ],
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function actionOptions(): array
    {
        return AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->limit(self::ACTIONS_LIMIT)
            ->pluck('action')
            ->map(fn (string $action): array => ['value' => $action, 'label' => $action])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function userOptions(): array
    {
        return User::query()
            ->whereIn('id', AuditLog::query()->select('user_id')->whereNotNull('user_id')->distinct())
            ->orderBy('name')
            ->limit(self::USERS_LIMIT)
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])
            ->values()
            ->all();
    }
}
