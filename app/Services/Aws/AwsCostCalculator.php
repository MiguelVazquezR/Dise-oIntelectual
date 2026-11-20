<?php

namespace App\Services\Aws;

use App\Enums\AwsFaceOperation;
use App\Enums\AwsUsageStatus;
use App\Models\AwsUsageRecord;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Costo interno del consumo de AWS Rekognition (S-05: nunca `float`).
 *
 * La tarifa es un **cargo interno acordado** por cada 1,000 operaciones —no la
 * tarifa oficial de AWS (D-14)— y se configura en `rekognition.price_per_1000`.
 * Todos los importes se calculan con enteros escalados y se serializan como
 * cadenas decimales de 6 posiciones.
 */
final class AwsCostCalculator
{
    /** Posiciones decimales de `aws_usage_records.amount_mxn`. */
    public const DECIMALS = 6;

    private const MICRO_UNITS = 1_000_000;

    /**
     * Tarifa interna por millar de operaciones, tal como está configurada.
     */
    public function pricePerThousand(): string
    {
        $configured = config('rekognition.price_per_1000', '24');
        $price = is_string($configured) ? trim($configured) : (string) $configured;

        return $price !== '' ? $price : '24';
    }

    /**
     * Tarifa interna lista para presentación: cadena con 2 decimales (§7.4).
     *
     * Se redondea con enteros escalados (medio hacia arriba) para no pasar por
     * `float`, igual que el resto de los importes.
     */
    public function pricePerThousandForDisplay(): string
    {
        $cents = intdiv(max(0, $this->toMicro($this->pricePerThousand())) + 5_000, 10_000);

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    public function zero(): string
    {
        return '0.'.str_repeat('0', self::DECIMALS);
    }

    /**
     * Importe de una operación: cantidad × tarifa / 1,000, redondeado a 6
     * posiciones con redondeo medio hacia arriba.
     */
    public function amountFor(int $quantity, ?string $pricePerThousand = null): string
    {
        $quantity = max(0, $quantity);

        if ($quantity === 0) {
            return $this->zero();
        }

        [$scaledPrice, $scale] = $this->parseDecimal($pricePerThousand ?? $this->pricePerThousand());

        if ($scaledPrice <= 0) {
            return $this->zero();
        }

        $denominator = 10 ** $scale;
        $numerator = $quantity * $scaledPrice * 1_000;
        $micro = intdiv($numerator, $denominator);

        if (($numerator % $denominator) * 2 >= $denominator) {
            $micro++;
        }

        return $this->formatMicro($micro);
    }

    /**
     * Las operaciones fallidas se registran con importe cero: no se cobran.
     */
    public function amountForStatus(AwsUsageStatus $status, int $quantity, ?string $pricePerThousand = null): string
    {
        return $status === AwsUsageStatus::Succeeded
            ? $this->amountFor($quantity, $pricePerThousand)
            : $this->zero();
    }

    /**
     * Suma de importes sin pasar por `float`.
     */
    public function add(string $left, string $right): string
    {
        return $this->formatMicro($this->toMicro($left) + $this->toMicro($right));
    }

    public function toMicro(string $amount): int
    {
        $value = trim($amount);

        if ($value === '') {
            return 0;
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        $parts = explode('.', $value, 2);
        $whole = (int) preg_replace('/\D/', '', $parts[0]);
        $fraction = substr(
            str_pad((string) preg_replace('/\D/', '', $parts[1] ?? ''), self::DECIMALS, '0'),
            0,
            self::DECIMALS,
        );
        $micro = ($whole * self::MICRO_UNITS) + (int) $fraction;

        return $negative ? -$micro : $micro;
    }

    public function formatMicro(int $micro): string
    {
        $sign = $micro < 0 ? '-' : '';
        $absolute = abs($micro);

        return sprintf('%s%d.%06d', $sign, intdiv($absolute, self::MICRO_UNITS), $absolute % self::MICRO_UNITS);
    }

    /**
     * Totales del consumo facturable de la consulta recibida.
     *
     * Las filas se agregan en PHP con enteros para no perder precisión: solo
     * las operaciones `succeeded` consumen rostros e importe; las fallidas se
     * cuentan en `records` pero no en `faces`.
     *
     * @param  Builder<AwsUsageRecord>  $query
     * @return array{
     *     faces: int,
     *     amount_mxn: string,
     *     records: int,
     *     by_operation: array<int, array{operation: string, faces: int, amount_mxn: string}>,
     *     by_branch: array<int, array{branch: array{id: int|null, name: string}, faces: int, amount_mxn: string}>
     * }
     */
    public function totals(Builder $query): array
    {
        $records = $query->get(['operation', 'quantity', 'status', 'amount_mxn', 'branch_id']);

        $branchNames = $this->branchNames($records);
        $operationTotals = [];
        $branchTotals = [];
        $faces = 0;
        $amountMicro = 0;

        foreach ($records as $record) {
            $operation = $record->operation->value;
            $branchKey = (int) ($record->branch_id ?? 0);

            $operationTotals[$operation] ??= ['faces' => 0, 'micro' => 0];
            $branchTotals[$branchKey] ??= ['faces' => 0, 'micro' => 0];

            if ($record->status !== AwsUsageStatus::Succeeded) {
                continue;
            }

            $quantity = (int) $record->quantity;
            $recordMicro = $this->toMicro((string) $record->amount_mxn);

            $faces += $quantity;
            $amountMicro += $recordMicro;
            $operationTotals[$operation]['faces'] += $quantity;
            $operationTotals[$operation]['micro'] += $recordMicro;
            $branchTotals[$branchKey]['faces'] += $quantity;
            $branchTotals[$branchKey]['micro'] += $recordMicro;
        }

        $byOperation = [];

        foreach (AwsFaceOperation::cases() as $case) {
            if (! isset($operationTotals[$case->value])) {
                continue;
            }

            $byOperation[] = [
                'operation' => $case->value,
                'faces' => $operationTotals[$case->value]['faces'],
                'amount_mxn' => $this->formatMicro($operationTotals[$case->value]['micro']),
            ];
        }

        $byBranch = [];

        foreach ($branchTotals as $branchKey => $totals) {
            $byBranch[] = [
                'branch' => [
                    'id' => $branchKey > 0 ? $branchKey : null,
                    'name' => $branchKey > 0 ? ($branchNames[$branchKey] ?? 'Sucursal') : 'Sin sucursal',
                ],
                'faces' => $totals['faces'],
                'amount_mxn' => $this->formatMicro($totals['micro']),
            ];
        }

        usort($byBranch, fn (array $left, array $right): int => strcmp($left['branch']['name'], $right['branch']['name']));

        return [
            'faces' => $faces,
            'amount_mxn' => $this->formatMicro($amountMicro),
            'records' => $records->count(),
            'by_operation' => $byOperation,
            'by_branch' => $byBranch,
        ];
    }

    /**
     * @param  Collection<int, AwsUsageRecord>  $records
     * @return array<int, string>
     */
    private function branchNames(Collection $records): array
    {
        $branchIds = $records
            ->pluck('branch_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($branchIds === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id')
            ->all();

        return $names;
    }

    /**
     * @return array{0: int, 1: int} Precio escalado a entero y cantidad de decimales.
     */
    private function parseDecimal(string $value): array
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,12}))?$/', trim($value), $matches) !== 1) {
            return [0, 0];
        }

        $fraction = $matches[2] ?? '';

        return [(int) ($matches[1].$fraction), strlen($fraction)];
    }
}
