<?php

namespace App\Models;

use App\Enums\VacationRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int|null $vacation_period_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $days
 * @property VacationRequestStatus $status
 * @property int $requested_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read VacationPeriod|null $period
 */
#[Fillable([
    'employee_id',
    'vacation_period_id',
    'start_date',
    'end_date',
    'days',
    'status',
    'requested_by',
    'decided_by',
    'decided_at',
    'notes',
])]
class VacationRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'decimal:2',
            'status' => VacationRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<VacationRequest>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', VacationRequestStatus::Pending);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<VacationPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(VacationPeriod::class, 'vacation_period_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
