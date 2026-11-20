<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $employee_number
 * @property string $first_name
 * @property string $last_name
 * @property string|null $second_last_name
 * @property int $branch_id
 * @property string $position
 * @property string|null $department
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon $hire_date
 * @property Carbon|null $termination_date
 * @property EmployeeStatus $status
 * @property string|null $pin_hash
 * @property Carbon|null $pin_updated_at
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $full_name
 * @property-read Branch $branch
 * @property-read User|null $user
 */
#[Fillable([
    'employee_number',
    'first_name',
    'last_name',
    'second_last_name',
    'branch_id',
    'position',
    'department',
    'email',
    'phone',
    'hire_date',
    'termination_date',
    'status',
    'pin_updated_at',
    'user_id',
])]
#[Hidden(['pin_hash'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'termination_date' => 'date',
            'status' => EmployeeStatus::class,
            'pin_updated_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', EmployeeStatus::Active);
    }

    /**
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function forBranch(Builder $query, int $branchId): void
    {
        $query->where('branch_id', $branchId);
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->last_name, $this->second_last_name])));
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<EmployeeCompensation, $this>
     */
    public function compensations(): HasMany
    {
        return $this->hasMany(EmployeeCompensation::class);
    }

    /**
     * @return HasMany<AttendanceEvent, $this>
     */
    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    /**
     * @return HasMany<AttendanceIncident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(AttendanceIncident::class);
    }

    /**
     * @return HasMany<VacationPeriod, $this>
     */
    public function vacationPeriods(): HasMany
    {
        return $this->hasMany(VacationPeriod::class);
    }

    /**
     * @return HasMany<VacationRequest, $this>
     */
    public function vacationRequests(): HasMany
    {
        return $this->hasMany(VacationRequest::class);
    }

    /**
     * @return HasMany<EmployeePayrollAdjustment, $this>
     */
    public function payrollAdjustments(): HasMany
    {
        return $this->hasMany(EmployeePayrollAdjustment::class);
    }

    /**
     * @return HasMany<PayrollItem, $this>
     */
    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * @return HasOne<BiometricEnrollment, $this>
     */
    public function biometricEnrollment(): HasOne
    {
        return $this->hasOne(BiometricEnrollment::class);
    }
}
