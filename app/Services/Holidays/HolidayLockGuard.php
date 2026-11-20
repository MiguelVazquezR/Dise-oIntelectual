<?php

namespace App\Services\Holidays;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Protección de años cerrados del calendario de festivos.
 *
 * Un año se considera cerrado cuando es anterior al año en curso: sus festivos
 * ya se usaron en pre-nómina y no deben modificarse sin autorización explícita
 * del coordinador (bandera `force` de la petición, validada en el controlador).
 */
final class HolidayLockGuard
{
    /**
     * El año está cerrado si es anterior al año actual.
     */
    public function isLocked(int $year): bool
    {
        return $year < $this->currentYear();
    }

    public function currentYear(): int
    {
        return (int) CarbonImmutable::now()->year;
    }

    /**
     * ¿La fecha cae dentro de un periodo de pre-nómina ya cerrado?
     *
     * Se usa para impedir borrados de festivos que ya impactaron un periodo
     * cerrado (§7.3).
     */
    public function hasClosedPayroll(CarbonInterface $date): bool
    {
        $day = $date->toDateString();

        return PayrollPeriod::query()
            ->where('status', PayrollPeriodStatus::Closed->value)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->exists();
    }

    /**
     * Mensaje de error homogéneo para la UI.
     */
    public function lockedMessage(int $year): string
    {
        return "El año {$year} está cerrado: sus festivos ya no se pueden modificar. Solicita autorización explícita al coordinador.";
    }
}
