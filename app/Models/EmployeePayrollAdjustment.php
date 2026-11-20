<?php

namespace App\Models;

use App\Enums\AdjustmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int|null $payroll_period_id
 * @property int $payroll_concept_id
 * @property string $amount
 * @property string $quantity
 * @property string $reason
 * @property AdjustmentStatus $status
 * @property int $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $applied_payroll_item_id
 * @property bool $is_sensitive
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read PayrollConcept $concept
 */
#[Fillable([
    'employee_id',
    'payroll_period_id',
    'payroll_concept_id',
    'amount',
    'quantity',
    'reason',
    'status',
    'created_by',
    'approved_by',
    'approved_at',
    'applied_payroll_item_id',
    'is_sensitive',
])]
class EmployeePayrollAdjustment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quantity' => 'decimal:2',
            'status' => AdjustmentStatus::class,
            'approved_at' => 'datetime',
            'is_sensitive' => 'boolean',
        ];
    }

    /**
     * @param  Builder<EmployeePayrollAdjustment>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', AdjustmentStatus::Pending);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * @return BelongsTo<PayrollConcept, $this>
     */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(PayrollConcept::class, 'payroll_concept_id');
    }

    /**
     * @return BelongsTo<PayrollItem, $this>
     */
    public function appliedItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'applied_payroll_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
