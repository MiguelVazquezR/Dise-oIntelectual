<?php

namespace App\Models;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
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
 * @property AttendanceEventType $type
 * @property Carbon $occurred_at
 * @property Carbon $work_date
 * @property AttendanceMethod $method
 * @property string|null $gps_latitude
 * @property string|null $gps_longitude
 * @property string|null $gps_accuracy_m
 * @property AttendanceStatus $validation_status
 * @property string|null $validation_notes
 * @property string|null $ip_address
 * @property string|null $device_label
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Branch $branch
 */
#[Fillable([
    'employee_id',
    'branch_id',
    'type',
    'occurred_at',
    'work_date',
    'method',
    'gps_latitude',
    'gps_longitude',
    'gps_accuracy_m',
    'validation_status',
    'validation_notes',
    'ip_address',
    'device_label',
    'metadata',
])]
class AttendanceEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttendanceEventType::class,
            'method' => AttendanceMethod::class,
            'validation_status' => AttendanceStatus::class,
            'occurred_at' => 'datetime',
            'work_date' => 'date',
            'gps_latitude' => 'decimal:7',
            'gps_longitude' => 'decimal:7',
            'gps_accuracy_m' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * Filtra por rango de fechas laborables (columnas `date`).
     *
     * @param  Builder<AttendanceEvent>  $query
     */
    #[Scope]
    protected function forPeriod(Builder $query, Carbon|string $from, Carbon|string $to): void
    {
        $query->whereBetween('work_date', [$from, $to]);
    }

    /**
     * @param  Builder<AttendanceEvent>  $query
     */
    #[Scope]
    protected function forEmployee(Builder $query, int $employeeId): void
    {
        $query->where('employee_id', $employeeId);
    }

    /**
     * @param  Builder<AttendanceEvent>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, AttendanceEventType $type): void
    {
        $query->where('type', $type);
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
}
