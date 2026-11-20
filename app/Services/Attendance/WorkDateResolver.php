<?php

namespace App\Services\Attendance;

use App\Models\Branch;
use Carbon\CarbonImmutable;

/**
 * Determina la `work_date` de un marcaje en la zona horaria de la sucursal.
 *
 * Si el instante cae dentro de la ventana del turno iniciado el día anterior
 * (turno nocturno, D-09), la fecha laborable es el día anterior. La ventana
 * incluye una gracia posterior al fin del turno para que las salidas que se
 * registran unos minutos tarde sigan perteneciendo al turno previo en lugar de
 * abrir un día nuevo con la jornada anterior incompleta.
 */
final class WorkDateResolver
{
    /** Minutos de gracia después del fin del turno previo (por defecto 2 h). */
    public const DEFAULT_GRACE_MINUTES = 120;

    public function __construct(
        private readonly ScheduleWindowResolver $resolver,
        private readonly int $graceMinutes = self::DEFAULT_GRACE_MINUTES,
    ) {}

    public function resolve(Branch $branch, CarbonImmutable $moment): CarbonImmutable
    {
        $local = $moment->setTimezone($branch->timezone);
        $today = $local->startOfDay();
        $previous = $this->resolver->forBranchDate($branch, $today->subDay());

        $start = $previous->scheduledStart();
        $end = $previous->scheduledEnd();

        if ($start !== null && $end !== null) {
            $graceEnd = $end->addMinutes(max(0, $this->graceMinutes));

            if ($local->greaterThanOrEqualTo($start) && $local->lessThanOrEqualTo($graceEnd)) {
                return $today->subDay();
            }
        }

        return $today;
    }
}
