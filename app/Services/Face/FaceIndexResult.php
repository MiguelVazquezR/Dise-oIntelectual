<?php

namespace App\Services\Face;

/**
 * Resultado tipado de un enrolamiento facial (`IndexFaces`).
 *
 * Nunca expone la imagen ni datos del SDK distintos del identificador del
 * rostro y del `aws_request_id` de la operación.
 */
final class FaceIndexResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $faceId,
        public readonly ?string $requestId,
        public readonly bool $simulated,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
    ) {}

    public static function success(string $faceId, ?string $requestId, bool $simulated = false): self
    {
        return new self(true, $faceId, $requestId, $simulated, null, null);
    }

    public static function failure(string $errorCode, string $errorMessage, bool $simulated = false): self
    {
        return new self(false, null, null, $simulated, $errorCode, $errorMessage);
    }
}
