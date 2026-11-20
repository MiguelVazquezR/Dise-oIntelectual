<?php

namespace App\Models;

use App\Enums\PayrollRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payroll_period_id
 * @property int $branch_id
 * @property PayrollRunStatus $status
 * @property string $total_earnings
 * @property string $total_deductions
 * @property string $total_net
 * @property Carbon|null $calculated_at
 * @property int|null $calculated_by
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property array<string, mixed>|null $rules_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PayrollPeriod $period
 * @property-read Branch $branch
 */
#[Fillable([
    'payroll_period_id',
    'branch_id',
    'status',
    'total_earnings',
    'total_deductions',
    'total_net',
    'calculated_at',
    'calculated_by',
    'reviewed_at',
    'reviewed_by',
    'closed_at',
    'closed_by',
    'rules_snapshot',
])]
class PayrollRun extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayrollRunStatus::class,
            'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'calculated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'closed_at' => 'datetime',
            'rules_snapshot' => 'array',
        ];
    }

    /**
     * @param  Builder<PayrollRun>  $query
     */
    #[Scope]
    protected function forBranch(Builder $query, int $branchId): void
    {
        $query->where('branch_id', $branchId);
    }

    /**
     * @param  Builder<PayrollRun>  $query
     */
    #[Scope]
    protected function notClosed(Builder $query): void
    {
        $query->where('status', '!=', PayrollRunStatus::Closed);
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<PayrollItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
