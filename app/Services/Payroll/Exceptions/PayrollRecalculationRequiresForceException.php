<?php

namespace App\Services\Payroll\Exceptions;

/**
 * El periodo ya fue revisado y el recálculo requiere confirmación explícita
 * (`force`), que además deja nota en la bitácora.
 */
class PayrollRecalculationRequiresForceException extends PayrollStateException {}
