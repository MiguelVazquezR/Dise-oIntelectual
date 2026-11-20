<?php

namespace App\Support\Employees;

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use Carbon\CarbonImmutable;

/**
 * Registra compensaciones conservando historial.
 *
 * - Si ya existe una compensación vigente con la misma fecha de inicio, se
 *   actualiza en lugar de duplicarla.
 * - Si la nueva vigencia es posterior, la compensación abierta se cierra el día
 *   anterior.
 * - Si la nueva vigencia es anterior a la abierta, el registro nuevo nace
 *   cerrado justo antes de la vigente.
 */
final class CompensationRecorder
{
    /**
     * @param  array{base_salary: string|int, pay_frequency: string, effective_from: string, notes?: string|null}  $data
     */
    public static function record(Employee $employee, array $data): EmployeeCompensation
    {
        $effectiveFrom = $data['effective_from'];

        $open = $employee->compensations()
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->first();

        if ($open instanceof EmployeeCompensation) {
            $openFrom = $open->effective_from->toDateString();

            if ($openFrom === $effectiveFrom) {
                $open->update([
                    'base_salary' => $data['base_salary'],
                    'pay_frequency' => $data['pay_frequency'],
                    'notes' => $data['notes'] ?? $open->notes,
                ]);

                return $open;
            }

            if ($openFrom < $effectiveFrom) {
                $open->update([
                    'effective_to' => CarbonImmutable::parse($effectiveFrom)->subDay()->toDateString(),
                ]);
            }
        }

        $effectiveTo = null;

        if ($open instanceof EmployeeCompensation && $open->effective_from->toDateString() > $effectiveFrom) {
            $effectiveTo = CarbonImmutable::parse($open->effective_from->toDateString())->subDay()->toDateString();
        }

        return $employee->compensations()->create([
            'base_salary' => $data['base_salary'],
            'pay_frequency' => $data['pay_frequency'],
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
