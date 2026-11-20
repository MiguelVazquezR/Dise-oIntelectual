<?php

namespace App\Services\Face;

use App\Enums\AwsFaceOperation;
use App\Enums\AwsUsageSource;
use App\Enums\AwsUsageStatus;
use App\Models\AwsUsageRecord;
use App\Services\Aws\AwsCostCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Anota en `aws_usage_records` cada operación facial facturable.
 *
 * - El `idempotency_key` identifica el **intento lógico**: reintentar con la
 *   misma clave actualiza la fila existente en lugar de duplicar el consumo.
 * - El importe se calcula con `AwsCostCalculator` (enteros, sin `float`):
 *   cantidad × tarifa / 1,000 con 6 decimales; los fallos se guardan con
 *   `status=failed` y `amount_mxn=0.000000`.
 * - `metadata` nunca contiene imágenes, PIN ni secretos.
 */
final class FaceUsageRecorder
{
    public function __construct(private readonly AwsCostCalculator $costCalculator) {}

    /**
     * @param  array<string, mixed>  $metadata  Datos no sensibles (código de error, región, etc.).
     */
    public function record(
        AwsFaceOperation $operation,
        AwsUsageStatus $status,
        AwsUsageSource $source,
        string $idempotencyKey,
        int $quantity = 1,
        ?int $branchId = null,
        ?int $employeeId = null,
        ?string $awsRequestId = null,
        array $metadata = [],
        ?CarbonInterface $occurredAt = null,
    ): AwsUsageRecord {
        $quantity = max(0, $quantity);
        $unitPrice = $this->costCalculator->pricePerThousand();
        $amount = $this->costCalculator->amountForStatus($status, $quantity, $unitPrice);

        $attributes = [
            'branch_id' => $branchId,
            'employee_id' => $employeeId,
            'operation' => $operation,
            'quantity' => $quantity,
            'status' => $status,
            'source' => $source,
            'aws_request_id' => $awsRequestId,
            'unit_price_mxn' => $unitPrice,
            'amount_mxn' => $amount,
            'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
            'metadata' => $metadata,
        ];

        return DB::transaction(function () use ($idempotencyKey, $attributes): AwsUsageRecord {
            $existing = AwsUsageRecord::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof AwsUsageRecord) {
                $existing->fill($attributes)->save();

                return $existing;
            }

            try {
                return AwsUsageRecord::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    ...$attributes,
                ]);
            } catch (UniqueConstraintViolationException) {
                $record = AwsUsageRecord::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
                $record->fill($attributes)->save();

                return $record;
            }
        });
    }
}
