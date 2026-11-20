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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  array<int, array{segments: array<int, array{0: string, 1: string, 2: string}>, tolerance?: int}>  $days
 */
function pinClockSchedule(Branch $branch, array $days): BranchSchedule
{
    $schedule = BranchSchedule::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Horario checador',
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'is_active' => true,
    ]);

    for ($weekday = 1; $weekday <= 7; $weekday++) {
        $definition = $days[$weekday] ?? ['segments' => []];
        $day = $schedule->days()->create([
            'weekday' => $weekday,
            'is_rest_day' => $definition['segments'] === [],
            'late_tolerance_minutes' => $definition['tolerance'] ?? 10,
        ]);

        foreach (array_values($definition['segments']) as $index => $segment) {
            $day->segments()->create([
                'start_time' => $segment[0],
                'end_time' => $segment[1],
                'kind' => $segment[2],
                'sort_order' => $index,
            ]);
        }
    }

    return $schedule;
}

function pinClockBranch(string $code = 'PIN-A', string $name = 'Sucursal Checador'): Branch
{
    $branch = Branch::factory()->create([
        'code' => $code,
        'name' => $name,
        'timezone' => 'America/Mexico_City',
    ]);

    $office = [
        'segments' => [
            ['09:00', '14:00', 'work'],
            ['14:00', '15:00', 'break'],
            ['15:00', '19:00', 'work'],
        ],
    ];

    pinClockSchedule($branch, [
        1 => $office,
        2 => $office,
        3 => $office,
        4 => $office,
        5 => $office,
        6 => ['segments' => [['09:00', '13:00', 'work']], 'tolerance' => 0],
    ]);

    return $branch;
}

function pinClockNightBranch(string $code = 'PIN-NOCHE'): Branch
{
    $branch = Branch::factory()->create([
        'code' => $code,
        'name' => 'Sucursal Nocturna',
        'timezone' => 'America/Mexico_City',
    ]);

    pinClockSchedule($branch, array_fill_keys(
        [1, 2, 3, 4, 5, 6],
        ['segments' => [['22:00', '06:00', 'work']], 'tolerance' => 15],
    ));

    return $branch;
}

function pinClockUser(Branch $branch, string $role = 'branch_manager'): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', $role)->value('id'));
    $user->branches()->sync([$branch->id => ['is_primary' => true]]);

    return $user;
}

function pinClockEmployee(Branch $branch, array $overrides = []): Employee
{
    return Employee::factory()->create(array_merge([
        'employee_number' => 'EMP-PIN-0001',
        'branch_id' => $branch->id,
        'status' => 'active',
        'pin_hash' => Hash::make('1234'),
    ], $overrides));
}

/**
 * @return array<string, mixed>
 */
function pinClockPayload(Branch $branch, array $overrides = []): array
{
    return array_merge([
        'employee_number' => 'EMP-PIN-0001',
        'pin' => '1234',
        'branch_id' => $branch->id,
        'event_type' => 'clock_in',
    ], $overrides);
}

function pinClockSetNow(string $utcDateTime): void
{
    $moment = Carbon::parse($utcDateTime, 'UTC');
    Carbon::setTestNow($moment);
    CarbonImmutable::setTestNow($moment->toImmutable());
}

test('registra un marcaje por PIN con GPS y sin persistir el PIN', function (): void {
    $branch = pinClockBranch();
    $employee = pinClockEmployee($branch);
    $user = pinClockUser($branch);

    pinClockSetNow('2026-11-04 15:00:00');

    $response = $this->actingAs($user)->post(route('kiosk.pin'), pinClockPayload($branch, [
        'gps' => ['latitude' => 19.487, 'longitude' => -99.156, 'accuracy' => 12.5],
    ]));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $response->assertSessionMissing('error');

    $event = AttendanceEvent::query()->sole();

    expect($event->employee_id)->toBe($employee->id)
        ->and($event->branch_id)->toBe($branch->id)
        ->and($event->type)->toBe(AttendanceEventType::ClockIn)
        ->and($event->method)->toBe(AttendanceMethod::Pin)
        ->and($event->validation_status->value)->toBe('valid')
        ->and($event->work_date->toDateString())->toBe('2026-11-04')
        ->and($event->occurred_at->toDateTimeString())->toBe('2026-11-04 15:00:00')
        ->and((string) $event->gps_latitude)->toBe('19.4870000')
        ->and((string) $event->gps_longitude)->toBe('-99.1560000')
        ->and((string) $event->gps_accuracy_m)->toBe('12.50')
        ->and($event->metadata)->toMatchArray(['gps_missing' => false, 'recorded_via' => 'kiosk'])
        ->and($event->getAttributes())->not->toHaveKey('pin');
});

test('rechaza un PIN inválido sin registrar el marcaje', function (): void {
    $branch = pinClockBranch();
    pinClockEmployee($branch);
    $user = pinClockUser($branch);

    $response = $this->actingAs($user)->post(route('kiosk.pin'), pinClockPayload($branch, ['pin' => '9999']));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $response->assertSessionMissing('success');

    expect(AttendanceEvent::query()->count())->toBe(0);
});

test('limita los intentos por número de empleado', function (): void {
    $branch = pinClockBranch();
    pinClockEmployee($branch);
    $user = pinClockUser($branch);

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $response = $this->actingAs($user)->post(route('kiosk.pin'), pinClockPayload($branch, ['pin' => '0000']));
    }

    $response->assertStatus(429);
    expect(AttendanceEvent::query()->count())->toBe(0);
});

test('el límite por colaborador incluye la IP para evitar bloqueos dirigidos', function (): void {
    $branch = pinClockBranch();
    pinClockEmployee($branch);
    $user = pinClockUser($branch);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.1'])
            ->actingAs($user)
            ->post(route('kiosk.pin'), pinClockPayload($branch));
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.1'])
        ->actingAs($user)
        ->post(route('kiosk.pin'), pinClockPayload($branch))
        ->assertStatus(429);

    $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.2'])
        ->actingAs($user)
        ->post(route('kiosk.pin'), pinClockPayload($branch))
        ->assertStatus(302);
});

test('no registra marcajes de colaboradores inactivos', function (): void {
    $branch = pinClockBranch();
    pinClockEmployee($branch, ['status' => 'inactive']);
    $user = pinClockUser($branch);

    $response = $this->actingAs($user)->post(route('kiosk.pin'), pinClockPayload($branch));

    $response->assertSessionHas('error');
    expect(AttendanceEvent::query()->count())->toBe(0);
});

test('el GPS es opcional y los marcajes sin GPS quedan marcados', function (): void {
    $branch = pinClockBranch();
    pinClockEmployee($branch);
    $user = pinClockUser($branch);

    pinClockSetNow('2026-11-04 15:05:00');

    $response = $this->actingAs($user)->post(route('kiosk.pin'), pinClockPayload($branch));

    $response->assertSessionHas('success');

    $event = AttendanceEvent::query()->sole();

    expect($event->gps_latitude)->toBeNull()
        ->and($event->gps_longitude)->toBeNull()
        ->and($event->metadata)->toMatchArray(['gps_missing' => true]);
});

test('evita el doble marcaje del mismo tipo en menos de 60 segundos', function (): void {
    $branch = pinClockBranch();
    pinClockEmployee($branch);
    $user = pinClockUser($branch);

    pinClockSetNow('2026-11-04 15:00:00');
    $this->actingAs($user)->post(route('kiosk.pin'), pinClockPayload($branch))->assertSessionHas('success');

    pinClockSetNow('2026-11-04 15:00:30');
    $response = $this->actingAs($user)->post(route('kiosk.pin'), pinClockPayload($branch));

    $response->assertSessionHas('error');
    expect(AttendanceEvent::query()->count())->toBe(1);

    $event = AttendanceEvent::query()->sole();
    expect($event->validation_status->value)->toBe('flagged');
});

test('un turno nocturno asigna la fecha laborable del día previo', function (): void {
    $branch = pinClockNightBranch();
    pinClockEmployee($branch);
    $user = pinClockUser($branch);

    // 02:00 hora local del 5 de noviembre: sigue siendo el turno del día 4.
    pinClockSetNow('2026-11-05 08:00:00');

    $response = $this->actingAs($user)->post(route('kiosk.pin'), pinClockPayload($branch, [
        'event_type' => 'clock_out',
    ]));

    $response->assertSessionHas('success');

    $event = AttendanceEvent::query()->sole();

    expect($event->work_date->toDateString())->toBe('2026-11-04')
        ->and($event->occurred_at->toDateTimeString())->toBe('2026-11-05 08:00:00');
});

test('el checador expone sucursal, estado facial y marcajes recientes', function (): void {
    $branch = pinClockBranch();
    $employee = pinClockEmployee($branch);
    $user = pinClockUser($branch);

    pinClockSetNow('2026-11-04 15:00:00');

    AttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'branch_id' => $branch->id,
        'type' => AttendanceEventType::ClockIn,
        'occurred_at' => CarbonImmutable::now(),
        'work_date' => '2026-11-04',
        'method' => AttendanceMethod::Pin,
    ]);

    $this->actingAs($user)
        ->get(route('kiosk.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('kiosk/Index')
            ->where('branch.id', $branch->id)
            ->where('branch.timezone', 'America/Mexico_City')
            ->where('face.configured', false)
            ->where('face.enabled', false)
            ->where('can.face', false)
            ->has('options.branches', 1)
            ->has('recent_events', 1)
            ->where('recent_events.0.type', 'clock_in')
            ->where('recent_events.0.method', 'pin'));
});

test('el marcaje facial no llama a AWS cuando la integración está desactivada', function (): void {
    $branch = pinClockBranch();
    $user = pinClockUser($branch);

    $response = $this->actingAs($user)->post(route('kiosk.face'), [
        'branch_id' => $branch->id,
        'event_type' => 'clock_in',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Integración no configurada');
    expect(AttendanceEvent::query()->count())->toBe(0);

    config(['rekognition.enabled' => true]);

    $response = $this->actingAs($user)->post(route('kiosk.face'), [
        'branch_id' => $branch->id,
        'event_type' => 'clock_in',
    ]);

    $response->assertSessionHas('error', 'Reconocimiento facial pendiente de configuración');
    expect(AttendanceEvent::query()->count())->toBe(0);
});
