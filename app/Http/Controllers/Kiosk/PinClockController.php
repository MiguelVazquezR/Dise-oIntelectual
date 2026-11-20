<?php

namespace App\Http\Controllers\Kiosk;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceMethod;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\PinClockRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\Kiosk\AttendanceClockRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Registro de marcajes por PIN desde el checador.
 *
 * - Valida `employee_number` + `pin` con `Hash::check` (nunca persiste ni
 *   devuelve el PIN y no lo escribe en logs).
 * - El alta del evento y la regla de doble marcaje (60 s) viven en
 *   `AttendanceClockRecorder`, compartido con el marcaje facial.
 * - Tras 5 PIN incorrectos bloquea 15 minutos con un mensaje genérico.
 */
class PinClockController extends Controller
{
    private const MAX_FAILURES = 5;

    private const FAILURE_DECAY_SECONDS = 900;

    public function store(PinClockRequest $request, AttendanceClockRecorder $recorder): RedirectResponse
    {
        $data = $request->validated();
        $employeeNumber = mb_strtoupper(trim((string) $data['employee_number']));
        $branch = Branch::query()->findOrFail((int) $data['branch_id']);
        $failureKey = 'kiosk-pin-failure:'.$employeeNumber;

        if (RateLimiter::tooManyAttempts($failureKey, self::MAX_FAILURES)) {
            return back()->with('error', 'Demasiados intentos. Espera 15 minutos antes de volver a capturar el PIN.');
        }

        $employee = Employee::query()->where('employee_number', $employeeNumber)->first();

        if (! $employee instanceof Employee
            || $employee->pin_hash === null
            || ! Hash::check((string) $data['pin'], $employee->pin_hash)) {
            RateLimiter::hit($failureKey, self::FAILURE_DECAY_SECONDS);

            return back()->with('error', 'PIN o número de empleado incorrecto.');
        }

        RateLimiter::clear($failureKey);

        if ($employee->status !== EmployeeStatus::Active) {
            return back()->with('error', 'El colaborador no está activo; no se registró el marcaje.');
        }

        if ($employee->branch_id !== $branch->id) {
            return back()->with('error', 'El colaborador no pertenece a la sucursal seleccionada.');
        }

        $type = AttendanceEventType::from((string) $data['event_type']);
        $occurredAt = CarbonImmutable::now();
        $gps = is_array($data['gps'] ?? null) ? $data['gps'] : null;

        $outcome = $recorder->record(
            employee: $employee,
            branch: $branch,
            type: $type,
            method: AttendanceMethod::Pin,
            gps: $gps,
            occurredAt: $occurredAt,
            ipAddress: $request->ip(),
            deviceLabel: Str::limit((string) $request->userAgent(), 120, ''),
        );

        if ($outcome->duplicate) {
            return back()->with('error', 'Doble marcaje detectado: espera al menos un minuto antes de volver a marcar.');
        }

        $localTime = $outcome->event->occurred_at->setTimezone($branch->timezone)->format('H:i');

        return back()->with('success', AttendanceClockRecorder::successMessage($type, $localTime));
    }
}
