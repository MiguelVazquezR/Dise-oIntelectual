<?php

namespace App\Services\Vacations;

use App\Models\Employee;
use App\Models\VacationPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Generación de periodos de vacaciones por año laboral (aniversario de ingreso,
 * D-11).
 *
 * Cada periodo va del aniversario al día anterior al siguiente aniversario. El
 * periodo solo se crea cuando el aniversario ya se cumplió. La llave única
 * `(employee_id, period_start)` y `firstOrCreate` hacen la operación
 * idempotente y protegen los ajustes autorizados (`days_adjustment`) de
 * cualquier sobrescritura.
 */
final class VacationPeriodGenerator
{
    /**
     * Genera los periodos devengados del colaborador.
     *
     * @return array{employee_id: int, year: int|null, created: int, skipped: int, periods: array<int, array<string, mixed>>}
     */
    public function forEmployee(Employee $employee, ?int $onlyYear = null): array
    {
        $result = $this->generate([$employee], $onlyYear);

        return [
            'employee_id' => $employee->id,
            'year' => $onlyYear,
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'periods' => $result['periods'],
        ];
    }

    /**
     * Genera los periodos cuyo aniversario cae en el año indicado.
     *
     * @param  array<int, int>|null  $branchIds  `null` = todas las sucursales
     * @return array{year: int, created: int, skipped: int, employees: int, periods: array<int, array<string, mixed>>}
     */
    public function forYear(int $year, ?array $branchIds = null): array
    {
        $employees = Employee::query()
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
            ->orderBy('employee_number')
            ->get();

        $result = $this->generate($employees, $year);

        return [
            'year' => $year,
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'employees' => $employees->count(),
            'periods' => $result['periods'],
        ];
    }

    /**
     * @param  iterable<int, Employee>  $employees
     * @return array{created: int, skipped: int, periods: array<int, array<string, mixed>>}
     */
    private function generate(iterable $employees, ?int $onlyYear): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $created = 0;
        $skipped = 0;
        $periods = [];

        DB::transaction(function () use ($employees, $onlyYear, $today, &$created, &$skipped, &$periods): void {
            foreach ($employees as $employee) {
                foreach ($this->anniversaryYears($employee, $today, $onlyYear) as $anniversaryYear) {
                    $period = $this->store($employee, $anniversaryYear);

                    if (! $period instanceof VacationPeriod) {
                        continue;
                    }

                    if ($period->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $skipped++;
                    }

                    $periods[] = $this->row($period);
                }
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'periods' => $periods,
        ];
    }

    /**
     * Años de aniversario a generar para un colaborador.
     *
     * @return array<int, int>
     */
    private function anniversaryYears(Employee $employee, CarbonImmutable $today, ?int $onlyYear): array
    {
        $hireDate = $employee->hire_date->toImmutable()->startOfDay();
        $firstYear = $hireDate->year + 1;

        if ($firstYear > $today->year) {
            return [];
        }

        $lastYear = $today->year;
        $terminationDate = $employee->termination_date?->toImmutable()->startOfDay();

        if ($terminationDate instanceof CarbonImmutable) {
            $lastYear = min($lastYear, (int) $terminationDate->year);
        }

        if ($onlyYear !== null) {
            if ($onlyYear < $firstYear || $onlyYear > $lastYear) {
                return [];
            }

            $firstYear = $onlyYear;
            $lastYear = $onlyYear;
        }

        $years = [];

        for ($anniversaryYear = $firstYear; $anniversaryYear <= $lastYear; $anniversaryYear++) {
            $anniversary = $hireDate->addYearsNoOverflow($anniversaryYear - $hireDate->year);

            if ($anniversary->greaterThan($today)) {
                continue;
            }

            if ($terminationDate instanceof CarbonImmutable && $anniversary->greaterThan($terminationDate)) {
                continue;
            }

            $years[] = $anniversaryYear;
        }

        return $years;
    }

    private function store(Employee $employee, int $anniversaryYear): ?VacationPeriod
    {
        $hireDate = $employee->hire_date->toImmutable()->startOfDay();
        $completedYears = $anniversaryYear - $hireDate->year;

        if ($completedYears < 1) {
            return null;
        }

        $periodStart = $hireDate->addYearsNoOverflow($completedYears);
        $periodEnd = $periodStart->addYearNoOverflow()->subDay();

        $existing = VacationPeriod::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->first();

        if ($existing instanceof VacationPeriod) {
            return $existing;
        }

        return VacationPeriod::query()->create([
            'employee_id' => $employee->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'anniversary_years' => $completedYears,
            'days_granted' => VacationAccrualRules::daysForYears($completedYears),
            'days_adjustment' => 0,
            'legal_reference' => VacationAccrualRules::LEGAL_BASIS,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(VacationPeriod $period): array
    {
        return [
            'id' => $period->id,
            'employee_id' => $period->employee_id,
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
            'anniversary_years' => $period->anniversary_years,
            'days_granted' => $period->days_granted,
            'days_adjustment' => $period->days_adjustment,
        ];
    }
}
