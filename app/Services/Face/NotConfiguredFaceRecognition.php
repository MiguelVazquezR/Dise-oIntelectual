<?php

namespace App\Services\Face;

use App\Contracts\FaceRecognitionService;

/**
 * Implementación inerte del contrato biométrico (D-13).
 *
 * Se usa mientras AWS Rekognition no esté configurado: responde que no está
 * listo y devuelve fallos tipados en lugar de lanzar excepciones, de forma que
 * el checador nunca se rompe y jamás se intenta una llamada de red.
 */
final class NotConfiguredFaceRecognition implements FaceRecognitionService
{
    private const MESSAGE = 'El reconocimiento facial aún no está configurado.';

    public function isConfigured(): bool
    {
        return false;
    }

    public function driver(): string
    {
        return FaceRecognitionManager::DRIVER_NOT_CONFIGURED;
    }

    public function isSimulated(): bool
    {
        return false;
    }

    public function indexFace(
        int $employeeId,
        string $imageContent,
        ?int $branchId = null,
        ?string $idempotencyKey = null,
    ): FaceIndexResult {
        return FaceIndexResult::failure('not_configured', self::MESSAGE);
    }

    public function searchFaceByIdentity(
        int $branchId,
        string $imageContent,
        ?string $idempotencyKey = null,
    ): FaceSearchResult {
        return FaceSearchResult::failure('not_configured', self::MESSAGE);
    }

    public function deleteFace(
        string $externalFaceId,
        ?int $employeeId = null,
        ?int $branchId = null,
        ?string $idempotencyKey = null,
    ): FaceDeleteResult {
        return FaceDeleteResult::failure('not_configured', self::MESSAGE);
    }
}
