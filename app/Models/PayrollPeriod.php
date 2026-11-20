<?php

namespace App\Models;

use App\Enums\PayrollPeriodStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payroll_schedule_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon $payment_date
 * @property PayrollPeriodStatus $status
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PayrollSchedule $schedule
 */
#[Fillable(['payroll_schedule_id', 'start_date', 'end_date', 'payment_date', 'status', 'closed_at', 'closed_by'])]
class PayrollPeriod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'payment_date' => 'date',
            'status' => PayrollPeriodStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<PayrollPeriod>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', PayrollPeriodStatus::Open);
    }

    /**
     * @return BelongsTo<PayrollSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PayrollSchedule::class, 'payroll_schedule_id');
    }

    /**
     * @return HasMany<PayrollRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
