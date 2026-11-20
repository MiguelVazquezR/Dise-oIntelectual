<?php

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

function userRoleManagementAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', User::ADMIN_ROLE)->value('id'));

    return $user;
}

function userRoleManagementBranchManager(Branch $branch): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', 'branch_manager')->value('id'));
    $user->branches()->attach($branch->id, ['is_primary' => true]);

    return $user;
}

test('el administrador crea un usuario con rol y sucursal', function (): void {
    $admin = userRoleManagementAdmin();
    $branch = Branch::factory()->create();
    $hrRole = Role::query()->where('slug', 'hr')->firstOrFail();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Laura Demo',
        'email' => 'laura.demo@diseno-intelectual.test',
        'password' => 'NominaDemo2026!',
        'role_ids' => [$hrRole->id],
        'branch_ids' => [$branch->id],
    ]);

    $user = User::query()->where('email', 'laura.demo@diseno-intelectual.test')->firstOrFail();

    $response->assertRedirect(route('users.index'));
    expect($user->hasRole('hr'))->toBeTrue()
        ->and($user->branches()->pluck('branches.id')->all())->toBe([$branch->id])
        ->and($user->email_verified_at)->not->toBeNull();
});

test('un encargado de sucursal recibe 403 en la administración de usuarios', function (): void {
    $branch = Branch::factory()->create();
    $manager = userRoleManagementBranchManager($branch);

    $this->actingAs($manager)
        ->get(route('users.index'))
        ->assertForbidden();

    $this->actingAs($manager)
        ->get(route('roles.index'))
        ->assertForbidden();
});

test('el rol del sistema no se puede modificar desde la API', function (): void {
    $admin = userRoleManagementAdmin();
    $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
    $permissionsBefore = $adminRole->permissions()->pluck('slug')->sort()->values()->all();

    $response = $this->actingAs($admin)
        ->from(route('roles.index'))
        ->put(route('roles.update', $adminRole), ['permissions' => ['dashboard.view']]);

    $response->assertRedirect(route('roles.index'));
    $response->assertSessionHas('error');

    $permissionsAfter = $adminRole->fresh()->permissions()->pluck('slug')->sort()->values()->all();

    expect($permissionsAfter)->toBe($permissionsBefore)
        ->and($permissionsAfter)->toContain('users.manage');
});

test('el administrador no puede quitarse su propio rol admin', function (): void {
    $admin = userRoleManagementAdmin();
    $managerRole = Role::query()->where('slug', 'branch_manager')->firstOrFail();

    $response = $this->actingAs($admin)->put(route('users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role_ids' => [$managerRole->id],
        'branch_ids' => [],
    ]);

    $response->assertForbidden();
    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
});
