<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $branch_id
 * @property IncidentType $type
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $quantity
 * @property string|null $reason
 * @property string|null $evidence_path
 * @property IncidentStatus $status
 * @property int $created_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Branch $branch
 * @property-read User|null $decider
 */
#[Fillable([
    'employee_id',
    'branch_id',
    'type',
    'start_date',
    'end_date',
    'quantity',
    'reason',
    'evidence_path',
    'status',
    'created_by',
    'decided_by',
    'decided_at',
    'notes',
])]
class AttendanceIncident extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'status' => IncidentStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'quantity' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<AttendanceIncident>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', IncidentStatus::Pending);
    }

    /**
     * Incidencias que se solapan con un rango de fechas laborables.
     *
     * Usa `whereDate` porque SQLite guarda las columnas `date` como
     * `Y-m-d 00:00:00`; comparar cadenas crudas excluía los días límite
     * (por ejemplo una incidencia que inicia el mismo día consultado).
     *
     * @param  Builder<AttendanceIncident>  $query
     */
    #[Scope]
    protected function overlapping(Builder $query, Carbon|string $from, Carbon|string $to): void
    {
        $query->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
