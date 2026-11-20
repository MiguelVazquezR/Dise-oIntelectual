<?php

namespace App\Enums;

enum PayrollPeriodStatus: string
{
    case Open = 'open';
    case Calculated = 'calculated';
    case Reviewed = 'reviewed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::Calculated => 'Calculado',
            self::Reviewed => 'Revisado',
            self::Closed => 'Cerrado',
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
