<?php

namespace App\Services\Vacations;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\VacationRequestStatus;
use App\Models\AttendanceIncident;
use App\Models\Employee;
use App\Models\User;
use App\Models\VacationPeriod;
use App\Models\VacationRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta y decisión de solicitudes de vacaciones.
 *
 * Reglas de negocio:
 * - Los días se calculan como días naturales inclusivos del rango solicitado
 *   (pendiente de confirmar con el cliente si deben excluir días de descanso;
 *   ver reporte de la ola).
 * - El rango completo debe caber en un mismo periodo laboral (aniversario).
 * - No se permiten solicitudes que se solapen con otra solicitud pendiente o
 *   aprobada, ni con incidencias de vacaciones ya aprobadas.
 * - Aprobar crea/actualiza la incidencia de asistencia tipo `vacation` en
 *   estado `approved`, para que el motor de asistencias excluya esos días.
 */
final class VacationRequestService
{
    public function __construct(
        private readonly VacationBalanceService $balances,
        private readonly VacationAuditRecorder $audit,
    ) {}

    /**
     * Registra una solicitud de vacaciones en estado pendiente.
     *
     * @param  array{start_date: string, end_date: string, notes?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function request(Employee $employee, array $data, User $actor): VacationRequest
    {
        $start = CarbonImmutable::parse($data['start_date'])->startOfDay();
        $end = CarbonImmutable::parse($data['end_date'])->startOfDay();

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'La fecha final no puede ser anterior a la fecha inicial.',
            ]);
        }

        $days = $this->daysBetween($start, $end);
        $period = $this->resolvePeriod($employee, $start, $end);

        if (! $period instanceof VacationPeriod) {
            throw ValidationException::withMessages([
                'start_date' => 'Las fechas no están cubiertas por un periodo de vacaciones. Genera los periodos por aniversario antes de capturar la solicitud.',
            ]);
        }

        if ($this->hasOverlappingRequest($employee, $start, $end)) {
            throw ValidationException::withMessages([
                'start_date' => 'Ya existe una solicitud de vacaciones pendiente o aprobada que se empalma con esas fechas.',
            ]);
        }

        if ($this->hasApprovedIncident($employee, $start, $end)) {
            throw ValidationException::withMessages([
                'start_date' => 'Esas fechas ya están registradas como vacaciones aprobadas en incidencias de asistencia.',
            ]);
        }

        $this->balances->assertCanConsume($period, $days);

        $request = DB::transaction(function () use ($employee, $actor, $period, $data, $start, $end, $days): VacationRequest {
            $request = VacationRequest::query()->create([
                'employee_id' => $employee->id,
                'vacation_period_id' => $period->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'status' => VacationRequestStatus::Pending->value,
                'requested_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->record('vacation.request.created', $request, $actor, null, $this->snapshot($request));

            return $request;
        });

        return $request->refresh();
    }

    /**
     * Aprueba la solicitud y crea la incidencia de asistencia correspondiente.
     *
     * @throws ValidationException
     */
    public function approve(VacationRequest $request, User $actor, ?string $notes = null): VacationRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes): VacationRequest {
            $this->assertPending($request);

            $request->loadMissing(['employee', 'period']);

            $start = $request->start_date->toImmutable()->startOfDay();
            $end = $request->end_date->toImmutable()->startOfDay();
            $period = $request->period ?? $this->resolvePeriod($request->employee, $start, $end);

            if (! $period instanceof VacationPeriod) {
                throw ValidationException::withMessages([
                    'decision' => 'La solicitud no tiene un periodo de vacaciones asociado. Genera los periodos por aniversario.',
                ]);
            }

            if ($this->hasOverlappingRequest($request->employee, $start, $end, $request->getKey())) {
                throw ValidationException::withMessages([
                    'decision' => 'No se puede aprobar: ya existe otra solicitud aprobada que se empalma con esas fechas.',
                ]);
            }

            if ($this->hasApprovedIncident($request->employee, $start, $end)) {
                throw ValidationException::withMessages([
                    'decision' => 'No se puede aprobar: esas fechas ya están registradas como vacaciones aprobadas en incidencias de asistencia.',
                ]);
            }

            $this->balances->assertCanConsume($period, (float) $request->days);

            $before = $this->snapshot($request);

            $request->update([
                'vacation_period_id' => $period->id,
                'status' => VacationRequestStatus::Approved->value,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'notes' => $notes ?? $request->notes,
            ]);

            $this->syncIncident($request->refresh(), $actor, $notes);

            $this->audit->record('vacation.request.approved', $request, $actor, $before, $this->snapshot($request));

            return $request;
        });
    }

    /**
     * Rechaza la solicitud y cancela la incidencia de asistencia si existía.
     *
     * @throws ValidationException
     */
    public function reject(VacationRequest $request, User $actor, ?string $notes = null): VacationRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes): VacationRequest {
            $this->assertPending($request);

            $before = $this->snapshot($request);

            $request->update([
                'status' => VacationRequestStatus::Rejected->value,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'notes' => $notes ?? $request->notes,
            ]);

            AttendanceIncident::query()
                ->where('employee_id', $request->employee_id)
                ->where('type', IncidentType::Vacation->value)
                ->where('status', IncidentStatus::Approved->value)
                ->whereDate('start_date', $request->start_date->toDateString())
                ->whereDate('end_date', $request->end_date->toDateString())
                ->update([
                    'status' => IncidentStatus::Cancelled->value,
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->audit->record('vacation.request.rejected', $request, $actor, $before, $this->snapshot($request));

            return $request;
        });
    }

    /**
     * Periodo laboral que cubre por completo el rango solicitado.
     */
    public function resolvePeriod(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): ?VacationPeriod
    {
        return VacationPeriod::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period_start', '<=', $start->toDateString())
            ->whereDate('period_end', '>=', $end->toDateString())
            ->orderByDesc('period_start')
            ->first();
    }

    /**
     * Días naturales inclusivos del rango.
     */
    public function daysBetween(CarbonImmutable $start, CarbonImmutable $end): float
    {
        return (float) ($start->diffInDays($end) + 1);
    }

    /**
     * @throws ValidationException
     */
    private function assertPending(VacationRequest $request): void
    {
        if ($request->status !== VacationRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'decision' => 'Solo se pueden decidir solicitudes en estado pendiente.',
            ]);
        }
    }

    private function hasOverlappingRequest(
        Employee $employee,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?int $ignoreRequestId = null,
    ): bool {
        return VacationRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [VacationRequestStatus::Pending->value, VacationRequestStatus::Approved->value])
            ->when($ignoreRequestId !== null, fn ($query) => $query->whereKeyNot($ignoreRequestId))
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();
    }

    private function hasApprovedIncident(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return AttendanceIncident::query()
            ->where('employee_id', $employee->id)
            ->where('type', IncidentType::Vacation->value)
            ->where('status', IncidentStatus::Approved->value)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();
    }

    private function syncIncident(VacationRequest $request, User $actor, ?string $notes): AttendanceIncident
    {
        $request->loadMissing('employee');

        $incident = AttendanceIncident::query()
            ->where('employee_id', $request->employee_id)
            ->where('type', IncidentType::Vacation->value)
            ->whereDate('start_date', $request->start_date->toDateString())
            ->whereDate('end_date', $request->end_date->toDateString())
            ->first() ?? new AttendanceIncident([
                'employee_id' => $request->employee_id,
                'type' => IncidentType::Vacation->value,
                'start_date' => $request->start_date->toDateString(),
                'end_date' => $request->end_date->toDateString(),
            ]);

        $incident->forceFill([
            'branch_id' => $request->employee->branch_id,
            'quantity' => $request->days,
            'status' => IncidentStatus::Approved,
            'created_by' => $incident->created_by ?? $request->requested_by,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'reason' => $incident->reason ?? 'Vacaciones aprobadas',
            'notes' => $notes ?? $incident->notes,
        ])->save();

        return $incident;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(VacationRequest $request): array
    {
        return [
            'id' => $request->id,
            'employee_id' => $request->employee_id,
            'vacation_period_id' => $request->vacation_period_id,
            'start_date' => $request->start_date->toDateString(),
            'end_date' => $request->end_date->toDateString(),
            'days' => (string) $request->days,
            'status' => $request->status->value,
            'decided_by' => $request->decided_by,
            'decided_at' => $request->decided_at?->toIso8601String(),
            'notes' => $request->notes,
        ];
    }
}
