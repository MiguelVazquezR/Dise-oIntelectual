<?php

namespace App\Services\Face;

use App\Enums\BiometricStatus;
use App\Models\BiometricEnrollment;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Consentimiento explícito y ciclo de vida del enrolamiento biométrico.
 *
 * - Solo se enrola con consentimiento registrado (`consent_given_at` y
 *   `consent_version`); la imagen se procesa en memoria y se descarta.
 * - El proveedor activo lo decide `FaceRecognitionManager`: el modo simulado
 *   se etiqueta como tal en el registro y en la auditoría.
 * - La baja elimina el rostro del proveedor y revoca el consentimiento
 *   (`status=revoked`, `revoked_at`); si el proveedor no confirma, la
 *   revocación local se mantiene y se informa del fallo.
 * - Cada operación queda en `audit_logs` sin biometría ni secretos.
 */
final class BiometricEnrollmentService
{
    public const PROVIDER_AWS = 'aws_rekognition';

    public const PROVIDER_DEMO = 'demo_rekognition';

    private const DEMO_COLLECTION = 'demo';

    public function __construct(
        private readonly FaceRecognitionManager $manager,
        private readonly BiometricAuditRecorder $audit,
    ) {}

    /**
     * Estado público del enrolamiento de un colaborador (sin datos biométricos).
     *
     * @return array<string, mixed>
     */
    public function status(Employee $employee): array
    {
        $enrollment = $this->enrollmentFor($employee);
        $driver = $this->manager->driver();

        return [
            'employee_id' => $employee->id,
            'status' => $enrollment?->status->value ?? 'not_enrolled',
            'provider' => $enrollment?->provider,
            'collection_id_masked' => $this->maskCollectionId($enrollment->collection_id ?? config('rekognition.collection_id')),
            'consent_given_at' => $enrollment?->consent_given_at?->toIso8601String(),
            'consent_version' => $enrollment?->consent_version,
            'enrolled_at' => $enrollment?->enrolled_at?->toIso8601String(),
            'revoked_at' => $enrollment?->revoked_at?->toIso8601String(),
            'last_error' => $enrollment?->last_error,
            'configured' => $driver === FaceRecognitionManager::DRIVER_AWS,
            'simulated' => $driver === FaceRecognitionManager::DRIVER_FAKE,
            'driver' => $driver,
        ];
    }

    /**
     * Registra el consentimiento y enrola el rostro del colaborador.
     *
     * @param  string  $imageContent  Bytes de la imagen; no se persiste.
     * @param  string  $consentVersion  Versión del consentimiento aceptado.
     */
    public function enroll(
        Employee $employee,
        string $imageContent,
        string $consentVersion,
        ?string $idempotencyKey = null,
        ?User $actor = null,
    ): BiometricEnrollmentOutcome {
        $driver = $this->manager->driver();

        if ($driver === FaceRecognitionManager::DRIVER_NOT_CONFIGURED) {
            return new BiometricEnrollmentOutcome(
                false,
                'La integración con AWS Rekognition no está configurada; no se registró el consentimiento biométrico.',
            );
        }

        $service = $this->manager->service();
        $key = $idempotencyKey !== null && trim($idempotencyKey) !== ''
            ? trim($idempotencyKey)
            : (string) Str::uuid();

        $result = $service->indexFace($employee->id, $imageContent, $employee->branch_id, $key);
        $provider = $this->provider($driver);
        $collection = $driver === FaceRecognitionManager::DRIVER_FAKE
            ? self::DEMO_COLLECTION
            : trim((string) config('rekognition.collection_id'));
        $now = CarbonImmutable::now();

        if (! $result->success) {
            $this->persist($employee, $actor, 'biometric.enrollment_failed', [
                'provider' => $provider,
                'collection_id' => $collection,
                'status' => BiometricStatus::Failed,
                'consent_given_at' => $now,
                'consent_version' => $consentVersion,
                'last_error' => $result->errorMessage,
            ]);

            return new BiometricEnrollmentOutcome(
                false,
                $result->errorMessage ?? 'No fue posible registrar el rostro; no se guardó la imagen.',
            );
        }

        $this->persist($employee, $actor, 'biometric.enrolled', [
            'provider' => $provider,
            'collection_id' => $collection,
            'external_face_id' => $result->faceId,
            'status' => BiometricStatus::Enrolled,
            'consent_given_at' => $now,
            'consent_version' => $consentVersion,
            'enrolled_at' => $now,
            'revoked_at' => null,
            'last_error' => null,
        ]);

        return new BiometricEnrollmentOutcome(
            true,
            $service->isSimulated()
                ? 'Enrolamiento simulado registrado (modo demostración, source=demo).'
                : 'Consentimiento registrado y rostro enrolado correctamente.',
        );
    }

    /**
     * Revoca el consentimiento y elimina el rostro del proveedor.
     */
    public function revoke(Employee $employee, ?User $actor = null): BiometricEnrollmentOutcome
    {
        $enrollment = $this->enrollmentFor($employee);

        if (! $enrollment instanceof BiometricEnrollment) {
            return new BiometricEnrollmentOutcome(false, 'El colaborador no tiene un enrolamiento biométrico registrado.');
        }

        if ($enrollment->status === BiometricStatus::Revoked) {
            return new BiometricEnrollmentOutcome(true, 'El enrolamiento ya estaba revocado.');
        }

        $driver = $this->manager->driver();
        $remoteError = null;

        if ($driver !== FaceRecognitionManager::DRIVER_NOT_CONFIGURED
            && is_string($enrollment->external_face_id)
            && $enrollment->external_face_id !== '') {
            $result = $this->manager->service()->deleteFace(
                $enrollment->external_face_id,
                $employee->id,
                $employee->branch_id,
            );

            if (! $result->success) {
                $remoteError = $result->errorMessage ?? 'El proveedor no confirmó la baja del rostro.';
            }
        }

        $this->persist($employee, $actor, 'biometric.revoked', [
            'status' => BiometricStatus::Revoked,
            'revoked_at' => CarbonImmutable::now(),
            'external_face_id' => null,
            'last_error' => $remoteError,
        ]);

        return $remoteError === null
            ? new BiometricEnrollmentOutcome(true, 'Consentimiento revocado y rostro eliminado del proveedor.')
            : new BiometricEnrollmentOutcome(false, 'Consentimiento revocado localmente; el proveedor no confirmó la baja del rostro: '.$remoteError);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(Employee $employee, ?User $actor, string $action, array $attributes): void
    {
        DB::transaction(function () use ($employee, $actor, $action, $attributes): void {
            $before = $this->snapshot($this->enrollmentFor($employee));
            $enrollment = BiometricEnrollment::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                $attributes,
            );
            $this->audit->record($action, $employee, $actor, $before, $this->snapshot($enrollment));
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshot(?BiometricEnrollment $enrollment): ?array
    {
        if (! $enrollment instanceof BiometricEnrollment) {
            return null;
        }

        return [
            'status' => $enrollment->status->value,
            'provider' => $enrollment->provider,
            'consent_version' => $enrollment->consent_version,
            'simulated' => $enrollment->provider === self::PROVIDER_DEMO,
        ];
    }

    private function enrollmentFor(Employee $employee): ?BiometricEnrollment
    {
        return BiometricEnrollment::query()->where('employee_id', $employee->id)->first();
    }

    private function provider(string $driver): string
    {
        return $driver === FaceRecognitionManager::DRIVER_FAKE ? self::PROVIDER_DEMO : self::PROVIDER_AWS;
    }

    private function maskCollectionId(mixed $collectionId): ?string
    {
        if (! is_string($collectionId) || trim($collectionId) === '') {
            return null;
        }

        return '****'.mb_substr(trim($collectionId), -4);
    }
}
