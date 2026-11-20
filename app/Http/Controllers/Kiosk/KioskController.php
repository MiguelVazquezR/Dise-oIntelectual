<?php

namespace App\Http\Controllers\Kiosk;

use App\Contracts\FaceRecognitionService;
use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\Branch;
use App\Models\User;
use App\Support\Branches\BranchOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pantalla del checador (§7.3).
 *
 * El operador ve solo las sucursales que tiene asignadas; el PIN se captura en
 * la pantalla compartida y nunca se comparte con el cliente en las props.
 */
class KioskController extends Controller
{
    public function __construct(private readonly FaceRecognitionService $faceRecognition) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AttendanceEvent::class);

        /** @var User $user */
        $user = $request->user();
        $branch = $this->resolveBranch($user, $request->integer('branch_id'));
        $face = $this->faceStatus();

        return Inertia::render('kiosk/Index', [
            'branch' => $branch instanceof Branch
                ? [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'timezone' => $branch->timezone,
                ]
                : null,
            'options' => [
                'branches' => BranchOptions::forUser($user),
            ],
            'face' => $face,
            'recent_events' => $branch instanceof Branch ? $this->recentEvents($branch) : [],
            'can' => [
                'face' => $face['configured'] && $face['enabled'],
            ],
        ]);
    }

    /**
     * @return array{configured: bool, enabled: bool, collection_masked: string|null, message: string}
     */
    private function faceStatus(): array
    {
        $enabled = (bool) config('rekognition.enabled');
        $configured = $this->faceRecognition->isConfigured();

        $message = match (true) {
            ! $enabled => 'Reconocimiento facial desactivado (modo demostración).',
            ! $configured => 'Reconocimiento facial pendiente de configuración.',
            default => 'Reconocimiento facial disponible.',
        };

        return [
            'configured' => $configured,
            'enabled' => $enabled,
            'collection_masked' => $this->maskCollectionId(config('rekognition.collection_id')),
            'message' => $message,
        ];
    }

    private function maskCollectionId(mixed $collectionId): ?string
    {
        if (! is_string($collectionId) || $collectionId === '') {
            return null;
        }

        return '****'.mb_substr($collectionId, -4);
    }

    /**
     * @return array<int, array{employee_name: string, type: string, occurred_at: string, method: string}>
     */
    private function recentEvents(Branch $branch): array
    {
        $start = CarbonImmutable::now($branch->timezone)->startOfDay();

        return AttendanceEvent::query()
            ->with('employee:id,first_name,last_name,second_last_name')
            ->where('branch_id', $branch->id)
            ->whereBetween('occurred_at', [$start->utc(), $start->addDay()->utc()])
            ->orderByDesc('occurred_at')
            ->limit(5)
            ->get()
            ->map(fn (AttendanceEvent $event): array => [
                'employee_name' => $event->employee->fullName(),
                'type' => $event->type->value,
                'occurred_at' => $event->occurred_at->toImmutable()->utc()->toIso8601String(),
                'method' => $event->method->value,
            ])
            ->values()
            ->all();
    }

    /**
     * Sucursal del operador: la solicitada si está permitida, la principal o la
     * primera visible.
     */
    private function resolveBranch(User $user, int $requestedBranchId): ?Branch
    {
        $allowed = $user->allowedBranchIds();

        if ($requestedBranchId > 0) {
            $branch = Branch::query()->where('is_active', true)->find($requestedBranchId);

            if ($branch instanceof Branch && ($allowed === null || in_array($branch->id, $allowed, true))) {
                return $branch;
            }
        }

        if ($allowed === []) {
            return null;
        }

        $primary = $user->primaryBranch();

        if ($primary instanceof Branch && ($allowed === null || in_array($primary->id, $allowed, true))) {
            return $primary;
        }

        return Branch::query()
            ->where('is_active', true)
            ->when($allowed !== null, fn (Builder $query) => $query->whereIn('id', $allowed))
            ->orderBy('name')
            ->first();
    }
}
