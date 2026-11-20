<?php

use App\Models\Branch;
use App\Models\Holiday;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-19 10:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function holidayManagementAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', User::ADMIN_ROLE)->value('id'));

    return $user;
}

function holidayManagementUser(string $roleSlug, array $branchIds = []): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', $roleSlug)->value('id'));
    $user->branches()->sync($branchIds);

    return $user;
}

test('el listado de festivos exige holidays.view y la generación holidays.manage', function (): void {
    $branch = Branch::factory()->create();
    $userWithoutPermissions = User::factory()->create();
    $viewer = holidayManagementUser('viewer', [$branch->id]);

    $this->actingAs($userWithoutPermissions)
        ->get(route('holidays.index'))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->get(route('holidays.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('holidays/Index')
            ->where('can.manage', false)
            ->where('filters.year', 2026)
            ->has('legal_notice'));

    $this->actingAs($viewer)
        ->post(route('holidays.generate', ['year' => 2026]))
        ->assertForbidden();

    $this->assertDatabaseCount('holidays', 0);
});

test('la generación de festivos es idempotente', function (): void {
    $admin = holidayManagementAdmin();

    $this->actingAs($admin)
        ->post(route('holidays.generate', ['year' => 2026]))
        ->assertRedirect(route('holidays.index', ['year' => 2026]))
        ->assertSessionHas('success');

    expect(Holiday::query()->whereYear('date', 2026)->count())->toBe(7);

    $this->actingAs($admin)
        ->post(route('holidays.generate', ['year' => 2026]))
        ->assertRedirect(route('holidays.index', ['year' => 2026]))
        ->assertSessionHas('success');

    expect(Holiday::query()->whereYear('date', 2026)->count())->toBe(7)
        ->and(Holiday::query()->whereDate('date', '2026-02-02')->whereNull('branch_id')->count())->toBe(1);

    $this->assertDatabaseHas('audit_logs', ['action' => 'holiday.generated']);
});

test('un año cerrado no se regenera ni se elimina sin autorización', function (): void {
    $admin = holidayManagementAdmin();

    $this->actingAs($admin)
        ->post(route('holidays.generate', ['year' => 2025]))
        ->assertRedirect(route('holidays.index', ['year' => 2025]))
        ->assertSessionHas('error');

    expect(Holiday::query()->whereYear('date', 2025)->count())->toBe(0);

    $closedHoliday = Holiday::query()->create([
        'name' => 'Festivo de año cerrado',
        'date' => '2025-11-17',
        'type' => 'mandatory',
        'legal_basis' => 'LFT art. 74, fracción VI',
    ]);

    $this->actingAs($admin)
        ->delete(route('holidays.destroy', $closedHoliday))
        ->assertRedirect(route('holidays.index', ['year' => 2025]))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('holidays', ['id' => $closedHoliday->id]);
});

test('registra festivos de empresa por sucursal y valida los obligatorios', function (): void {
    $admin = holidayManagementAdmin();
    $matriz = Branch::factory()->create(['code' => 'MATRIZ']);
    $norte = Branch::factory()->create(['code' => 'NORTE']);

    $this->actingAs($admin)
        ->post(route('holidays.store'), [
            'name' => 'Aniversario Matriz',
            'date' => '2026-12-31',
            'type' => 'company',
            'branch_id' => $matriz->id,
        ])
        ->assertRedirect(route('holidays.index', ['year' => 2026]))
        ->assertSessionHas('success');

    $companyHoliday = Holiday::query()->whereDate('date', '2026-12-31')->first();

    expect($companyHoliday)->not->toBeNull()
        ->and($companyHoliday->name)->toBe('Aniversario Matriz')
        ->and($companyHoliday->type->value)->toBe('company')
        ->and($companyHoliday->branch_id)->toBe($matriz->id)
        ->and($companyHoliday->created_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->post(route('holidays.store'), [
            'name' => 'Aniversario Norte',
            'date' => '2026-12-31',
            'type' => 'company',
            'branch_id' => $norte->id,
        ])
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->post(route('holidays.store'), [
            'name' => 'Aniversario duplicado',
            'date' => '2026-12-31',
            'type' => 'company',
            'branch_id' => $matriz->id,
        ])
        ->assertSessionHasErrors('date');

    $this->actingAs($admin)
        ->post(route('holidays.store'), [
            'name' => 'Día adicional',
            'date' => '2026-12-30',
            'type' => 'mandatory',
        ])
        ->assertSessionHasErrors('legal_basis');

    $this->actingAs($admin)
        ->post(route('holidays.store'), [
            'name' => 'Día adicional',
            'date' => '2026-12-30',
            'type' => 'mandatory',
            'legal_basis' => 'LFT art. 74',
            'branch_id' => $matriz->id,
        ])
        ->assertSessionHasErrors('branch_id');

    $this->assertDatabaseMissing('holidays', ['name' => 'Día adicional']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'holiday.created']);
});

test('cada sucursal solo ve sus festivos de empresa y los obligatorios globales', function (): void {
    $matriz = Branch::factory()->create(['code' => 'MATRIZ']);
    $norte = Branch::factory()->create(['code' => 'NORTE']);
    $admin = holidayManagementAdmin();

    $this->actingAs($admin)->post(route('holidays.generate', ['year' => 2026]));

    Holiday::query()->create([
        'name' => 'Aniversario Matriz',
        'date' => '2026-12-31',
        'type' => 'company',
        'branch_id' => $matriz->id,
    ]);

    Holiday::query()->create([
        'name' => 'Aniversario Norte',
        'date' => '2026-12-30',
        'type' => 'company',
        'branch_id' => $norte->id,
    ]);

    $manager = holidayManagementUser('branch_manager', [$matriz->id]);

    $this->actingAs($manager)
        ->get(route('holidays.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('holidays/Index')
            ->where('summary.mandatory', 7)
            ->where('summary.company', 1)
            ->where('summary.total', 8)
            ->has('holidays', 7)
            ->where('holidays.0.month', 1)
            ->where('holidays.0.holidays.0.date', '2026-01-01')
            ->where('holidays.0.holidays.0.is_locked', false)
            ->where('filters.branch_id', null));

    $this->actingAs($manager)
        ->get(route('holidays.index', ['year' => 2026, 'branch_id' => $norte->id]))
        ->assertForbidden();

    $this->actingAs($manager)
        ->post(route('holidays.store'), [
            'name' => 'Intento en sucursal ajena',
            'date' => '2026-12-29',
            'type' => 'company',
            'branch_id' => $norte->id,
        ])
        ->assertForbidden();
});
