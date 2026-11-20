<?php

namespace App\Enums;

enum AttendanceMethod: string
{
    case Pin = 'pin';
    case Face = 'face';

    public function label(): string
    {
        return match ($this) {
            self::Pin => 'PIN',
            self::Face => 'Reconocimiento facial',
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
