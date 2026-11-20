<?php

namespace App\Enums;

enum PayrollConceptCalculation: string
{
    case Fixed = 'fixed';
    case PerQuantity = 'per_quantity';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Monto fijo',
            self::PerQuantity => 'Por cantidad',
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
