<?php

namespace App\Services\Attendance;

use App\Models\Employee;

/**
 * Traduce un resultado del motor de jornada a la fila del contrato §7.3.
 */
final class AttendanceRowPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromResult(AttendanceDayResult $result, Employee $employee): array
    {
        $employee->loadMissing('branch');

        return [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
            ],
            'branch' => [
                'id' => $employee->branch->id,
                'name' => $employee->branch->name,
                'timezone' => $employee->branch->timezone,
            ],
            ...$result->toArray(),
        ];
    }
}
