<?php

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\AttendanceIncident;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

function incidentBranch(string $code = 'INC-A'): Branch
{
    return Branch::factory()->create(['code' => $code, 'name' => 'Sucursal '.$code]);
}

function incidentEmployee(Branch $branch, string $employeeNumber = 'EMP-INC-01'): Employee
{
    return Employee::factory()->create([
        'branch_id' => $branch->id,
        'employee_number' => $employeeNumber,
    ]);
}

/**
 * @param  array<int, int>  $branchIds
 */
function incidentUser(array $branchIds, string $role = 'branch_manager'): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', $role)->value('id'));
    $user->branches()->sync($branchIds);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function incidentPayload(Employee $employee, array $overrides = []): array
{
    return array_merge([
        'employee_id' => $employee->id,
        'type' => IncidentType::Vacation->value,
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-11',
        'quantity' => '2.00',
        'reason' => 'Vacaciones de prueba (ficticias)',
    ], $overrides);
}

function incidentPending(Branch $branch, Employee $employee, User $creator, array $overrides = []): AttendanceIncident
{
    $start = CarbonImmutable::parse('2026-11-10', 'America/Mexico_City');

    return AttendanceIncident::query()->create(array_merge([
        'employee_id' => $employee->id,
        'branch_id' => $branch->id,
        'type' => IncidentType::Vacation,
        'start_date' => $start,
        'end_date' => $start->addDay(),
        'quantity' => '2.00',
        'reason' => 'Vacaciones de prueba (ficticias)',
        'status' => IncidentStatus::Pending,
        'created_by' => $creator->id,
    ], $overrides));
}

test('el scope overlapping incluye los días límite del rango', function (): void {
    $branch = incidentBranch();
    $employee = incidentEmployee($branch);
    $manager = incidentUser([$branch->id]);

    // Incidencia del 2026-11-10 al 2026-11-11.
    incidentPending($branch, $employee, $manager);

    expect(AttendanceIncident::query()->overlapping('2026-11-10', '2026-11-10')->count())->toBe(1)
        ->and(AttendanceIncident::query()->overlapping('2026-11-01', '2026-11-10')->count())->toBe(1)
        ->and(AttendanceIncident::query()->overlapping('2026-11-11', '2026-11-11')->count())->toBe(1)
        ->and(AttendanceIncident::query()->overlapping('2026-11-12', '2026-11-20')->count())->toBe(0)
        ->and(AttendanceIncident::query()->overlapping('2026-10-01', '2026-11-09')->count())->toBe(0);
});

test('registra una incidencia con la sucursal del colaborador y auditoría', function (): void {
    $branch = incidentBranch();
    $employee = incidentEmployee($branch);
    $manager = incidentUser([$branch->id]);

    $response = $this->actingAs($manager)->post(route('incidents.store'), incidentPayload($employee));

    $response->assertRedirect(route('incidents.index'));
    $response->assertSessionHas('success');

    $incident = AttendanceIncident::query()->sole();

    expect($incident->employee_id)->toBe($employee->id)
        ->and($incident->branch_id)->toBe($branch->id)
        ->and($incident->type)->toBe(IncidentType::Vacation)
        ->and($incident->status)->toBe(IncidentStatus::Pending)
        ->and($incident->created_by)->toBe($manager->id)
        ->and($incident->start_date->toDateString())->toBe('2026-11-10')
        ->and((string) $incident->quantity)->toBe('2.00');

    $audit = AuditLog::query()->where('action', 'incident.created')->sole();

    expect($audit->auditable_id)->toBe($incident->id)
        ->and($audit->user_id)->toBe($manager->id)
        ->and($audit->after['type'] ?? null)->toBe('vacation');
});

test('rechaza una incidencia duplicada exacta', function (): void {
    $branch = incidentBranch();
    $employee = incidentEmployee($branch);
    $manager = incidentUser([$branch->id]);

    $this->actingAs($manager)->post(route('incidents.store'), incidentPayload($employee));

    $response = $this->actingAs($manager)->post(route('incidents.store'), incidentPayload($employee));

    $response->assertSessionHasErrors('start_date');
    expect(AttendanceIncident::query()->count())->toBe(1);
});

test('aprobar una incidencia exige permiso y deja auditoría', function (): void {
    $branch = incidentBranch();
    $employee = incidentEmployee($branch);
    $manager = incidentUser([$branch->id]);

    $incident = incidentPending($branch, $employee, $manager);

    $response = $this->actingAs($manager)->post(route('incidents.decide', $incident), [
        'decision' => IncidentStatus::Approved->value,
        'notes' => 'Aprobada en prueba.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $incident->refresh();

    expect($incident->status)->toBe(IncidentStatus::Approved)
        ->and($incident->decided_by)->toBe($manager->id)
        ->and($incident->decided_at)->not->toBeNull()
        ->and($incident->notes)->toBe('Aprobada en prueba.');

    expect(AuditLog::query()->where('action', 'incident.decided')->count())->toBe(1);
});

test('solo se pueden editar incidencias pendientes', function (): void {
    $branch = incidentBranch();
    $employee = incidentEmployee($branch);
    $manager = incidentUser([$branch->id]);

    $approved = incidentPending($branch, $employee, $manager, [
        'status' => IncidentStatus::Approved,
        'decided_by' => $manager->id,
        'decided_at' => CarbonImmutable::now(),
    ]);

    $response = $this->actingAs($manager)->put(route('incidents.update', $approved), [
        'type' => IncidentType::Vacation->value,
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-21',
        'quantity' => '2.00',
        'reason' => 'Cambio no permitido',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($approved->refresh()->start_date->toDateString())->toBe('2026-11-10');

    $pending = incidentPending($branch, incidentEmployee($branch, 'EMP-INC-02'), $manager);

    $response = $this->actingAs($manager)->put(route('incidents.update', $pending), [
        'type' => IncidentType::JustifiedAbsence->value,
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-21',
        'quantity' => '2.00',
        'reason' => 'Motivo actualizado',
    ]);

    $response->assertRedirect(route('incidents.index'));
    $pending->refresh();

    expect($pending->type)->toBe(IncidentType::JustifiedAbsence)
        ->and($pending->start_date->toDateString())->toBe('2026-11-20')
        ->and(AuditLog::query()->where('action', 'incident.updated')->count())->toBe(1);
});

test('guarda la evidencia en el disco privado', function (): void {
    Storage::fake('local');

    $branch = incidentBranch();
    $employee = incidentEmployee($branch);
    $manager = incidentUser([$branch->id]);

    $response = $this->actingAs($manager)->post(route('incidents.store'), incidentPayload($employee, [
        'type' => IncidentType::SickLeave->value,
        'evidence' => UploadedFile::fake()->create('justificante.pdf', 120, 'application/pdf'),
    ]));

    $response->assertRedirect(route('incidents.index'));

    $incident = AttendanceIncident::query()->sole();

    expect($incident->evidence_path)->not->toBeNull()
        ->and($incident->evidence_path)->toStartWith('incidents/');

    Storage::disk('local')->assertExists($incident->evidence_path);
});

test('un usuario de otra sucursal no puede decidir la incidencia', function (): void {
    $north = incidentBranch('INC-NORTE');
    $south = incidentBranch('INC-SUR');
    $northEmployee = incidentEmployee($north, 'EMP-INC-NORTH');
    $southEmployee = incidentEmployee($south, 'EMP-INC-SOUTH');
    $northManager = incidentUser([$north->id]);
    $southManager = incidentUser([$south->id]);

    $southIncident = incidentPending($south, $southEmployee, $southManager);

    $this->actingAs($northManager)
        ->post(route('incidents.decide', $southIncident), ['decision' => 'approved'])
        ->assertForbidden();

    $this->actingAs($northManager)
        ->put(route('incidents.update', $southIncident), [
            'type' => IncidentType::Vacation->value,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-11',
            'quantity' => '2.00',
        ])
        ->assertForbidden();

    expect($southIncident->refresh()->status)->toBe(IncidentStatus::Pending)
        ->and($northEmployee->id)->not->toBe($southEmployee->id);
});

test('el listado de incidencias respeta el aislamiento por sucursal', function (): void {
    $north = incidentBranch('INC-NORTE');
    $south = incidentBranch('INC-SUR');
    $northEmployee = incidentEmployee($north, 'EMP-INC-NORTH');
    $southEmployee = incidentEmployee($south, 'EMP-INC-SOUTH');
    $northManager = incidentUser([$north->id]);
    $southManager = incidentUser([$south->id]);

    incidentPending($north, $northEmployee, $northManager);
    incidentPending($south, $southEmployee, $southManager);

    $this->actingAs($northManager)
        ->get(route('incidents.index', ['from' => '2026-11-01', 'to' => '2026-11-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('incidents/Index')
            ->where('incidents.meta.total', 1)
            ->where('incidents.data', fn ($rows): bool => count($rows) === 1
                && $rows[0]['employee']['full_name'] === $northEmployee->fullName())
            ->where('can.manage', true)
            ->where('can.approve', true)
            ->has('options.types', count(IncidentType::cases()))
            ->has('options.statuses', count(IncidentStatus::cases())));
});
