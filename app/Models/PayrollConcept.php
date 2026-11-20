<?php

namespace App\Models;

use App\Enums\PayrollConceptCalculation;
use App\Enums\PayrollConceptType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property PayrollConceptType $type
 * @property PayrollConceptCalculation $calculation
 * @property string|null $default_amount
 * @property bool $is_sensitive
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'type', 'calculation', 'default_amount', 'is_sensitive', 'is_active', 'notes'])]
class PayrollConcept extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PayrollConceptType::class,
            'calculation' => PayrollConceptCalculation::class,
            'default_amount' => 'decimal:2',
            'is_sensitive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<PayrollConcept>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<EmployeePayrollAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(EmployeePayrollAdjustment::class);
    }
}
