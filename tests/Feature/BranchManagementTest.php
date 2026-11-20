<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

function branchManagementAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', User::ADMIN_ROLE)->value('id'));

    return $user;
}

function branchManagementUser(string $roleSlug, array $branchIds = []): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', $roleSlug)->value('id'));
    $user->branches()->sync($branchIds);

    return $user;
}

function branchManagementRestrictedManager(Branch $branch): User
{
    $role = Role::query()->create([
        'name' => 'Encargado restringido',
        'slug' => 'restricted_manager',
        'description' => 'Rol de prueba con administración limitada a una sucursal.',
        'is_system' => false,
    ]);

    $role->permissions()->sync(
        Permission::query()->whereIn('slug', ['branches.view', 'branches.manage'])->pluck('id'),
    );

    $user = User::factory()->create();
    $user->roles()->attach($role);
    $user->branches()->attach($branch->id, ['is_primary' => true]);

    return $user;
}

test('el listado de sucursales requiere el permiso branches.view', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('branches.index'))
        ->assertForbidden();
});

test('la creación de sucursales requiere el permiso branches.manage', function (): void {
    $branch = Branch::factory()->create();
    $viewer = branchManagementUser('viewer', [$branch->id]);

    $this->actingAs($viewer)
        ->get(route('branches.create'))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('branches.store'), [
            'name' => 'Sucursal no permitida',
            'code' => 'NOPE',
            'address' => 'Calle Falsa 123, CDMX',
            'timezone' => 'America/Mexico_City',
            'is_active' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('branches', ['code' => 'NOPE']);
});

test('un usuario de sucursal solo ve y edita su propia sucursal', function (): void {
    $own = Branch::factory()->create(['code' => 'PROPIA']);
    $other = Branch::factory()->create(['code' => 'AJENA']);
    $manager = branchManagementRestrictedManager($own);

    $this->actingAs($manager)
        ->get(route('branches.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('branches/Index')
            ->where('branches.meta.total', 1)
            ->where('branches.data.0.id', $own->id)
            ->where('can.manage', true));

    $this->actingAs($manager)
        ->get(route('branches.edit', $other))
        ->assertForbidden();

    $this->actingAs($manager)
        ->put(route('branches.update', $other), [
            'name' => 'Intento ajeno',
            'code' => 'AJENA',
            'address' => 'Calle Falsa 123, CDMX',
            'timezone' => 'America/Mexico_City',
            'is_active' => true,
        ])
        ->assertForbidden();
});

test('no se puede eliminar una sucursal con colaboradores', function (): void {
    $branch = Branch::factory()->create();
    Employee::factory()->create(['branch_id' => $branch->id]);
    $admin = branchManagementAdmin();

    $response = $this->actingAs($admin)
        ->from(route('branches.index'))
        ->delete(route('branches.destroy', $branch));

    $response->assertRedirect(route('branches.index'));
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('branches', ['id' => $branch->id]);
});
