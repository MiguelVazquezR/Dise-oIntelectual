<?php

namespace App\Http\Controllers;

use App\Http\Requests\Aws\StoreBiometricEnrollmentRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\Face\BiometricEnrollmentService;
use App\Support\BranchAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Consentimiento y enrolamiento biométrico por colaborador.
 *
 * Las rutas exigen `employees.manage` + `aws.settings.manage` (consulta:
 * `employees.view`) y el actor solo opera sobre colaboradores de sus
 * sucursales. La imagen recibida se procesa en memoria y se descarta; la
 * respuesta y la auditoría no contienen datos biométricos.
 */
class EmployeeFaceController extends Controller
{
    /** Límite de AWS Rekognition para `IndexFaces` (5 MB). */
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    public function __construct(private readonly BiometricEnrollmentService $enrollments) {}

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureBranchAccess($request->user(), $employee);

        return response()->json(['data' => $this->enrollments->status($employee)]);
    }

    public function store(StoreBiometricEnrollmentRequest $request, Employee $employee): RedirectResponse
    {
        $this->ensureBranchAccess($request->user(), $employee);

        $image = $this->decodeImage($request->validated('image'));

        if ($image === null || strlen($image) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'image' => 'La imagen no es válida o excede 5 MB.',
            ]);
        }

        $outcome = $this->enrollments->enroll(
            $employee,
            $image,
            (string) $request->validated('consent_version'),
            is_string($request->validated('idempotency_key')) ? $request->validated('idempotency_key') : null,
            $request->user(),
        );

        return back()->with($outcome->success ? 'success' : 'error', $outcome->message);
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->ensureBranchAccess($request->user(), $employee);

        $outcome = $this->enrollments->revoke($employee, $request->user());

        return back()->with($outcome->success ? 'success' : 'error', $outcome->message);
    }

    private function ensureBranchAccess(?User $user, Employee $employee): void
    {
        abort_unless($user instanceof User && BranchAccess::allows($user, $employee->branch_id), 403);
    }

    /**
     * Decodifica la imagen en memoria (admite data URL) y la descarta; nunca
     * se guarda en disco ni en storage.
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
}
