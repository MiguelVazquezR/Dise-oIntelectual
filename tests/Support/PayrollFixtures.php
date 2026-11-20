<?php

namespace Tests\Support;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceMethod;
use App\Models\AttendanceEvent;
use App\Models\Branch;
use App\Models\BranchSchedule;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\PayrollPeriod;
use App\Models\PayrollSchedule;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Datos ficticios compartidos por las pruebas de pre-nómina (OLA 4-A).
 *
 * Sucursal con horario lunes–sábado 09:00–17:00 (domingo descanso), sin
 * descanso intermedio, para que los minutos programados sean 480 y las horas
 * extra y faltas sean fáciles de verificar.
 */
final class PayrollFixtures
{
    public static function branch(string $code, string $name): Branch
    {
        $branch = Branch::factory()->create([
            'code' => $code,
            'name' => $name,
            'timezone' => 'America/Mexico_City',
        ]);

        $schedule = BranchSchedule::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Horario de prueba '.$code,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $day = $schedule->days()->create([
                'weekday' => $weekday,
                'is_rest_day' => $weekday === 7,
                'late_tolerance_minutes' => 10,
            ]);

            if ($weekday !== 7) {
                $day->segments()->create([
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'kind' => 'work',
                    'sort_order' => 0,
                ]);
            }
        }

        return $branch;
    }

    public static function employee(
        Branch $branch,
        string $employeeNumber,
        string $salary,
        string $frequency = 'weekly',
        string $hireDate = '2025-01-01',
    ): Employee {
        $employee = Employee::factory()->create([
            'branch_id' => $branch->id,
            'employee_number' => $employeeNumber,
            'hire_date' => $hireDate,
            'termination_date' => null,
            'status' => 'active',
        ]);

        EmployeeCompensation::query()->create([
            'employee_id' => $employee->id,
            'base_salary' => $salary,
            'pay_frequency' => $frequency,
            'effective_from' => $hireDate,
            'effective_to' => null,
            'notes' => 'Compensación de prueba (ficticia)',
        ]);

        return $employee;
    }

    public static function schedule(string $frequency, string $name = 'Esquema de prueba'): PayrollSchedule
    {
        return PayrollSchedule::query()->create([
            'name' => $name,
            'frequency' => $frequency,
            'start_weekday' => 1,
            'cutoff_weekday' => 7,
            'payment_offset_days' => 2,
            'is_active' => true,
        ]);
    }

    public static function period(PayrollSchedule $schedule, string $startDate, string $endDate, ?string $paymentDate = null): PayrollPeriod
    {
        return PayrollPeriod::query()->create([
            'payroll_schedule_id' => $schedule->id,
            'start_date' => CarbonImmutable::parse($startDate),
            'end_date' => CarbonImmutable::parse($endDate),
            'payment_date' => CarbonImmutable::parse($paymentDate ?? $endDate)->addDays(2),
        ]);
    }

    /**
     * Registra entrada y salida de un día (fechas en la zona de la sucursal).
     */
    public static function clock(
        Employee $employee,
        Branch $branch,
        string $workDate,
        string $clockIn = '09:00',
        string $clockOut = '17:00',
    ): void {
        foreach ([[AttendanceEventType::ClockIn, $clockIn], [AttendanceEventType::ClockOut, $clockOut]] as [$type, $time]) {
            AttendanceEvent::query()->create([
                'employee_id' => $employee->id,
                'branch_id' => $branch->id,
                'type' => $type,
                'occurred_at' => CarbonImmutable::parse($workDate.' '.$time, 'America/Mexico_City')->utc(),
                'work_date' => $workDate,
                'method' => AttendanceMethod::Pin,
            ]);
        }
    }

    /**
     * Jornada completa lunes–sábado de la semana que inicia en `$weekStart`.
     */
    public static function fullWeek(Employee $employee, Branch $branch, string $weekStart = '2026-11-02'): void
    {
        $monday = CarbonImmutable::parse($weekStart, 'America/Mexico_City');

        for ($offset = 0; $offset <= 5; $offset++) {
            self::clock($employee, $branch, $monday->addDays($offset)->toDateString());
        }
    }

    public static function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', User::ADMIN_ROLE)->value('id'));

        return $user;
    }

    /**
     * @param  array<int, int>  $branchIds
     */
    public static function user(array $branchIds, string $role = 'branch_manager'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->value('id'));
        $user->branches()->sync($branchIds);

        return $user;
    }
}
