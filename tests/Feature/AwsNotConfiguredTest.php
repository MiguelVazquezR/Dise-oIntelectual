<?php

use App\Models\AttendanceEvent;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\Aws\AwsClientFactory;
use Aws\Rekognition\RekognitionClient;
use Aws\Sts\StsClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);

    config([
        'rekognition.enabled' => false,
        'rekognition.driver' => 'aws',
        'rekognition.region' => 'us-east-1',
        'rekognition.collection_id' => null,
        'rekognition.access_key' => null,
        'rekognition.secret_key' => null,
        'rekognition.price_per_1000' => '24',
    ]);
});

function awsMissingBranch(string $code = 'AWS-NC'): Branch
{
    return Branch::factory()->create([
        'code' => $code,
        'name' => 'Sucursal '.$code,
        'timezone' => 'America/Mexico_City',
    ]);
}

function awsMissingAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', User::ADMIN_ROLE)->value('id'));

    return $user;
}

/**
 * Fábrica que falla si alguien intenta construir un cliente de AWS: garantiza
 * que las pruebas sin configuración no realizan llamadas externas.
 */
function awsMissingClientFactory(): AwsClientFactory
{
    return new class extends AwsClientFactory
    {
        public function rekognition(): RekognitionClient
        {
            throw new RuntimeException('No se debe crear el cliente de Rekognition sin configuración.');
        }

        public function sts(): StsClient
        {
            throw new RuntimeException('No se debe crear el cliente de STS sin configuración.');
        }
    };
}

test('sin credenciales el checador facial responde con error controlado y no llama a AWS', function (): void {
    $branch = awsMissingBranch();
    $admin = awsMissingAdmin();
    $this->app->instance(AwsClientFactory::class, awsMissingClientFactory());

    $this->actingAs($admin)
        ->post(route('kiosk.face'), [
            'branch_id' => $branch->id,
            'event_type' => 'clock_in',
            'image' => base64_encode('imagen-ficticia'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Integración no configurada');

    expect(AttendanceEvent::query()->count())->toBe(0);
    expect(DB::table('aws_usage_records')->count())->toBe(0);
});

test('con la integración habilitada pero sin credenciales no se intenta ninguna llamada', function (): void {
    config(['rekognition.enabled' => true, 'rekognition.collection_id' => 'coleccion-ficticia']);

    $branch = awsMissingBranch('AWS-NC-2');
    $admin = awsMissingAdmin();
    $this->app->instance(AwsClientFactory::class, awsMissingClientFactory());

    $this->actingAs($admin)
        ->post(route('kiosk.face'), [
            'branch_id' => $branch->id,
            'event_type' => 'clock_out',
            'image' => base64_encode('imagen-ficticia'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Reconocimiento facial pendiente de configuración');

    expect(DB::table('aws_usage_records')->count())->toBe(0);
});

test('el marcaje por PIN sigue operativo sin AWS configurado', function (): void {
    $branch = awsMissingBranch('AWS-NC-3');
    $admin = awsMissingAdmin();
    $employee = Employee::factory()->create([
        'branch_id' => $branch->id,
        'employee_number' => 'AWS-PIN-01',
        'pin_hash' => Hash::make('4321'),
    ]);

    $this->actingAs($admin)
        ->post(route('kiosk.pin'), [
            'employee_number' => 'AWS-PIN-01',
            'pin' => '4321',
            'branch_id' => $branch->id,
            'event_type' => 'clock_in',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(AttendanceEvent::query()->where('employee_id', $employee->id)->count())->toBe(1);
});

test('el diagnóstico muestra configured=false y enmascara la colección sin exponer claves', function (): void {
    config([
        'rekognition.enabled' => true,
        'rekognition.access_key' => 'AKIA-PRUEBA-EXPUESTA',
        'rekognition.secret_key' => '',
        'rekognition.collection_id' => 'coleccion-ficticia-2026',
    ]);

    $admin = awsMissingAdmin();

    $response = $this->actingAs($admin)->get(route('aws.settings'));

    $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->component('settings/Aws')
        ->where('configuration.enabled', true)
        ->where('configuration.configured', false)
        ->where('configuration.driver', 'not_configured')
        ->where('configuration.has_access_key', true)
        ->where('configuration.has_secret', false)
        ->where('configuration.collection_id_masked', '****2026')
        ->where('can.manage', true)
        ->missing('configuration.access_key')
        ->missing('configuration.secret_key')
        ->where('test_result', null));

    $response->assertDontSee('AKIA-PRUEBA-EXPUESTA');
});

test('la prueba de credenciales sin configuración no llama a AWS y deja el resultado en sesión', function (): void {
    $admin = awsMissingAdmin();
    $this->app->instance(AwsClientFactory::class, awsMissingClientFactory());

    $response = $this->actingAs($admin)->post(route('aws.settings.test'));

    $response->assertRedirect()->assertSessionHas('error');

    /** @var array<string, mixed> $result */
    $result = session('test_result');

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->toBe('No hay credenciales de AWS configuradas; no se realizó ninguna llamada.');
    expect($result['checks'])->toBeArray()->not->toBeEmpty();
    expect(DB::table('aws_usage_records')->count())->toBe(0);
});

test('el diagnóstico consume el resultado de la prueba una sola vez', function (): void {
    config([
        'rekognition.access_key' => 'AKIA-PRUEBA-EXPUESTA',
        'rekognition.secret_key' => 'SECRETO-PRUEBA',
        'rekognition.collection_id' => 'coleccion-ficticia-2026',
    ]);
    $this->app->instance(AwsClientFactory::class, awsMissingClientFactory());

    $admin = awsMissingAdmin();

    $this->actingAs($admin)->post(route('aws.settings.test'))->assertRedirect();
    $this->actingAs($admin)
        ->get(route('aws.settings'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('test_result.ok', false));

    $this->actingAs($admin)
        ->get(route('aws.settings'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('test_result', null));
});
