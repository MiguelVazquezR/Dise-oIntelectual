<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vacations\DecideVacationRequestRequest;
use App\Http\Requests\Vacations\StoreVacationRequestRequest;
use App\Models\Employee;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Vacations\VacationRequestService;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;

/**
 * Alta y decisión de solicitudes de vacaciones.
 *
 * Aprobar convierte la solicitud en una incidencia de asistencia `vacation`
 * aprobada para que el motor de asistencias excluya esos días (§7.3).
 */
class VacationRequestController extends Controller
{
    public function __construct(private readonly VacationRequestService $vacationRequests) {}

    /**
     * Registra una solicitud de vacaciones para un colaborador.
     */
    public function store(StoreVacationRequestRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        /** @var Employee $employee */
        $employee = Employee::query()->findOrFail($data['employee_id']);

        abort_unless(
            BranchAccess::allows($user, (int) $employee->branch_id),
            403,
        );

        $vacationRequest = $this->vacationRequests->request($employee, [
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'notes' => $data['notes'] ?? null,
        ], $user);

        return to_route('vacations.index', ['year' => (int) $vacationRequest->start_date->year])
            ->with('success', "Solicitud de vacaciones registrada para {$employee->fullName()}.");
    }

    /**
     * Aprueba o rechaza una solicitud pendiente.
     */
    public function decide(
        DecideVacationRequestRequest $request,
        VacationRequest $vacationRequest,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $decision = (string) $request->validated('decision');
        $notes = $request->validated('notes');

        if ($decision === 'approved') {
            $this->vacationRequests->approve($vacationRequest, $user, $notes);
            $message = 'Solicitud de vacaciones aprobada; los días se registraron como incidencia de asistencia.';
        } else {
            $this->vacationRequests->reject($vacationRequest, $user, $notes);
            $message = 'Solicitud de vacaciones rechazada.';
        }

        return to_route('vacations.index', ['year' => (int) $vacationRequest->start_date->year])
            ->with('success', $message);
    }
}
