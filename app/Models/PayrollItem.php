<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property string $base_amount
 * @property string $overtime_amount
 * @property string $bonus_amount
 * @property string $adjustment_amount
 * @property string $absence_deduction
 * @property int $late_minutes
 * @property string $absence_days
 * @property int $overtime_minutes
 * @property string $net_amount
 * @property array<string, mixed>|null $snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PayrollRun $run
 * @property-read Employee $employee
 */
#[Fillable([
    'payroll_run_id',
    'employee_id',
    'base_amount',
    'overtime_amount',
    'bonus_amount',
    'adjustment_amount',
    'absence_deduction',
    'late_minutes',
    'absence_days',
    'overtime_minutes',
    'net_amount',
    'snapshot',
])]
class PayrollItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'absence_deduction' => 'decimal:2',
            'late_minutes' => 'integer',
            'absence_days' => 'decimal:2',
            'overtime_minutes' => 'integer',
            'net_amount' => 'decimal:2',
            'snapshot' => 'array',
        ];
    }

    /**
     * @param  Builder<PayrollItem>  $query
     */
    #[Scope]
    protected function forEmployee(Builder $query, int $employeeId): void
    {
        $query->where('employee_id', $employeeId);
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<PayrollItemConcept, $this>
     */
    public function concepts(): HasMany
    {
        return $this->hasMany(PayrollItemConcept::class);
    }
}
