<?php

namespace App\Http\Controllers\Kiosk;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\StoreFaceClockRequest;
use App\Models\Employee;
use App\Services\Face\FaceRecognitionManager;
use App\Services\Kiosk\AttendanceClockRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Marcaje por reconocimiento facial (Fase 6).
 *
 * - Delega la decisión del proveedor en `FaceRecognitionManager`: sin
 *   credenciales responde con el mensaje controlado y **no** llama a AWS; en
 *   modo simulación (`driver=fake`) no reconoce identidades.
 * - Con AWS configurado consulta `SearchFacesByImage` (el consumo queda en
 *   `aws_usage_records`). Si el rostro corresponde a un colaborador activo y
 *   enrolado de la sucursal, registra el marcaje con `AttendanceClockRecorder`
 *   —el mismo que usa el PIN—: fecha laboral, método `face`, estado `flagged`
 *   por no haber verificación de vida y la regla de doble marcaje de 60 s.
 * - Nunca sustituye ni elimina un marcaje por PIN y nunca guarda la imagen.
 */
class FaceClockController extends Controller
{
    public function __construct(
        private readonly FaceRecognitionManager $manager,
        private readonly AttendanceClockRecorder $recorder,
    ) {}

    public function store(StoreFaceClockRequest $request): RedirectResponse
    {
        $driver = $this->manager->driver();

        if ($driver === FaceRecognitionManager::DRIVER_NOT_CONFIGURED) {
            return back()->with('error', (bool) config('rekognition.enabled')
                ? 'Reconocimiento facial pendiente de configuración'
                : 'Integración no configurada');
        }

        if ($driver === FaceRecognitionManager::DRIVER_FAKE) {
            return back()->with('error', 'Modo demostración: el reconocimiento facial simulado no registra asistencia. Usa tu PIN.');
        }

        $data = $request->validated();
        $image = $this->decodeImage($data['image'] ?? null);

        if ($image === null) {
            return back()->with('error', 'No se recibió una imagen válida para el reconocimiento facial.');
        }

        $result = $this->manager->service()->searchFaceByIdentity(
            (int) $data['branch_id'],
            $image,
            (string) Str::uuid(),
        );

        if (! $result->success) {
            return back()->with('error', $result->errorMessage ?? 'No fue posible reconocer el rostro. Usa tu PIN.');
        }

        if ($result->employeeId === null) {
            return back()->with('error', 'No se reconoció el rostro. Verifica tu enrolamiento o registra tu asistencia con el PIN.');
        }

        $employee = Employee::query()->with('branch')->find($result->employeeId);

        if (! $employee instanceof Employee
            || $employee->branch_id !== (int) $data['branch_id']) {
            return back()->with('error', 'No se reconoció el rostro. Verifica tu enrolamiento o registra tu asistencia con el PIN.');
        }

        if ($employee->status !== EmployeeStatus::Active) {
            return back()->with('error', 'El colaborador no está activo; no se registró el marcaje.');
        }

        $type = AttendanceEventType::from((string) $data['event_type']);
        $gps = is_array($data['gps'] ?? null) ? $data['gps'] : null;
        $simulated = $driver === FaceRecognitionManager::DRIVER_FAKE;
        $collection = $simulated ? 'demo' : config('rekognition.collection_id');

        $outcome = $this->recorder->record(
            employee: $employee,
            branch: $employee->branch,
            type: $type,
            method: AttendanceMethod::Face,
            gps: $gps,
            metadata: [
                'provider' => $simulated ? 'demo_rekognition' : 'aws_rekognition',
                'collection_id' => $this->maskCollectionId($collection),
                'similarity' => $result->confidence !== null ? round($result->confidence, 2) : null,
                'gps' => $gps,
            ],
            validationStatus: AttendanceStatus::Flagged,
            validationNotes: 'Marcaje por reconocimiento facial sin verificación de vida (liveness): requiere validación manual.',
            ipAddress: $request->ip(),
            deviceLabel: Str::limit((string) $request->userAgent(), 120, ''),
        );

        if ($outcome->duplicate) {
            return back()->with('error', 'Doble marcaje detectado: espera al menos un minuto antes de volver a marcar.');
        }

        $localTime = $outcome->event->occurred_at->setTimezone($employee->branch->timezone)->format('H:i');

        return back()->with(
            'success',
            AttendanceClockRecorder::successMessage($type, $localTime).' (reconocimiento facial sin verificación de vida; pendiente de validación).',
        );
    }

    /**
     * Decodifica la imagen en memoria (admite data URL) y la descarta al
     * terminar; nunca se guarda en disco ni en storage.
     */
    private function decodeImage(mixed $payload): ?string
    {
        if (! is_string($payload) || trim($payload) === '') {
            return null;
        }

        $base64 = $payload;

        if (str_contains($base64, 'base64,')) {
            $base64 = substr($base64, (int) strpos($base64, 'base64,') + 7);
        }

        $binary = base64_decode((string) preg_replace('/\s+/', '', $base64), true);

        return is_string($binary) && $binary !== '' ? $binary : null;
    }

    private function maskCollectionId(mixed $collectionId): ?string
    {
        if (! is_string($collectionId) || trim($collectionId) === '') {
            return null;
        }

        return '****'.mb_substr(trim($collectionId), -4);
    }
}
