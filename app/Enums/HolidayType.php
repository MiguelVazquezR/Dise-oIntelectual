<?php

namespace App\Enums;

enum HolidayType: string
{
    case Mandatory = 'mandatory';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Mandatory => 'Obligatorio (LFT)',
            self::Company => 'De la empresa',
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
