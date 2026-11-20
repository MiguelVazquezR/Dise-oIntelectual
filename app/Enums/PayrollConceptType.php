<?php

namespace App\Enums;

enum PayrollConceptType: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::Earning => 'Percepción',
            self::Deduction => 'Deducción',
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
