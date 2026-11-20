<?php

namespace App\Models;

use App\Enums\PayrollScheduleFrequency;
use App\Enums\Weekday;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property PayrollScheduleFrequency $frequency
 * @property Weekday $start_weekday
 * @property Weekday $cutoff_weekday
 * @property int $payment_offset_days
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'frequency', 'start_weekday', 'cutoff_weekday', 'payment_offset_days', 'is_active'])]
class PayrollSchedule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => PayrollScheduleFrequency::class,
            'start_weekday' => Weekday::class,
            'cutoff_weekday' => Weekday::class,
            'payment_offset_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<PayrollSchedule>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<PayrollPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(PayrollPeriod::class);
    }
}
