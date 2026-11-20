<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Payroll\Concerns\HandlesPayrollState;
use App\Http\Requests\Payroll\CalculatePayrollPeriodRequest;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\Exceptions\PayrollStateException;
use App\Services\Payroll\PayrollCalculator;
use Illuminate\Http\RedirectResponse;

/**
 * Cálculo (y recálculo) de la pre-nómina de un periodo (§7.4).
 *
 * El cálculo es idempotente: reemplaza items y conceptos de cada corrida.
 * - Periodo `reviewed`: exige `force` para recalcular y deja nota.
 * - Periodo `closed`: 409 sin tocar nada.
 * - Usuario de sucursal: solo calcula las sucursales que tiene asignadas.
 */
class PayrollCalculationController extends Controller
{
    use HandlesPayrollState;

    public function __construct(private readonly PayrollCalculator $calculator) {}

    public function store(CalculatePayrollPeriodRequest $request, PayrollPeriod $period): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $outcome = $this->calculator->calculate(
                period: $period,
                actor: $user,
                force: $request->boolean('force'),
                note: $request->input('note') !== null ? (string) $request->input('note') : null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (PayrollStateException $exception) {
            return $this->payrollStateError($exception);
        }

        $message = sprintf(
            'Pre-nómina calculada: %d corrida(s), %d colaborador(es). Neto preliminar %s %s.',
            $outcome->runs,
            $outcome->items,
            config('payroll.currency', 'MXN'),
            $outcome->totals()['net'],
        );

        if ($outcome->recalculated) {
            $message .= ' Se reemplazaron los importes anteriores del periodo.';
        }

        return back()->with('success', $message);
    }
}
