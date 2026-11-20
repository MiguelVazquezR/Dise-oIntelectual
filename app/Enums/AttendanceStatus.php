<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Valid = 'valid';
    case Flagged = 'flagged';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Válido',
            self::Flagged => 'Con observaciones',
            self::Rejected => 'Rechazado',
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
