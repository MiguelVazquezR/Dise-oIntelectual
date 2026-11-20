<?php

namespace App\Enums;

enum AwsUsageStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => 'Exitoso',
            self::Failed => 'Fallido',
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
