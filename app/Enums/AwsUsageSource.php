<?php

namespace App\Enums;

enum AwsUsageSource: string
{
    case Demo = 'demo';
    case Real = 'real';

    public function label(): string
    {
        return match ($this) {
            self::Demo => 'Simulación',
            self::Real => 'AWS real',
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
