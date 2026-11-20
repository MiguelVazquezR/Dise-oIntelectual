<?php

namespace App\Http\Requests\Concerns;

use Closure;

/**
 * Validación del payload de una imagen facial enviada al proveedor biométrico.
 *
 * Controles explícitos antes de llamar a AWS Rekognition:
 * - base64 estricto (admite data URL del navegador);
 * - contenido decodificado entre 1 byte y 5 MB (límite del proveedor);
 * - tipo declarado en un data URL limitado a JPG/PNG/WEBP y verificado contra
 *   los bytes reales de la imagen (no se confía en la extensión ni en el MIME
 *   declarado por el cliente).
 *
 * La imagen se procesa en memoria: nunca se escribe en disco ni en logs.
 */
trait ValidatesFaceImage
{
    /** Límite documentado de Rekognition para una imagen (`Bytes`, 5 MB). */
    protected const MAX_FACE_IMAGE_BYTES = 5 * 1024 * 1024;

    /** Tipos MIME aceptados para una fotografía de rostro. */
    protected const ALLOWED_FACE_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Reglas del campo `image`.
     *
     * @return array<int, string|Closure>
     */
    protected function faceImageRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:8000000',
            function (string $attribute, mixed $value, Closure $fail): void {
                $error = $this->faceImageError($value);

                if ($error !== null) {
                    $fail($error);
                }
            },
        ];
    }

    /**
     * Devuelve el mensaje de error o `null` cuando la imagen cumple los requisitos.
     */
    protected function faceImageError(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return 'Adjunta una fotografía válida del rostro.';
        }

        $payload = trim($value);
        $declaredMime = null;

        if (str_starts_with($payload, 'data:')) {
            if (preg_match('#^data:([a-z]+/[a-z0-9.+-]+);base64,#i', $payload, $matches) !== 1) {
                return 'El formato de la imagen no es válido: se esperaba base64.';
            }

            $declaredMime = strtolower($matches[1]);
            $payload = substr($payload, strlen($matches[0]));
        }

        $binary = base64_decode((string) preg_replace('/\s+/', '', $payload), true);

        if (! is_string($binary) || $binary === '') {
            return 'La imagen recibida no es un base64 válido.';
        }

        if (strlen($binary) > self::MAX_FACE_IMAGE_BYTES) {
            return 'La imagen excede el límite de 5 MB por rostro.';
        }

        if ($declaredMime !== null) {
            if (! in_array($declaredMime, self::ALLOWED_FACE_IMAGE_MIMES, true)) {
                return 'Solo se admiten imágenes JPG, PNG o WEBP.';
            }

            $info = @getimagesizefromstring($binary);
            $detected = is_array($info) ? strtolower($info['mime']) : null;

            if ($detected === null || ! in_array($detected, self::ALLOWED_FACE_IMAGE_MIMES, true)) {
                return 'El contenido de la imagen no corresponde a un JPG, PNG o WEBP válido.';
            }
        }

        return null;
    }
}
