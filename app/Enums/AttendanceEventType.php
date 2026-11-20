<?php

namespace App\Enums;

enum AttendanceEventType: string
{
    case ClockIn = 'clock_in';
    case BreakStart = 'break_start';
    case BreakEnd = 'break_end';
    case ClockOut = 'clock_out';

    public function label(): string
    {
        return match ($this) {
            self::ClockIn => 'Entrada',
            self::BreakStart => 'Inicio de descanso',
            self::BreakEnd => 'Fin de descanso',
            self::ClockOut => 'Salida',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
