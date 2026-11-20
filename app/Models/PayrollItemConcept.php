<?php

namespace App\Models;

use App\Enums\PayrollConceptType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payroll_item_id
 * @property string $code
 * @property string $name
 * @property PayrollConceptType $type
 * @property string $quantity
 * @property string $unit_amount
 * @property string $amount
 * @property string|null $rule_applied
 * @property string|null $source_type
 * @property int|null $source_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PayrollItem $item
 */
#[Fillable([
    'payroll_item_id',
    'code',
    'name',
    'type',
    'quantity',
    'unit_amount',
    'amount',
    'rule_applied',
    'source_type',
    'source_id',
])]
class PayrollItemConcept extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PayrollConceptType::class,
            'quantity' => 'decimal:2',
            'unit_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PayrollItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }
}
