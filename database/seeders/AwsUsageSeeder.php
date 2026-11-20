<?php

namespace Database\Seeders;

use App\Enums\AwsFaceOperation;
use App\Enums\AwsUsageSource;
use App\Enums\AwsUsageStatus;
use App\Models\AwsUsageRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\Aws\AwsCostCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Consumo simulado de AWS Rekognition para el panel interno.
 *
 * - Todos los registros son ficticios y llevan `source=demo`; nunca se marcan
 *   como `real`.
 * - No crea personas: usa las sucursales y colaboradores ficticios existentes
 *   y deja las referencias en `null` si todavía no se han sembrado.
 * - Es idempotente: la `idempotency_key` se deriva de la fecha y del índice, de
 *   modo que ejecutar el seeder dos veces no duplica registros.
 * - Cubre los últimos 60 días con importes calculados por `AwsCostCalculator`
 *   (unos pocos registros fallidos con importe cero).
 */
class AwsUsageSeeder extends Seeder
{
    private const DAYS = 60;

    private const TIMEZONE = 'America/Mexico_City';

    public function run(): void
    {
        $calculator = app(AwsCostCalculator::class);
        $price = $calculator->pricePerThousand();
        $operations = AwsFaceOperation::cases();
        $branches = Branch::query()->orderBy('id')->get(['id']);
        $employees = Employee::query()->orderBy('id')->get(['id', 'branch_id']);
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $created = 0;

        for ($offset = self::DAYS - 1; $offset >= 0; $offset--) {
            $day = $today->subDays($offset);
            $recordsForDay = 1 + ($this->seedNumber('day:'.$day->toDateString()) % 3);

            for ($index = 0; $index < $recordsForDay; $index++) {
                $key = 'demo-usage:'.$day->toDateString().':'.$index;
                $seed = $this->seedNumber($key);
                $employee = $employees->isEmpty() ? null : $employees[$seed % $employees->count()];
                $branchId = $employee?->branch_id;

                if ($branchId === null && ! $branches->isEmpty()) {
                    $branchId = $branches[$seed % $branches->count()]->id;
                }

                $failed = $seed % 12 === 0;
                $status = $failed ? AwsUsageStatus::Failed : AwsUsageStatus::Succeeded;
                $quantity = 1 + ($seed % 5);

                $record = AwsUsageRecord::query()->updateOrCreate(
                    ['idempotency_key' => $key],
                    [
                        'branch_id' => $branchId,
                        'employee_id' => $employee?->id,
                        'operation' => $operations[$seed % count($operations)],
                        'quantity' => $quantity,
                        'status' => $status,
                        'source' => AwsUsageSource::Demo,
                        'aws_request_id' => $failed ? null : 'demo-request-'.substr(hash('sha256', $key), 0, 12),
                        'unit_price_mxn' => $price,
                        'amount_mxn' => $calculator->amountForStatus($status, $quantity, $price),
                        'occurred_at' => $day->setTime(9 + ($seed % 8), $seed % 60, 0)->utc(),
                        'metadata' => [
                            'simulated' => true,
                            'note' => 'Registro ficticio de demostración; no corresponde a llamadas reales a AWS.',
                        ],
                    ],
                );

                if ($record->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        $this->command?->info('Consumo AWS simulado: '.$created.' registros demo nuevos (los existentes se conservaron).');
    }

    private function seedNumber(string $key): int
    {
        return (int) (hexdec(substr(hash('sha256', $key), 0, 8)) % 1_000);
    }
}
