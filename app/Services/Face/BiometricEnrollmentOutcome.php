<?php

namespace App\Services\Face;

/**
 * Resultado de una operación de consentimiento/enrolamiento biométrico.
 */
final class BiometricEnrollmentOutcome
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
    ) {}
}
