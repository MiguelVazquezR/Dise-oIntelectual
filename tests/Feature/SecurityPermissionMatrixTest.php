<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Rutas atendidas por controladores de la aplicación, sin las preferencias de
 * la cuenta (perfil y contraseña propias, que no dependen de un permiso).
 *
 * @return array<int, RoutingRoute>
 */
function securityAppRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->getActionName(), 'App\\Http\\Controllers\\'))
        ->reject(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'settings'))
        ->values()
        ->all();
}

/**
 * @param  array<int, string>  $permissionSlugs
 */
function securityUser(string $roleSlug, array $permissionSlugs = [], array $branchIds = []): User
{
    $user = User::factory()->create();
    $role = Role::query()->where('slug', $roleSlug)->first();

    if ($role === null) {
        $role = Role::query()->create([
            'name' => 'Rol de seguridad '.$roleSlug,
            'slug' => $roleSlug,
            'description' => 'Rol de prueba de la auditoría de seguridad',
            'is_system' => false,
        ]);
    }

    if ($permissionSlugs !== []) {
        $role->permissions()->sync(
            Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id')->all(),
        );
    }

    $user->roles()->attach($role->id);

    if ($branchIds !== []) {
        $user->branches()->sync($branchIds);
    }

    return $user;
}

test('toda ruta de datos exige sesión, correo verificado y permiso RBAC declarado', function (): void {
    $routes = securityAppRoutes();

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $middleware = array_map(static fn (mixed $entry): string => (string) $entry, $route->gatherMiddleware());
        $uri = $route->uri();

        expect($middleware)->toContain('auth');

        if (str_starts_with($uri, 'my/')) {
            continue;
        }

        expect($middleware)->toContain('verified');

        $permissions = array_values(array_filter(
            $middleware,
            static fn (string $entry): bool => str_starts_with($entry, 'permission:'),
        ));

        expect($permissions)->not->toBeEmpty("La ruta {$uri} no declara middleware permission:");
    }
});

test('cada slug de permiso usado en rutas existe en el catálogo RBAC', function (): void {
    $catalog = Permission::query()->pluck('slug')->all();

    expect($catalog)->not->toBeEmpty();

    foreach (securityAppRoutes() as $route) {
        foreach ($route->gatherMiddleware() as $entry) {
            $middleware = (string) $entry;

            if (! str_starts_with($middleware, 'permission:')) {
                continue;
            }

            $slugs = explode(',', substr($middleware, strlen('permission:')));

            foreach ($slugs as $slug) {
                expect($catalog)->toContain(trim($slug));
            }
        }
    }
});

test('el grupo web conserva el middleware CSRF y no hay exclusiones globales', function (): void {
    $groups = app(Kernel::class)->getMiddlewareGroups();

    $hasCsrf = collect($groups['web'])->contains(
        static fn (mixed $middleware): bool => is_string($middleware)
            && is_a($middleware, PreventRequestForgery::class, true),
    );

    expect($hasCsrf)->toBeTrue();

    $except = (new ReflectionClass(PreventRequestForgery::class))
        ->getProperty('except')
        ->getDefaultValue();

    expect($except)->toBe([]);
});

test('un visitante anónimo es redirigido al login en todas las rutas de datos', function (string $uri): void {
    $this->get($uri)->assertRedirect(route('login'));
})->with([
    '/dashboard',
    '/employees',
    '/employees/export',
    '/attendance',
    '/incidents',
    '/holidays',
    '/vacations',
    '/payroll/periods',
    '/adjustments',
    '/concepts',
    '/kiosk',
    '/aws/usage',
    '/settings/aws',
    '/audit',
    '/my/attendance',
    '/my/vacations',
]);

test('las props compartidas no exponen hash de PIN, contraseñas ni secretos', function (): void {
    $admin = securityUser(User::ADMIN_ROLE);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('auth.user', fn ($user): bool => array_keys(collect($user)->all()) === ['id', 'name', 'email'])
        ->missing('auth.user.pin_hash')
        ->missing('auth.user.password')
        ->missing('auth.user.two_factor_secret')
        ->missing('auth.access_key')
        ->missing('auth.secret'));

    $content = (string) $response->getContent();

    expect($content)
        ->not->toContain('pin_hash')
        ->not->toContain('two_factor_secret')
        ->not->toContain('AWS_SECRET_ACCESS_KEY')
        ->not->toContain('access_key');
});

test('los módulos administrativos y de AWS exigen su permiso específico', function (): void {
    $branch = Branch::factory()->create(['code' => 'SEC-MATRIZ', 'name' => 'Sucursal Matriz']);
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);

    $viewer = securityUser('viewer', [], [$branch->id]);
    $manager = securityUser('branch_manager', [], [$branch->id]);
    $hr = securityUser('hr');
    $admin = securityUser(User::ADMIN_ROLE);

    // Consulta: lectura del padrón, sin escritura ni administración.
    $this->actingAs($viewer)->get(route('employees.index'))->assertOk();
    $this->actingAs($viewer)->get(route('employees.export'))->assertOk();
    $this->actingAs($viewer)->get(route('users.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('audit.index'))->assertForbidden();
    $this->actingAs($viewer)->post(route('aws.settings.test'))->assertForbidden();
    $this->actingAs($viewer)->post(route('employees.store'))->assertForbidden();

    // Encargado de sucursal: sin AWS ni auditoría, y sin permiso para enrolar biometría.
    $this->actingAs($manager)->get(route('aws.usage'))->assertForbidden();
    $this->actingAs($manager)->get(route('audit.index'))->assertForbidden();
    $this->actingAs($manager)->post(route('employees.face.enrollment.store', $employee), [
        'consent_given' => true,
        'consent_version' => 'v1',
        'image' => 'data:image/png;base64,AAAA',
    ])->assertForbidden();

    // Recursos Humanos: sin administración de cuentas ni de credenciales de AWS.
    $this->actingAs($hr)->get(route('users.index'))->assertForbidden();
    $this->actingAs($hr)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($hr)->get(route('audit.index'))->assertOk();
    $this->actingAs($hr)->get(route('aws.usage'))->assertOk();
    $this->actingAs($hr)->get(route('aws.settings'))->assertForbidden();
    $this->actingAs($hr)->post(route('aws.settings.test'))->assertForbidden();

    // Administrador: único que administra cuentas, roles y credenciales.
    $this->actingAs($admin)->get(route('users.index'))->assertOk();
    $this->actingAs($admin)->get(route('roles.index'))->assertOk();
    $this->actingAs($admin)->get(route('aws.settings'))->assertOk();
});
