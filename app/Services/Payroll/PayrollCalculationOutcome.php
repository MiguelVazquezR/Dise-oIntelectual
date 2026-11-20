<?php

namespace App\Services\Payroll;

/**
 * Resultado de un cálculo de pre-nómina.
 *
 * Todos los importes se exponen como cadena decimal de 2 posiciones (contrato
 * §7) calculada a partir de centavos enteros.
 */
final class PayrollCalculationOutcome
{
    public function __construct(
        public readonly int $runs,
        public readonly int $items,
        public readonly int $earningsCents,
        public readonly int $deductionsCents,
        public readonly bool $recalculated,
        public readonly bool $forced,
        public readonly ?string $note = null,
    ) {}

    /**
     * @return array{earnings: string, deductions: string, net: string}
     */
    public function totals(): array
    {
        return [
            'earnings' => PayrollMoney::toDecimal($this->earningsCents),
            'deductions' => PayrollMoney::toDecimal($this->deductionsCents),
            'net' => PayrollMoney::toDecimal($this->earningsCents - $this->deductionsCents),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'runs' => $this->runs,
            'items' => $this->items,
            'totals' => $this->totals(),
            'recalculated' => $this->recalculated,
            'forced' => $this->forced,
            'note' => $this->note,
        ];
    }
}
