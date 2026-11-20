<?php

namespace App\Enums;

enum BiometricStatus: string
{
    case Pending = 'pending';
    case Enrolled = 'enrolled';
    case Failed = 'failed';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Enrolled => 'Registrado',
            self::Failed => 'Fallido',
            self::Revoked => 'Revocado',
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
