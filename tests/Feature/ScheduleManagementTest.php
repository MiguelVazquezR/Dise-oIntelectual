<?php

use App\Models\Branch;
use App\Models\BranchSchedule;
use App\Models\BranchScheduleDay;
use App\Models\BranchScheduleSegment;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

function scheduleManagementAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', 'admin')->value('id'));

    return $user;
}

/**
 * @return array<int, array<string, mixed>>
 */
function scheduleSmokeDays(): array
{
    return [
        ['weekday' => 1, 'is_rest_day' => false, 'late_tolerance_minutes' => 10, 'segments' => [
            ['start_time' => '09:00', 'end_time' => '14:00', 'kind' => 'work'],
            ['start_time' => '14:00', 'end_time' => '15:00', 'kind' => 'break'],
            ['start_time' => '15:00', 'end_time' => '19:00', 'kind' => 'work'],
        ]],
        ['weekday' => 2, 'is_rest_day' => false, 'late_tolerance_minutes' => 10, 'segments' => [
            ['start_time' => '09:00', 'end_time' => '17:00', 'kind' => 'work'],
        ]],
        ['weekday' => 3, 'is_rest_day' => false, 'late_tolerance_minutes' => 10, 'segments' => [
            ['start_time' => '09:00', 'end_time' => '17:00', 'kind' => 'work'],
        ]],
        ['weekday' => 4, 'is_rest_day' => false, 'late_tolerance_minutes' => 10, 'segments' => [
            ['start_time' => '09:00', 'end_time' => '17:00', 'kind' => 'work'],
        ]],
        ['weekday' => 5, 'is_rest_day' => false, 'late_tolerance_minutes' => 10, 'segments' => [
            ['start_time' => '09:00', 'end_time' => '17:00', 'kind' => 'work'],
        ]],
        ['weekday' => 6, 'is_rest_day' => false, 'late_tolerance_minutes' => 0, 'segments' => [
            ['start_time' => '09:00', 'end_time' => '13:00', 'kind' => 'work'],
        ]],
        ['weekday' => 7, 'is_rest_day' => true, 'late_tolerance_minutes' => 0, 'segments' => []],
    ];
}

test('crea, edita y valida un horario con segmentos', function (): void {
    $admin = scheduleManagementAdmin();
    $branch = Branch::factory()->create();

    $response = $this->actingAs($admin)->post(route('schedules.store'), [
        'name' => 'Horario de prueba',
        'branch_id' => $branch->id,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'is_active' => true,
        'days' => scheduleSmokeDays(),
    ]);

    $schedule = BranchSchedule::query()->where('name', 'Horario de prueba')->firstOrFail();

    $response->assertRedirect(route('schedules.index'));
    expect(BranchScheduleDay::query()->where('branch_schedule_id', $schedule->id)->count())->toBe(7)
        ->and(BranchScheduleSegment::query()->whereIn('branch_schedule_day_id', BranchScheduleDay::query()->where('branch_schedule_id', $schedule->id)->select('id'))->count())->toBe(8);

    $this->actingAs($admin)
        ->get(route('schedules.edit', $schedule))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('schedules/Edit')
            ->where('schedule.days.0.segments.0.start_time', '09:00')
            ->where('schedule.days.6.is_rest_day', true));

    $days = scheduleSmokeDays();
    $days[0]['segments'][0]['end_time'] = '13:30';
    $days[0]['segments'][1]['start_time'] = '13:30';

    $this->actingAs($admin)
        ->put(route('schedules.update', $schedule), [
            'name' => 'Horario de prueba editado',
            'branch_id' => $branch->id,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'is_active' => true,
            'days' => $days,
        ])
        ->assertRedirect(route('schedules.index'));

    $schedule->refresh();
    expect($schedule->name)->toBe('Horario de prueba editado')
        ->and($schedule->effective_to->toDateString())->toBe('2026-12-31')
        ->and(BranchScheduleDay::query()->where('branch_schedule_id', $schedule->id)->count())->toBe(7);

    $invalidDays = scheduleSmokeDays();
    $invalidDays[0]['segments'][1]['start_time'] = '13:00';

    $this->actingAs($admin)
        ->post(route('schedules.store'), [
            'name' => 'Horario inválido',
            'branch_id' => $branch->id,
            'effective_from' => '2026-01-01',
            'days' => $invalidDays,
        ])
        ->assertSessionHasErrors('days.0.segments');
});

test('actualiza el PIN con hash y sin exponerlo', function (): void {
    $admin = scheduleManagementAdmin();
    $employee = Employee::factory()->create();

    $response = $this->actingAs($admin)
        ->from(route('employees.show', $employee))
        ->post(route('employees.pin', $employee), [
            'pin' => '4821',
            'pin_confirmation' => '4821',
        ]);

    $response->assertRedirect(route('employees.show', $employee));
    $response->assertSessionHas('success');

    $employee->refresh();

    expect($employee->pin_hash)->not->toBeNull()
        ->and(Hash::check('4821', $employee->pin_hash))->toBeTrue()
        ->and($employee->pin_updated_at)->not->toBeNull()
        ->and($employee->toArray())->not->toHaveKey('pin_hash');

    $this->actingAs($admin)
        ->post(route('employees.pin', $employee), [
            'pin' => '12',
            'pin_confirmation' => '12',
        ])
        ->assertSessionHasErrors('pin');
});
