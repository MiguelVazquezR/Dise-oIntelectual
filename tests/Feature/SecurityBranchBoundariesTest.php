<?php

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollRunStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSchedule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\VacationPeriod;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

function securityBranch(string $code, string $name): Branch
{
    return Branch::factory()->create([
        'code' => $code,
        'name' => $name,
        'timezone' => 'America/Mexico_City',
    ]);
}

/**
 * Usuario con un rol propio que solo tiene los permisos indicados (sin
 * `data.all_branches`) y una sucursal asignada.
 *
 * @param  array<int, string>  $permissionSlugs
 * @param  array<int, int>  $branchIds
 */
function securityScopedUser(array $permissionSlugs, array $branchIds): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'security_scoped'],
        [
            'name' => 'Rol acotado de seguridad',
            'description' => 'Rol de prueba con permisos de nómina y alcance de sucursal',
            'is_system' => false,
        ],
    );

    $role->permissions()->sync(
        Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id')->all(),
    );

    $user = User::factory()->create();
    $user->roles()->attach($role->id);
    $user->branches()->sync($branchIds);

    return $user;
}

function securityPayrollPeriod(PayrollPeriodStatus $status = PayrollPeriodStatus::Calculated): PayrollPeriod
{
    $schedule = PayrollSchedule::query()->create([
        'name' => 'Esquema de seguridad',
        'frequency' => 'weekly',
        'start_weekday' => 1,
        'cutoff_weekday' => 7,
        'payment_offset_days' => 2,
        'is_active' => true,
    ]);

    return PayrollPeriod::query()->create([
        'payroll_schedule_id' => $schedule->id,
        'start_date' => '2026-11-02',
        'end_date' => '2026-11-08',
        'payment_date' => '2026-11-10',
        'status' => $status,
    ]);
}

function securityPayrollRun(PayrollPeriod $period, Branch $branch): PayrollRun
{
    return PayrollRun::query()->create([
        'payroll_period_id' => $period->id,
        'branch_id' => $branch->id,
        'status' => PayrollRunStatus::Calculated,
        'total_earnings' => '1000.00',
        'total_deductions' => '100.00',
        'total_net' => '900.00',
    ]);
}

test('un revisor acotado no puede revisar un periodo con corridas de otra sucursal', function (): void {
    $north = securityBranch('SEC-N', 'Sucursal Norte');
    $south = securityBranch('SEC-S', 'Sucursal Sur');
    $period = securityPayrollPeriod();
    securityPayrollRun($period, $north);
    securityPayrollRun($period, $south);

    $reviewer = securityScopedUser(['payroll.view', 'payroll.review'], [$north->id]);

    $this->actingAs($reviewer)
        ->post(route('payroll.periods.review', $period))
        ->assertForbidden();

    expect($period->refresh()->status)->toBe(PayrollPeriodStatus::Calculated);
});

test('un revisor acotado no puede cerrar un periodo con corridas de otra sucursal', function (): void {
    $north = securityBranch('SEC-N2', 'Sucursal Norte');
    $south = securityBranch('SEC-S2', 'Sucursal Sur');
    $period = securityPayrollPeriod(PayrollPeriodStatus::Reviewed);
    securityPayrollRun($period, $north);
    securityPayrollRun($period, $south);

    $closer = securityScopedUser(['payroll.view', 'payroll.close'], [$north->id]);

    $this->actingAs($closer)
        ->post(route('payroll.periods.close', $period), ['confirm' => true])
        ->assertForbidden();

    expect($period->refresh()->status)->toBe(PayrollPeriodStatus::Reviewed);
});

test('un revisor acotado sí puede revisar y cerrar un periodo de su sucursal', function (): void {
    $north = securityBranch('SEC-N3', 'Sucursal Norte');
    $period = securityPayrollPeriod();
    securityPayrollRun($period, $north);

    $reviewer = securityScopedUser(['payroll.view', 'payroll.review', 'payroll.close'], [$north->id]);

    $this->actingAs($reviewer)
        ->post(route('payroll.periods.review', $period))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($period->refresh()->status)->toBe(PayrollPeriodStatus::Reviewed);

    $this->actingAs($reviewer)
        ->post(route('payroll.periods.close', $period), ['confirm' => true])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($period->refresh()->status)->toBe(PayrollPeriodStatus::Closed);
});

test('el dashboard solo ofrece periodos con corridas de sucursales visibles', function (): void {
    $north = securityBranch('SEC-N4', 'Sucursal Norte');
    $south = securityBranch('SEC-S4', 'Sucursal Sur');

    $ownPeriod = securityPayrollPeriod();
    securityPayrollRun($ownPeriod, $north);

    $foreignPeriod = securityPayrollPeriod();
    securityPayrollRun($foreignPeriod, $south);

    $manager = User::factory()->create();
    $manager->roles()->attach(Role::query()->where('slug', 'branch_manager')->value('id'));
    $manager->branches()->sync([$north->id]);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('options.periods', fn ($periods): bool => count($periods) === 1
                && collect($periods)->first()['id'] === $ownPeriod->id));
});

test('«Mis vacaciones» solo muestra los periodos del colaborador vinculado', function (): void {
    $branch = securityBranch('SEC-MV', 'Sucursal Matriz');

    $ownEmployee = Employee::factory()->create(['branch_id' => $branch->id, 'employee_number' => 'SEC-MV-01']);
    $otherEmployee = Employee::factory()->create(['branch_id' => $branch->id, 'employee_number' => 'SEC-MV-02']);

    foreach ([$ownEmployee, $otherEmployee] as $employee) {
        VacationPeriod::query()->create([
            'employee_id' => $employee->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'anniversary_years' => 1,
            'days_granted' => '12.00',
            'days_adjustment' => '0.00',
        ]);
    }

    $user = User::factory()->create();
    $ownEmployee->update(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('my.vacations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('my/Vacations')
            ->where('employee.id', $ownEmployee->id)
            ->has('periods', 1)
            ->where('periods.0.id', VacationPeriod::query()->where('employee_id', $ownEmployee->id)->value('id')));
});

test('el listado de pre-nómina no expone importes de corridas de otra sucursal', function (): void {
    $north = securityBranch('SEC-N5', 'Sucursal Norte');
    $south = securityBranch('SEC-S5', 'Sucursal Sur');
    $period = securityPayrollPeriod();
    securityPayrollRun($period, $north);
    securityPayrollRun($period, $south);

    $manager = User::factory()->create();
    $manager->roles()->attach(Role::query()->where('slug', 'branch_manager')->value('id'));
    $manager->branches()->sync([$north->id]);

    $this->actingAs($manager)
        ->get(route('payroll.periods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('periods.data.0.runs_count', 1)
            ->where('periods.data.0.totals.earnings', '1000.00')
            ->where('periods.data.0.totals.net', '900.00'));

    // La ficha del periodo solo contiene la corrida visible.
    $this->actingAs($manager)
        ->get(route('payroll.periods.show', $period))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('runs', 1)
            ->where('runs.0.branch.id', $north->id)
            ->where('totals.earnings', '1000.00'));
});

test('el dashboard no refleja importes de periodos de otra sucursal', function (): void {
    $north = securityBranch('SEC-N6', 'Sucursal Norte');
    $south = securityBranch('SEC-S6', 'Sucursal Sur');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-08 18:00:00', 'America/Mexico_City'));

    $period = securityPayrollPeriod();
    securityPayrollRun($period, $south);

    $manager = User::factory()->create();
    $manager->roles()->attach(Role::query()->where('slug', 'branch_manager')->value('id'));
    $manager->branches()->sync([$north->id]);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('payroll.totals.earnings', '0.00')
            ->where('payroll.totals.deductions', '0.00')
            ->where('payroll.totals.net', '0.00'));

    CarbonImmutable::setTestNow();
});
