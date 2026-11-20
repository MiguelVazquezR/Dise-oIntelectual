<?php

namespace App\Models;

use App\Enums\Weekday;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_schedule_id
 * @property Weekday $weekday
 * @property bool $is_rest_day
 * @property int $late_tolerance_minutes
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BranchSchedule $schedule
 */
#[Fillable(['branch_schedule_id', 'weekday', 'is_rest_day', 'late_tolerance_minutes', 'notes'])]
class BranchScheduleDay extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
            'is_rest_day' => 'boolean',
            'late_tolerance_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BranchSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(BranchSchedule::class, 'branch_schedule_id');
    }

    /**
     * @return HasMany<BranchScheduleSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(BranchScheduleSegment::class)->orderBy('sort_order');
    }
}
