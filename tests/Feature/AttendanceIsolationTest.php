<?php

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceMethod;
use App\Models\AttendanceEvent;
use App\Models\Branch;
use App\Models\BranchSchedule;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

function attendanceIsolationBranch(string $code): Branch
{
    $branch = Branch::factory()->create([
        'code' => $code,
        'name' => 'Sucursal '.$code,
        'timezone' => 'America/Mexico_City',
    ]);

    $schedule = BranchSchedule::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Horario de aislamiento',
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
                'end_time' => '19:00',
                'kind' => 'work',
                'sort_order' => 0,
            ]);
        }
    }

    return $branch;
}

function attendanceIsolationEmployee(Branch $branch, string $employeeNumber): Employee
{
    $employee = Employee::factory()->create([
        'branch_id' => $branch->id,
        'employee_number' => $employeeNumber,
    ]);

    $date = CarbonImmutable::parse('2026-11-02', 'America/Mexico_City');

    $events = [
        [AttendanceEventType::ClockIn, 9],
        [AttendanceEventType::ClockOut, 19],
    ];

    foreach ($events as [$type, $hour]) {
        AttendanceEvent::query()->create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'type' => $type,
            'occurred_at' => $date->setTime($hour, 0)->utc(),
            'work_date' => '2026-11-02',
            'method' => AttendanceMethod::Pin,
        ]);
    }

    return $employee;
}

/**
 * @param  array<int, int>  $branchIds
 */
function attendanceIsolationUser(array $branchIds, string $role = 'branch_manager'): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', $role)->value('id'));
    $user->branches()->sync($branchIds);

    return $user;
}

test('un usuario de sucursal solo ve las asistencias de su sucursal', function (): void {
    $north = attendanceIsolationBranch('ISO-NORTE');
    $south = attendanceIsolationBranch('ISO-SUR');
    attendanceIsolationEmployee($north, 'EMP-NORTH-01');
    attendanceIsolationEmployee($south, 'EMP-SOUTH-01');

    $manager = attendanceIsolationUser([$north->id]);

    $this->actingAs($manager)
        ->get(route('attendance.index', ['from' => '2026-11-02', 'to' => '2026-11-02']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('attendance/Index')
            ->where('filters.from', '2026-11-02')
            ->where('filters.to', '2026-11-02')
            ->where('summary.employees', 1)
            ->where('summary.worked_minutes', 600)
            ->where('rows.meta.total', 1)
            ->where('rows.data', fn ($rows): bool => count($rows) === 1
                && $rows[0]['employee']['employee_number'] === 'EMP-NORTH-01'
                && $rows[0]['status'] === 'complete')
            ->where('options.branches', fn ($branches): bool => count($branches) === 1
                && $branches[0]['id'] === $north->id)
            ->where('can.manage', true)
            ->where('can.incidents', true));
});

test('un usuario con data.all_branches ve todas las sucursales', function (): void {
    $north = attendanceIsolationBranch('ISO-NORTE');
    $south = attendanceIsolationBranch('ISO-SUR');
    attendanceIsolationEmployee($north, 'EMP-NORTH-01');
    attendanceIsolationEmployee($south, 'EMP-SOUTH-01');

    $admin = attendanceIsolationUser([], User::ADMIN_ROLE);

    $this->actingAs($admin)
        ->get(route('attendance.index', ['from' => '2026-11-02', 'to' => '2026-11-02']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('summary.employees', 2)
            ->where('rows.meta.total', 2));
});

test('no puede abrir la ficha de un colaborador de otra sucursal', function (): void {
    $north = attendanceIsolationBranch('ISO-NORTE');
    $south = attendanceIsolationBranch('ISO-SUR');
    attendanceIsolationEmployee($north, 'EMP-NORTH-01');
    $southEmployee = attendanceIsolationEmployee($south, 'EMP-SOUTH-01');

    $manager = attendanceIsolationUser([$north->id]);

    $this->actingAs($manager)
        ->get('/attendance/employee/'.$southEmployee->id)
        ->assertForbidden();
});

test('el checador exige el permiso de registro', function (): void {
    $branch = attendanceIsolationBranch('ISO-NORTE');
    $viewer = attendanceIsolationUser([$branch->id], 'viewer');

    $this->actingAs($viewer)->get(route('kiosk.index'))->assertForbidden();
    $this->actingAs($viewer)
        ->post(route('kiosk.pin'), [
            'employee_number' => 'EMP-NORTH-01',
            'pin' => '1234',
            'branch_id' => $branch->id,
            'event_type' => 'clock_in',
        ])
        ->assertForbidden();
});

test('un colaborador sin cuenta vinculada ve «Mi asistencia» vacía', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('my.attendance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('my/Attendance')
            ->where('employee', null)
            ->has('days', 0)
            ->where('totals.worked_minutes', 0));
});

test('un colaborador vinculado solo consulta su propia asistencia', function (): void {
    $branch = attendanceIsolationBranch('ISO-NORTE');
    $employee = attendanceIsolationEmployee($branch, 'EMP-NORTH-01');
    attendanceIsolationEmployee($branch, 'EMP-NORTH-02');

    $user = User::factory()->create();
    $employee->update(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('my.attendance', ['from' => '2026-11-02', 'to' => '2026-11-02']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('my/Attendance')
            ->where('employee.id', $employee->id)
            ->where('days', fn ($days): bool => count($days) === 1
                && $days[0]['employee']['employee_number'] === 'EMP-NORTH-01')
            ->where('totals.worked_minutes', 600));
});
