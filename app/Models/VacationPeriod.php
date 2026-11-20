<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int $anniversary_years
 * @property string $days_granted
 * @property string $days_adjustment
 * @property string|null $legal_reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 */
#[Fillable(['employee_id', 'period_start', 'period_end', 'anniversary_years', 'days_granted', 'days_adjustment', 'legal_reference'])]
class VacationPeriod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'anniversary_years' => 'integer',
            'days_granted' => 'decimal:2',
            'days_adjustment' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<VacationRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(VacationRequest::class);
    }
}
