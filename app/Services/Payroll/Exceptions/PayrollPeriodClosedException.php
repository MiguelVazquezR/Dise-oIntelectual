<?php

namespace App\Services\Payroll\Exceptions;

/**
 * El periodo ya está cerrado: no se puede calcular, revisar ni modificar.
 *
 * El controlador responde 409 sin tocar datos (contrato §7.4).
 */
class PayrollPeriodClosedException extends PayrollStateException {}
