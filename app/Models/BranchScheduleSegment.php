<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_schedule_day_id
 * @property string $start_time
 * @property string $end_time
 * @property string $kind
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BranchScheduleDay $day
 */
#[Fillable(['branch_schedule_day_id', 'start_time', 'end_time', 'kind', 'sort_order'])]
class BranchScheduleSegment extends Model
{
    public const KIND_WORK = 'work';

    public const KIND_BREAK = 'break';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BranchScheduleDay, $this>
     */
    public function day(): BelongsTo
    {
        return $this->belongsTo(BranchScheduleDay::class, 'branch_schedule_day_id');
    }
}
