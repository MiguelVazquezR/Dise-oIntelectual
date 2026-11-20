<?php

namespace App\Models;

use App\Enums\AwsFaceOperation;
use App\Enums\AwsUsageSource;
use App\Enums\AwsUsageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $branch_id
 * @property int|null $employee_id
 * @property AwsFaceOperation $operation
 * @property int $quantity
 * @property AwsUsageStatus $status
 * @property AwsUsageSource $source
 * @property string|null $aws_request_id
 * @property string $idempotency_key
 * @property string $unit_price_mxn
 * @property string $amount_mxn
 * @property Carbon $occurred_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Branch|null $branch
 * @property-read Employee|null $employee
 */
#[Fillable([
    'branch_id',
    'employee_id',
    'operation',
    'quantity',
    'status',
    'source',
    'aws_request_id',
    'idempotency_key',
    'unit_price_mxn',
    'amount_mxn',
    'occurred_at',
    'metadata',
])]
class AwsUsageRecord extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation' => AwsFaceOperation::class,
            'status' => AwsUsageStatus::class,
            'source' => AwsUsageSource::class,
            'quantity' => 'integer',
            'unit_price_mxn' => 'decimal:4',
            'amount_mxn' => 'decimal:6',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @param  Builder<AwsUsageRecord>  $query
     */
    #[Scope]
    protected function forPeriod(Builder $query, Carbon|string $from, Carbon|string $to): void
    {
        $query->whereBetween('occurred_at', [$from, $to]);
    }

    /**
     * @param  Builder<AwsUsageRecord>  $query
     */
    #[Scope]
    protected function fromSource(Builder $query, AwsUsageSource $source): void
    {
        $query->where('source', $source);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
