<?php

namespace App\Services\Face;

/**
 * Resultado tipado de una búsqueda de rostro (`SearchFacesByImage`).
 *
 * `success` indica que AWS procesó la imagen; `employeeId` queda en `null`
 * cuando no hubo coincidencia dentro de la sucursal consultada.
 */
final class FaceSearchResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?int $employeeId,
        public readonly ?string $faceId,
        public readonly ?float $confidence,
        public readonly ?string $requestId,
        public readonly bool $simulated,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
    ) {}

    public static function success(
        ?int $employeeId = null,
        ?string $faceId = null,
        ?float $confidence = null,
        ?string $requestId = null,
        bool $simulated = false,
    ): self {
        return new self(true, $employeeId, $faceId, $confidence, $requestId, $simulated, null, null);
    }

    public static function failure(string $errorCode, string $errorMessage, bool $simulated = false): self
    {
        return new self(false, null, null, null, null, $simulated, $errorCode, $errorMessage);
    }

    public function matched(): bool
    {
        return $this->success && $this->employeeId !== null;
    }
}
