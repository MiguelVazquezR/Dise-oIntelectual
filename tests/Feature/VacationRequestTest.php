<?php

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\VacationRequestStatus;
use App\Models\AttendanceIncident;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Models\VacationPeriod;
use App\Models\VacationRequest;
use App\Services\Vacations\VacationBalanceService;
use App\Services\Vacations\VacationPeriodGenerator;
use App\Services\Vacations\VacationRequestService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-06-15 09:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function vacationAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', User::ADMIN_ROLE)->value('id'));

    return $user;
}

function vacationEmployee(): Employee
{
    return Employee::factory()->create([
        'hire_date' => '2020-03-10',
        'status' => 'active',
    ]);
}

test('el listado de vacaciones exige el permiso vacations.view', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('vacations.index'))
        ->assertForbidden();
});

test('genera periodos por aniversario de forma idempotente', function (): void {
    $admin = vacationAdmin();
    $employee = vacationEmployee();

    $this->actingAs($admin)
        ->post(route('vacations.periods.generate'), ['year' => 2026])
        ->assertRedirect(route('vacations.index', ['year' => 2026]))
        ->assertSessionHas('success');

    $period = VacationPeriod::query()
        ->where('employee_id', $employee->id)
        ->whereDate('period_start', '2026-03-10')
        ->first();

    expect($period)->not->toBeNull()
        ->and($period->period_end->toDateString())->toBe('2027-03-09')
        ->and($period->anniversary_years)->toBe(6)
        ->and($period->days_granted)->toBe('22.00')
        ->and($period->legal_reference)->toBe('LFT art. 76');

    $count = VacationPeriod::query()->where('employee_id', $employee->id)->count();

    $this->actingAs($admin)
        ->post(route('vacations.periods.generate'), ['year' => 2026])
        ->assertSessionHas('success');

    expect($count)->toBe(1)
        ->and(VacationPeriod::query()->where('employee_id', $employee->id)->count())->toBe($count);
});

test('aprobar una solicitud crea la incidencia de vacaciones aprobada y descuenta el saldo', function (): void {
    $admin = vacationAdmin();
    $employee = vacationEmployee();
    app(VacationPeriodGenerator::class)->forEmployee($employee);

    $this->actingAs($admin)
        ->post(route('vacations.requests.store'), [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'notes' => 'Vacaciones de verano',
        ])
        ->assertRedirect(route('vacations.index', ['year' => 2026]))
        ->assertSessionHas('success');

    $request = VacationRequest::query()->where('employee_id', $employee->id)->firstOrFail();

    expect($request->status)->toBe(VacationRequestStatus::Pending)
        ->and($request->days)->toBe('3.00');

    $this->actingAs($admin)
        ->post(route('vacations.requests.decide', ['vacationRequest' => $request->id]), [
            'decision' => 'approved',
            'notes' => 'Autorizado',
        ])
        ->assertRedirect(route('vacations.index', ['year' => 2026]))
        ->assertSessionHas('success');

    expect($request->fresh()->status)->toBe(VacationRequestStatus::Approved);

    $incident = AttendanceIncident::query()
        ->where('employee_id', $employee->id)
        ->where('type', IncidentType::Vacation->value)
        ->first();

    expect($incident)->not->toBeNull()
        ->and($incident->status)->toBe(IncidentStatus::Approved)
        ->and($incident->start_date->toDateString())->toBe('2026-07-01')
        ->and($incident->end_date->toDateString())->toBe('2026-07-03')
        ->and($incident->quantity)->toBe('3.00')
        ->and($incident->branch_id)->toBe($employee->branch_id);

    $period = $request->fresh()->period;
    $balance = app(VacationBalanceService::class)->forPeriod($period);

    expect($balance['days_taken'])->toBe('3.00')
        ->and($balance['days_available'])->toBe('19.00');

    $this->assertDatabaseHas('audit_logs', ['action' => 'vacation.request.approved']);
});

test('rechazar una solicitud no crea la incidencia de vacaciones', function (): void {
    $admin = vacationAdmin();
    $employee = vacationEmployee();
    app(VacationPeriodGenerator::class)->forEmployee($employee);

    $request = app(VacationRequestService::class)->request($employee, [
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-04',
    ], $admin);

    $this->actingAs($admin)
        ->post(route('vacations.requests.decide', ['vacationRequest' => $request->id]), [
            'decision' => 'rejected',
            'notes' => 'Temporada alta',
        ])
        ->assertRedirect(route('vacations.index', ['year' => 2026]))
        ->assertSessionHas('success');

    expect($request->fresh()->status)->toBe(VacationRequestStatus::Rejected);

    $incidentExists = AttendanceIncident::query()
        ->where('employee_id', $employee->id)
        ->where('type', IncidentType::Vacation->value)
        ->whereDate('start_date', '2026-08-03')
        ->whereDate('end_date', '2026-08-04')
        ->exists();

    expect($incidentExists)->toBeFalse();

    $this->assertDatabaseHas('audit_logs', ['action' => 'vacation.request.rejected']);
});

test('rechaza una solicitud sin saldo suficiente', function (): void {
    $admin = vacationAdmin();
    $employee = vacationEmployee();

    VacationPeriod::query()->create([
        'employee_id' => $employee->id,
        'period_start' => '2026-03-10',
        'period_end' => '2027-03-09',
        'anniversary_years' => 6,
        'days_granted' => 1,
        'days_adjustment' => 0,
        'legal_reference' => 'LFT art. 76',
    ]);

    $this->actingAs($admin)
        ->from(route('vacations.index', ['year' => 2026]))
        ->post(route('vacations.requests.store'), [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
        ])
        ->assertSessionHasErrors('start_date');

    $this->assertDatabaseCount('vacation_requests', 0);
});

test('rechaza una solicitud solapada con otra aprobada', function (): void {
    $admin = vacationAdmin();
    $employee = vacationEmployee();
    app(VacationPeriodGenerator::class)->forEmployee($employee);

    $service = app(VacationRequestService::class);
    $first = $service->request($employee, [
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-03',
    ], $admin);

    $service->approve($first, $admin);

    $this->actingAs($admin)
        ->from(route('vacations.index', ['year' => 2026]))
        ->post(route('vacations.requests.store'), [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-03',
            'end_date' => '2026-07-06',
        ])
        ->assertSessionHasErrors('start_date');

    expect(VacationRequest::query()->where('employee_id', $employee->id)->count())->toBe(1);
});

test('el listado de vacaciones muestra periodos con saldo y solicitudes', function (): void {
    $admin = vacationAdmin();
    $employee = vacationEmployee();
    app(VacationPeriodGenerator::class)->forEmployee($employee);

    $service = app(VacationRequestService::class);
    $approved = $service->request($employee, [
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-03',
    ], $admin);
    $service->approve($approved, $admin);

    $this->actingAs($admin)
        ->get(route('vacations.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('vacations/Index')
            ->where('filters.year', 2026)
            ->where('periods.meta.total', 1)
            ->where('periods.data.0.days_taken', '3.00')
            ->where('periods.data.0.days_available', '19.00')
            ->where('periods.data.0.status', 'current')
            ->where('requests.meta.total', 1)
            ->where('requests.data.0.status', 'approved')
            ->where('can.manage', true)
            ->where('can.approve', true)
            ->has('rules.table', 11));
});

test('aísla los periodos y solicitudes por sucursal del usuario', function (): void {
    $own = Branch::factory()->create(['code' => 'PROPIA']);
    $other = Branch::factory()->create(['code' => 'AJENA']);
    $ownEmployee = Employee::factory()->create(['branch_id' => $own->id, 'hire_date' => '2020-03-10']);
    Employee::factory()->create(['branch_id' => $other->id, 'hire_date' => '2020-03-10']);

    app(VacationPeriodGenerator::class)->forYear(2026);

    $manager = User::factory()->create();
    $manager->roles()->attach(Role::query()->where('slug', 'branch_manager')->value('id'));
    $manager->branches()->sync([$own->id]);

    $this->actingAs($manager)
        ->get(route('vacations.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('vacations/Index')
            ->where('periods.meta.total', 1)
            ->where('periods.data.0.employee.id', $ownEmployee->id)
            ->where('can.manage', true)
            ->where('can.approve', true));

    $this->actingAs($manager)
        ->get(route('vacations.index', ['year' => 2026, 'branch_id' => $other->id]))
        ->assertForbidden();
});

test('mi vacaciones lista los periodos del colaborador vinculado', function (): void {
    $user = User::factory()->create();
    $employee = vacationEmployee();
    $employee->update(['user_id' => $user->id]);

    app(VacationPeriodGenerator::class)->forEmployee($employee);

    $this->actingAs($user)
        ->get(route('my.vacations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('my/Vacations')
            ->where('linked', true)
            ->where('employee.id', $employee->id)
            ->where('employee.hire_date', '2020-03-10')
            ->where('period.from', '2021-03-10')
            ->where('period.to', '2027-03-09')
            ->has('periods', 6)
            ->where('totals.days_granted', '102.00')
            ->where('totals.days_taken', '0.00'));
});

test('mi vacaciones muestra un aviso cuando la cuenta no está vinculada a un colaborador', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('my.vacations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('my/Vacations')
            ->where('linked', false)
            ->where('employee', null)
            ->has('periods', 0)
            ->where('notice', fn (string $notice): bool => str_contains($notice, 'no está vinculada')));
});
