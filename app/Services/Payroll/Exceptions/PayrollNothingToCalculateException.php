<?php

namespace App\Services\Payroll\Exceptions;

/**
 * No hay colaboradores activos con compensación vigente en las sucursales
 * visibles para el actor, por lo que el cálculo no tiene nada que procesar.
 */
class PayrollNothingToCalculateException extends PayrollStateException {}
