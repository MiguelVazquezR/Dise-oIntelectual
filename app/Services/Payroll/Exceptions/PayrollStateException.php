<?php

namespace App\Services\Payroll\Exceptions;

use RuntimeException;

/**
 * Conflicto de estado de la pre-nómina (mensaje apto para mostrar al usuario).
 *
 * Los controladores convierten esta excepción en un `flash.error`; la subclase
 * `PayrollPeriodClosedException` responde además con HTTP 409 (contrato §7.4).
 */
class PayrollStateException extends RuntimeException {}
