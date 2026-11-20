<?php

namespace App\Services\Kiosk;

use App\Models\AttendanceEvent;

/**
 * Resultado de un intento de marcaje en el checador.
 *
 * `duplicate` indica que ya existía un marcaje del mismo tipo dentro de la
 * ventana de doble marcaje y que, por tanto, no se creó otro evento.
 */
final class ClockRecordOutcome
{
    private function __construct(
        public readonly bool $recorded,
        public readonly bool $duplicate,
        public readonly AttendanceEvent $event,
    ) {}

    public static function recorded(AttendanceEvent $event): self
    {
        return new self(true, false, $event);
    }

    public static function duplicate(AttendanceEvent $event): self
    {
        return new self(false, true, $event);
    }
}
