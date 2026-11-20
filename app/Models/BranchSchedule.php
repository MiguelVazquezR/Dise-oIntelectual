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
 * @property int $branch_id
 * @property string $name
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Branch $branch
 */
#[Fillable(['branch_id', 'name', 'effective_from', 'effective_to', 'is_active'])]
class BranchSchedule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<BranchSchedule>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<BranchSchedule>  $query
     */
    #[Scope]
    protected function forDate(Builder $query, Carbon|string $date): void
    {
        $query->where('effective_from', '<=', $date)
            ->where(fn (Builder $inner) => $inner->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<BranchScheduleDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(BranchScheduleDay::class)->orderBy('weekday');
    }
}
