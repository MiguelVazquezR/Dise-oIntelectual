<?php

namespace App\Models;

use App\Enums\PayFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $base_salary
 * @property PayFrequency $pay_frequency
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 */
#[Fillable(['employee_id', 'base_salary', 'pay_frequency', 'effective_from', 'effective_to', 'notes'])]
class EmployeeCompensation extends Model
{
    /**
     * Nombre explícito: el pluralizador de Eloquent no pluraliza "compensation".
     *
     * @var string
     */
    protected $table = 'employee_compensations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'pay_frequency' => PayFrequency::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @param  Builder<EmployeeCompensation>  $query
     */
    #[Scope]
    protected function effectiveOn(Builder $query, Carbon|string $date): void
    {
        $query->where('effective_from', '<=', $date)
            ->where(fn (Builder $inner) => $inner->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
