<?php

namespace App\Models;

use App\Enums\HolidayType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $date
 * @property HolidayType $type
 * @property int|null $branch_id
 * @property string|null $legal_basis
 * @property string|null $source_url
 * @property Carbon|null $source_checked_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Branch|null $branch
 * @property-read User|null $creator
 */
#[Fillable(['name', 'date', 'type', 'branch_id', 'legal_basis', 'source_url', 'source_checked_at', 'created_by'])]
class Holiday extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => HolidayType::class,
            'source_checked_at' => 'datetime',
        ];
    }

    /**
     * Festivos de un año completo (incluye los globales de todas las sucursales).
     *
     * @param  Builder<Holiday>  $query
     */
    #[Scope]
    protected function forYear(Builder $query, int $year): void
    {
        $query->whereYear('date', $year);
    }

    /**
     * Festivos aplicables a una sucursal: los globales (`branch_id` null) y los propios.
     *
     * @param  Builder<Holiday>  $query
     */
    #[Scope]
    protected function forBranch(Builder $query, ?int $branchId): void
    {
        $query->where(function (Builder $inner) use ($branchId): void {
            $inner->whereNull('branch_id');

            if ($branchId !== null) {
                $inner->orWhere('branch_id', $branchId);
            }
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
