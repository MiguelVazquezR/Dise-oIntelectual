<?php

namespace App\Services\Face;

/**
 * Resultado tipado de una baja de rostro (`DeleteFaces`).
 */
final class FaceDeleteResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $requestId,
        public readonly bool $simulated,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
    ) {}

    public static function success(?string $requestId, bool $simulated = false): self
    {
        return new self(true, $requestId, $simulated, null, null);
    }

    public static function failure(string $errorCode, string $errorMessage, bool $simulated = false): self
    {
        return new self(false, null, $simulated, $errorCode, $errorMessage);
    }
}
