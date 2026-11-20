<?php

namespace App\Enums;

enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Reviewed = 'reviewed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
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
