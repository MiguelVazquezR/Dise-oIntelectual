<?php

namespace App\Services\Face;

use App\Contracts\FaceRecognitionService;
use App\Enums\AwsFaceOperation;
use App\Enums\AwsUsageSource;
use App\Enums\AwsUsageStatus;
use Illuminate\Support\Str;
use Throwable;

/**
 * Simulación explícita del proveedor biométrico (D-13, driver `fake`).
 *
 * No realiza llamadas de red y **nunca** devuelve una identidad: una búsqueda
 * simulada no reconoce a nadie para no registrar asistencia ficticia. Todo su
 * consumo se anota con `source=demo` y las páginas lo etiquetan como simulación.
 */
final class FakeFaceRecognition implements FaceRecognitionService
{
    public function __construct(private readonly FaceUsageRecorder $usage) {}

    public function isConfigured(): bool
    {
        return false;
    }

    public function driver(): string
    {
        return FaceRecognitionManager::DRIVER_FAKE;
    }

    public function isSimulated(): bool
    {
        return true;
    }

    public function indexFace(
        int $employeeId,
        string $imageContent,
        ?int $branchId = null,
        ?string $idempotencyKey = null,
    ): FaceIndexResult {
        $this->recordUsage(
            AwsFaceOperation::IndexFaces,
            $idempotencyKey ?? (string) Str::uuid(),
            $employeeId,
            $branchId,
        );

        return FaceIndexResult::success('demo-face-'.$employeeId, null, true);
    }

    public function searchFaceByIdentity(
        int $branchId,
        string $imageContent,
        ?string $idempotencyKey = null,
    ): FaceSearchResult {
        $this->recordUsage(
            AwsFaceOperation::SearchFacesByImage,
            $idempotencyKey ?? (string) Str::uuid(),
            null,
            $branchId,
        );

        return FaceSearchResult::success(simulated: true);
    }

    public function deleteFace(
        string $externalFaceId,
        ?int $employeeId = null,
        ?int $branchId = null,
        ?string $idempotencyKey = null,
    ): FaceDeleteResult {
        $this->recordUsage(
            AwsFaceOperation::DeleteFaces,
            $idempotencyKey ?? (string) Str::uuid(),
            $employeeId,
            $branchId,
        );

        return FaceDeleteResult::success(null, true);
    }

    private function recordUsage(
        AwsFaceOperation $operation,
        string $idempotencyKey,
        ?int $employeeId,
        ?int $branchId,
    ): void {
        try {
            $this->usage->record(
                operation: $operation,
                status: AwsUsageStatus::Succeeded,
                source: AwsUsageSource::Demo,
                idempotencyKey: $idempotencyKey,
                quantity: 1,
                branchId: $branchId,
                employeeId: $employeeId,
                metadata: ['simulated' => true, 'driver' => self::class],
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
