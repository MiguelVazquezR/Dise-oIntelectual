<?php

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceMethod;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollRunStatus;
use App\Models\AttendanceEvent;
use App\Models\AttendanceIncident;
use App\Models\Branch;
use App\Models\BranchSchedule;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSchedule;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

function dashboardBranch(string $code, string $name): Branch
{
    $branch = Branch::factory()->create([
        'code' => $code,
        'name' => $name,
        'timezone' => 'America/Mexico_City',
    ]);

    $schedule = BranchSchedule::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Horario dashboard',
        'effective_from' => '2025-01-01',
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

function dashboardUser(string $role, array $branchIds = []): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', $role)->value('id'));

    if ($branchIds !== []) {
        $user->branches()->sync($branchIds);
    }

    return $user;
}

function dashboardEmployee(Branch $branch, string $number): Employee
{
    return Employee::factory()->create([
        'branch_id' => $branch->id,
        'employee_number' => $number,
    ]);
}

function dashboardEvent(
    Employee $employee,
    Branch $branch,
    AttendanceEventType $type,
    CarbonImmutable $localMoment,
): void {
    AttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'branch_id' => $branch->id,
        'type' => $type,
        'occurred_at' => $localMoment->utc(),
        'work_date' => $localMoment->toDateString(),
        'method' => AttendanceMethod::Pin,
    ]);
}

function dashboardIncident(
    User $actor,
    Employee $employee,
    IncidentType $type,
    string $date,
    string $quantity,
): AttendanceIncident {
    return AttendanceIncident::query()->create([
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'type' => $type,
        'start_date' => $date,
        'end_date' => $date,
        'quantity' => $quantity,
        'reason' => 'Dato ficticio de prueba',
        'status' => IncidentStatus::Approved,
        'created_by' => $actor->id,
        'decided_by' => $actor->id,
        'decided_at' => now(),
    ]);
}

function dashboardPayrollPeriod(CarbonImmutable $today): PayrollPeriod
{
    $schedule = PayrollSchedule::query()->create([
        'name' => 'Quincenal dashboard',
        'frequency' => 'biweekly',
        'start_weekday' => 1,
        'cutoff_weekday' => 5,
        'payment_offset_days' => 2,
        'is_active' => true,
    ]);

    return PayrollPeriod::query()->create([
        'payroll_schedule_id' => $schedule->id,
        'start_date' => $today->subDays(13)->toDateString(),
        'end_date' => $today->toDateString(),
        'payment_date' => $today->addDays(2)->toDateString(),
        'status' => PayrollPeriodStatus::Calculated,
    ]);
}

function dashboardPayrollRun(PayrollPeriod $period, Branch $branch, string $earnings, string $deductions, string $net): PayrollRun
{
    return PayrollRun::query()->create([
        'payroll_period_id' => $period->id,
        'branch_id' => $branch->id,
        'status' => PayrollRunStatus::Calculated,
        'total_earnings' => $earnings,
        'total_deductions' => $deductions,
        'total_net' => $net,
    ]);
}

test('el dashboard exige el permiso dashboard.view', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
});

test('el dashboard calcula las métricas reales del periodo por defecto', function (): void {
    $branch = dashboardBranch('DASH-A', 'Sucursal Norte');
    $admin = dashboardUser(User::ADMIN_ROLE);
    $today = CarbonImmutable::now('America/Mexico_City')->startOfDay();

    $employee = dashboardEmployee($branch, 'DASH-001');
    $absentEmployee = dashboardEmployee($branch, 'DASH-002');

    dashboardEvent($employee, $branch, AttendanceEventType::ClockIn, $today->setTime(9, 30));
    dashboardEvent($employee, $branch, AttendanceEventType::ClockOut, $today->setTime(21, 0));

    dashboardIncident($admin, $employee, IncidentType::JustifiedAbsence, $today->toDateString(), '1.00');
    dashboardIncident($admin, $absentEmployee, IncidentType::UnjustifiedAbsence, $today->toDateString(), '2.00');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Dashboard')
            ->where('filters.branch_id', null)
            ->where('filters.period_id', null)
            ->where('metrics.active_employees', 2)
            ->where('metrics.late_count', 1)
            ->where('metrics.justified_absences', '1.00')
            ->where('metrics.unjustified_absences', '2.00')
            ->where('metrics.overtime_minutes', 90)
            ->where('metrics.incidents_count', 2)
            ->missing('metrics.payroll_status')
            ->where('payroll.period', null)
            ->where('payroll.status', null)
            ->where('payroll.totals.earnings', '0.00')
            ->where('payroll.totals.deductions', '0.00')
            ->where('payroll.totals.net', '0.00')
            ->has('trend', 14)
            ->where('trend', function ($trend) use ($today): bool {
                $last = $trend[count($trend) - 1];

                return $last['date'] === $today->toDateString()
                    && $last['late_count'] === 1
                    && $last['absences'] === 1;
            })
            ->where('by_branch', fn ($rows): bool => count($rows) === 1
                && $rows[0]['branch']['id'] === $branch->id
                && $rows[0]['employees'] === 2
                && $rows[0]['late_count'] === 1)
            ->has('recent_incidents', 2)
            ->where('options.periods', [])
            ->where('options.branches', fn ($branches): bool => count($branches) === 1
                && $branches[0]['id'] === $branch->id)
            ->where('can.payroll', true)
            ->where('can.incidents', true)
            ->where('can.audit', true));
});

test('el dashboard resume el periodo de pre-nómina cuando existe', function (): void {
    $branch = dashboardBranch('DASH-B', 'Sucursal Sur');
    $admin = dashboardUser(User::ADMIN_ROLE);
    $today = CarbonImmutable::now('America/Mexico_City')->startOfDay();

    $period = dashboardPayrollPeriod($today);
    dashboardPayrollRun($period, $branch, '1000.50', '200.25', '800.25');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Dashboard')
            ->where('filters.period_id', $period->id)
            ->where('payroll.period.id', $period->id)
            ->where('payroll.period.status', 'calculated')
            ->where('payroll.status', 'calculated')
            ->where('payroll.period.start_date', $today->subDays(13)->toDateString())
            ->where('payroll.period.label', fn (string $label): bool => str_starts_with($label, 'Del '))
            ->where('payroll.totals.earnings', '1000.50')
            ->where('payroll.totals.deductions', '200.25')
            ->where('payroll.totals.net', '800.25')
            ->has('options.periods', 1)
            ->where('options.periods.0.id', $period->id));
});

test('el dashboard responde sin datos de nómina y sin colaboradores', function (): void {
    $admin = dashboardUser(User::ADMIN_ROLE);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Dashboard')
            ->where('metrics.active_employees', 0)
            ->where('metrics.late_count', 0)
            ->where('metrics.incidents_count', 0)
            ->where('payroll.period', null)
            ->where('payroll.totals.net', '0.00')
            ->has('by_branch', 0)
            ->has('recent_incidents', 0)
            ->has('trend', 14));
});

test('un encargado de sucursal solo ve las métricas y la nómina de su sucursal', function (): void {
    $north = dashboardBranch('DASH-N', 'Sucursal Norte');
    $south = dashboardBranch('DASH-S', 'Sucursal Sur');
    $today = CarbonImmutable::now('America/Mexico_City')->startOfDay();

    $northEmployee = dashboardEmployee($north, 'DASH-N-01');
    $southEmployee = dashboardEmployee($south, 'DASH-S-01');

    dashboardEvent($northEmployee, $north, AttendanceEventType::ClockIn, $today->setTime(9, 30));
    dashboardEvent($northEmployee, $north, AttendanceEventType::ClockOut, $today->setTime(19, 0));

    dashboardEvent($southEmployee, $south, AttendanceEventType::ClockIn, $today->setTime(9, 0));
    dashboardEvent($southEmployee, $south, AttendanceEventType::ClockOut, $today->setTime(19, 0));

    $period = dashboardPayrollPeriod($today);
    dashboardPayrollRun($period, $north, '100.00', '10.00', '90.00');
    dashboardPayrollRun($period, $south, '900.00', '90.00', '810.00');

    $manager = dashboardUser('branch_manager', [$north->id]);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Dashboard')
            ->where('metrics.active_employees', 1)
            ->where('metrics.late_count', 1)
            ->where('by_branch', fn ($rows): bool => count($rows) === 1
                && $rows[0]['branch']['id'] === $north->id)
            ->where('options.branches', fn ($branches): bool => count($branches) === 1
                && $branches[0]['id'] === $north->id)
            ->where('payroll.totals.earnings', '100.00')
            ->where('payroll.totals.deductions', '10.00')
            ->where('payroll.totals.net', '90.00'));
});
