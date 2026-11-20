<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Http\Requests\Incidents\DecideIncidentRequest;
use App\Http\Requests\Incidents\IndexIncidentRequest;
use App\Http\Requests\Incidents\StoreIncidentRequest;
use App\Http\Requests\Incidents\UpdateIncidentRequest;
use App\Models\AttendanceIncident;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendancePeriodSummary;
use App\Services\Attendance\IncidentAuditor;
use App\Support\BranchAccess;
use App\Support\Branches\BranchOptions;
use App\Support\Pagination\PaginatedPayload;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Incidencias de asistencia (§7.3).
 *
 * - `branch_id` siempre se deriva del colaborador.
 * - Se rechazan duplicados exactos (empleado + tipo + rango) además de la
 *   restricción única de la tabla.
 * - Solo se editan incidencias `pending`.
 * - Cada alta, edición y decisión queda en `audit_logs` sin datos sensibles.
 * - La evidencia se guarda en `storage/app/private/incidents` (disco `local`).
 */
class IncidentController extends Controller
{
    private const EVIDENCE_DISK = 'local';

    private const EVIDENCE_DIRECTORY = 'incidents';

    public function __construct(private readonly IncidentAuditor $auditor) {}

    public function index(IndexIncidentRequest $request): Response
    {
        $this->authorize('viewAny', AttendanceIncident::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);
        $today = CarbonImmutable::now(AttendancePeriodSummary::DEFAULT_TIMEZONE)->startOfDay();
        $from = isset($filters['from'])
            ? CarbonImmutable::parse($filters['from'])->startOfDay()
            : $today->subDays(29);
        $to = isset($filters['to'])
            ? CarbonImmutable::parse($filters['to'])->startOfDay()
            : $today;

        if ($to->lessThan($from)) {
            $to = $from;
        }

        $incidents = BranchAccess::scope(AttendanceIncident::query()->with([
            'employee:id,first_name,last_name,second_last_name,employee_number',
            'branch:id,name',
            'creator:id,name',
            'decider:id,name',
        ]))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branchId) => $query->where('branch_id', (int) $branchId))
            ->when($filters['employee_id'] ?? null, fn (Builder $query, int|string $employeeId) => $query->where('employee_id', (int) $employeeId))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AttendanceIncident $incident): array => $this->row($incident));

        return Inertia::render('incidents/Index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
                'employee_id' => isset($filters['employee_id']) ? (int) $filters['employee_id'] : null,
                'type' => $filters['type'] ?? null,
                'status' => $filters['status'] ?? null,
                'per_page' => $perPage,
            ],
            'incidents' => PaginatedPayload::from($incidents),
            'options' => [
                'branches' => BranchOptions::forUser($user),
                'employees' => $this->employeeOptions($user),
                'types' => $this->enumOptions(IncidentType::cases()),
                'statuses' => $this->enumOptions(IncidentStatus::cases()),
            ],
            'can' => [
                'manage' => $user->hasPermission('incidents.manage'),
                'approve' => $user->hasPermission('incidents.approve'),
            ],
        ]);
    }

    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $this->authorize('create', AttendanceIncident::class);

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $employee = Employee::query()->findOrFail((int) $data['employee_id']);

        abort_unless(BranchAccess::allows($user, $employee->branch_id), 403);

        $this->ensureNoDuplicate(
            $employee->id,
            (string) $data['type'],
            (string) $data['start_date'],
            (string) $data['end_date'],
        );

        $incident = DB::transaction(function () use ($request, $data, $employee, $user): AttendanceIncident {
            $evidencePath = $request->file('evidence')?->store(self::EVIDENCE_DIRECTORY, self::EVIDENCE_DISK);

            $incident = AttendanceIncident::query()->create([
                'employee_id' => $employee->id,
                'branch_id' => $employee->branch_id,
                'type' => $data['type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'quantity' => (string) $data['quantity'],
                'reason' => $data['reason'] ?? null,
                'evidence_path' => is_string($evidencePath) ? $evidencePath : null,
                'status' => IncidentStatus::Pending,
                'created_by' => $user->id,
            ]);

            $this->auditor->created($incident, $user, $request->ip(), $request->userAgent());

            return $incident;
        });

        return to_route('incidents.index')
            ->with('success', 'Incidencia registrada correctamente para '.$incident->employee->fullName().'.');
    }

    public function update(UpdateIncidentRequest $request, AttendanceIncident $incident): RedirectResponse
    {
        $this->authorize('update', $incident);

        if ($incident->status !== IncidentStatus::Pending) {
            return back()->with('error', 'Solo se pueden editar incidencias pendientes.');
        }

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $this->ensureNoDuplicate(
            $incident->employee_id,
            (string) $data['type'],
            (string) $data['start_date'],
            (string) $data['end_date'],
            $incident->id,
        );

        $before = $this->auditor->snapshot($incident);

        DB::transaction(function () use ($request, $incident, $data, $user, $before): void {
            $attributes = [
                'type' => $data['type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'quantity' => (string) $data['quantity'],
                'reason' => $data['reason'] ?? null,
            ];

            $evidence = $request->file('evidence');

            if ($evidence !== null) {
                $path = $evidence->store(self::EVIDENCE_DIRECTORY, self::EVIDENCE_DISK);

                if ($incident->evidence_path !== null) {
                    Storage::disk(self::EVIDENCE_DISK)->delete($incident->evidence_path);
                }

                $attributes['evidence_path'] = $path;
            }

            $incident->update($attributes);
            $this->auditor->updated($incident->refresh(), $before, $user, $request->ip(), $request->userAgent());
        });

        return to_route('incidents.index')->with('success', 'Incidencia actualizada correctamente.');
    }

    public function decide(DecideIncidentRequest $request, AttendanceIncident $incident): RedirectResponse
    {
        $this->authorize('decide', $incident);

        if ($incident->status !== IncidentStatus::Pending) {
            return back()->with('error', 'La incidencia ya fue decidida.');
        }

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $decision = IncidentStatus::from((string) $data['decision']);
        $before = $this->auditor->snapshot($incident);

        DB::transaction(function () use ($request, $incident, $decision, $data, $user, $before): void {
            $incident->update([
                'status' => $decision,
                'decided_by' => $user->id,
                'decided_at' => now(),
                'notes' => $data['notes'] ?? $incident->notes,
            ]);

            $this->auditor->decided($incident->refresh(), $before, $user, $request->ip(), $request->userAgent());
        });

        $label = $decision === IncidentStatus::Approved ? 'aprobada' : 'rechazada';

        return back()->with('success', "Incidencia {$label} correctamente.");
    }

    private function ensureNoDuplicate(
        int $employeeId,
        string $type,
        string $startDate,
        string $endDate,
        ?int $ignoreId = null,
    ): void {
        $exists = AttendanceIncident::query()
            ->where('employee_id', $employeeId)
            ->where('type', $type)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'start_date' => 'Ya existe una incidencia con el mismo colaborador, tipo y rango de fechas.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AttendanceIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'employee' => [
                'id' => $incident->employee_id,
                'full_name' => $incident->employee->fullName(),
            ],
            'branch' => [
                'id' => $incident->branch_id,
                'name' => $incident->branch->name,
            ],
            'type' => $incident->type->value,
            'start_date' => $incident->start_date->toDateString(),
            'end_date' => $incident->end_date->toDateString(),
            'quantity' => (string) $incident->quantity,
            'status' => $incident->status->value,
            'reason' => $incident->reason,
            'created_by' => $incident->creator?->name,
            'decided_by' => $incident->decider?->name,
            'decided_at' => $incident->decided_at?->toIso8601String(),
            'has_evidence' => $incident->evidence_path !== null,
        ];
    }

    /**
     * @return array<int, array{id: int, full_name: string, employee_number: string}>
     */
    private function employeeOptions(User $user): array
    {
        return BranchAccess::scope(Employee::query()->with('branch:id,name'))
            ->orderBy('first_name')
            ->limit(200)
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'full_name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, IncidentType|IncidentStatus>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(
            fn (IncidentType|IncidentStatus $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            $cases,
        );
    }
}
