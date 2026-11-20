<?php

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

function auditTestUser(string $role): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', $role)->value('id'));

    return $user;
}

function auditTestLog(User $actor, string $action, ?array $before = null, ?array $after = null): AuditLog
{
    return AuditLog::query()->create([
        'user_id' => $actor->id,
        'action' => $action,
        'auditable_type' => Employee::class,
        'auditable_id' => 1,
        'before' => $before,
        'after' => $after,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Prueba de auditoría',
    ]);
}

test('la auditoría exige el permiso audit.view', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('audit.index'))->assertForbidden();
});

test('la auditoría lista metadatos y nunca payloads con PIN o biometría', function (): void {
    $admin = auditTestUser(User::ADMIN_ROLE);

    auditTestLog($admin, 'biometric.enrolled', ['status' => 'pending'], [
        'pin' => 'pin-secreto-1234',
        'rostro' => 'rostro-secreto',
    ]);

    $response = $this->actingAs($admin)->get(route('audit.index'));

    $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->component('audit/Index')
        ->where('logs.meta.total', 1)
        ->where('logs.data.0.action', 'biometric.enrolled')
        ->where('logs.data.0.user.name', $admin->name)
        ->where('logs.data.0.auditable_type', Employee::class)
        ->where('logs.data.0.ip_address', '127.0.0.1')
        ->where('filters.action', null)
        ->where('can.view', true)
        ->missing('logs.data.0.before')
        ->missing('logs.data.0.after'));

    $response->assertDontSee('pin-secreto-1234');
    $response->assertDontSee('rostro-secreto');
});

test('la auditoría filtra por acción y usuario', function (): void {
    $admin = auditTestUser(User::ADMIN_ROLE);
    $manager = auditTestUser('branch_manager');

    auditTestLog($admin, 'holiday.generated');
    auditTestLog($manager, 'biometric.enrolled');
    auditTestLog($manager, 'biometric.revoked');

    $this->actingAs($admin)
        ->get(route('audit.index', ['action' => 'biometric.enrolled']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('logs.meta.total', 1)
            ->where('logs.data.0.action', 'biometric.enrolled')
            ->where('filters.action', 'biometric.enrolled')
            ->where('options.actions', fn ($actions): bool => count($actions) === 3));

    $this->actingAs($admin)
        ->get(route('audit.index', ['user_id' => $manager->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('logs.meta.total', 2)
            ->where('filters.user_id', $manager->id));
});
