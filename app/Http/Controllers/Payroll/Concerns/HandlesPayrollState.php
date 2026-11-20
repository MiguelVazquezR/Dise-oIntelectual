<?php

namespace App\Http\Controllers\Payroll\Concerns;

use App\Services\Payroll\Exceptions\PayrollPeriodClosedException;
use App\Services\Payroll\Exceptions\PayrollStateException;
use Illuminate\Http\RedirectResponse;

/**
 * Respuestas comunes a los conflictos de estado de la pre-nómina.
 *
 * Un periodo `closed` responde 409 sin tocar datos (contrato §7.4); el resto de
 * conflictos vuelve al formulario con `flash.error`.
 */
trait HandlesPayrollState
{
    protected function payrollConflict(string $message): RedirectResponse
    {
        return back()->with('error', $message)->setStatusCode(409);
    }

    protected function payrollStateError(PayrollStateException $exception): RedirectResponse
    {
        return $exception instanceof PayrollPeriodClosedException
            ? $this->payrollConflict($exception->getMessage())
            : back()->with('error', $exception->getMessage());
    }
}
