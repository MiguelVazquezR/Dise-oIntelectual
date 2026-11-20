<?php

use App\Enums\AwsUsageSource;
use App\Enums\BiometricStatus;
use App\Models\AuditLog;
use App\Models\AwsUsageRecord;
use App\Models\BiometricEnrollment;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);

    // Modo demostración explícito: ni llamadas de red ni identidades fabricadas.
    config([
        'rekognition.enabled' => false,
        'rekognition.driver' => 'fake',
    ]);
});

/** PNG 1×1 ficticio usado como payload de prueba (no es una persona real). */
const SECURITY_FACE_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNkAAIAAAoAAv/lxKUAAAAASUVORK5CYII=';

function securityBiometricBranch(string $code): Branch
{
    return Branch::factory()->create([
        'code' => $code,
        'name' => 'Sucursal '.$code,
        'timezone' => 'America/Mexico_City',
    ]);
}

function securityBiometricUser(string $roleSlug, array $branchIds = []): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', $roleSlug)->value('id'));

    if ($branchIds !== []) {
        $user->branches()->sync($branchIds);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function securityEnrollmentPayload(array $overrides = []): array
{
    return array_merge([
        'consent_given' => true,
        'consent_version' => 'aviso-privacidad-v1',
        'image' => 'data:image/png;base64,'.SECURITY_FACE_PNG,
    ], $overrides);
}

test('sin consentimiento explícito no se crea ningún enrolamiento biométrico', function (): void {
    $branch = securityBiometricBranch('BIO-A');
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);
    $admin = securityBiometricUser(User::ADMIN_ROLE);

    $this->actingAs($admin)
        ->post(route('employees.face.enrollment.store', $employee), securityEnrollmentPayload([
            'consent_given' => false,
        ]))
        ->assertSessionHasErrors('consent_given');

    expect(BiometricEnrollment::query()->count())->toBe(0)
        ->and(AwsUsageRecord::query()->count())->toBe(0);
});

test('el enrolamiento guarda consentimiento, se etiqueta como demo y no persiste la imagen', function (): void {
    $branch = securityBiometricBranch('BIO-B');
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);
    $admin = securityBiometricUser(User::ADMIN_ROLE);

    $this->actingAs($admin)
        ->post(route('employees.face.enrollment.store', $employee), securityEnrollmentPayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    $enrollment = BiometricEnrollment::query()->sole();

    expect($enrollment->status)->toBe(BiometricStatus::Enrolled)
        ->and($enrollment->provider)->toBe('demo_rekognition')
        ->and($enrollment->consent_given_at)->not->toBeNull()
        ->and($enrollment->consent_version)->toBe('aviso-privacidad-v1')
        ->and($enrollment->external_face_id)->not->toBeNull();

    $usage = AwsUsageRecord::query()->sole();

    expect($usage->source)->toBe(AwsUsageSource::Demo)
        ->and($usage->branch_id)->toBe($branch->id);

    $log = AuditLog::query()->where('action', 'biometric.enrolled')->sole();

    expect($log->after)
        ->not->toHaveKey('external_face_id')
        ->not->toHaveKey('image')
        ->not->toHaveKey('image_content')
        ->and(json_encode($log->toArray()))->not->toContain(SECURITY_FACE_PNG)
        ->and(json_encode($log->toArray()))->not->toContain('base64');

    // La imagen nunca se escribe en el disco privado ni en el público.
    expect(Storage::disk('local')->allFiles('incidents'))->toBe([])
        ->and(Storage::disk('local')->allFiles('biometric'))->toBe([]);
});

test('la revocación elimina el rostro del proveedor y conserva la baja auditable', function (): void {
    $branch = securityBiometricBranch('BIO-C');
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);
    $admin = securityBiometricUser(User::ADMIN_ROLE);

    $this->actingAs($admin)
        ->post(route('employees.face.enrollment.store', $employee), securityEnrollmentPayload())
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->delete(route('employees.face.enrollment.destroy', $employee))
        ->assertRedirect()
        ->assertSessionHas('success');

    $enrollment = BiometricEnrollment::query()->sole();

    expect($enrollment->status)->toBe(BiometricStatus::Revoked)
        ->and($enrollment->external_face_id)->toBeNull()
        ->and($enrollment->revoked_at)->not->toBeNull();

    // Idempotente: una segunda baja informa que ya estaba revocado.
    $this->actingAs($admin)
        ->delete(route('employees.face.enrollment.destroy', $employee))
        ->assertSessionHas('success', 'El enrolamiento ya estaba revocado.');

    expect(AuditLog::query()->where('action', 'biometric.revoked')->count())->toBe(1);
});

test('un encargado de sucursal no puede enrolar ni revocar rostros de otra sucursal', function (): void {
    $north = securityBiometricBranch('BIO-N');
    $south = securityBiometricBranch('BIO-S');
    $southEmployee = Employee::factory()->create(['branch_id' => $south->id]);

    // Rol con los permisos del enrolamiento pero acotado a la sucursal norte.
    $role = Role::query()->firstOrCreate(
        ['slug' => 'security_biometric'],
        [
            'name' => 'Encargado biométrico',
            'description' => 'Rol de prueba de la auditoría de seguridad',
            'is_system' => false,
        ],
    );
    $role->permissions()->sync(
        Permission::query()
            ->whereIn('slug', ['employees.view', 'employees.manage', 'aws.settings.manage'])
            ->pluck('id')
            ->all(),
    );

    $manager = User::factory()->create();
    $manager->roles()->attach($role->id);
    $manager->branches()->sync([$north->id]);

    $this->actingAs($manager)
        ->post(route('employees.face.enrollment.store', $southEmployee), securityEnrollmentPayload())
        ->assertForbidden();

    $this->actingAs($manager)
        ->delete(route('employees.face.enrollment.destroy', $southEmployee))
        ->assertForbidden();

    $this->actingAs($manager)
        ->get(route('employees.face.enrollment.show', $southEmployee))
        ->assertForbidden();

    expect(BiometricEnrollment::query()->count())->toBe(0);
});

test('rechaza imágenes que no son base64 válido, con MIME no permitido o mayores a 5 MB', function (): void {
    $branch = securityBiometricBranch('BIO-D');
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);
    $admin = securityBiometricUser(User::ADMIN_ROLE);

    $invalidPayloads = [
        'no-es-base64' => 'esto no es una imagen ¡!',
        'mime-no-permitido' => 'data:text/plain;base64,'.base64_encode('no soy una imagen'),
        'contenido-no-coincide' => 'data:image/png;base64,'.base64_encode('no soy un PNG'),
        'excede-5mb' => 'data:image/png;base64,'.base64_encode(str_repeat('A', (5 * 1024 * 1024) + 16)),
    ];

    foreach ($invalidPayloads as $image) {
        $this->actingAs($admin)
            ->post(route('employees.face.enrollment.store', $employee), securityEnrollmentPayload([
                'image' => $image,
            ]))
            ->assertSessionHasErrors('image');
    }

    expect(BiometricEnrollment::query()->count())->toBe(0)
        ->and(AwsUsageRecord::query()->count())->toBe(0);
});

test('el hash del PIN no se serializa al cliente y el PIN nunca aparece en la auditoría', function (): void {
    $branch = securityBiometricBranch('BIO-E');
    $employee = Employee::factory()->create([
        'branch_id' => $branch->id,
        'pin_hash' => bcrypt('123456'),
    ]);
    $admin = securityBiometricUser(User::ADMIN_ROLE);

    expect($employee->toArray())->not->toHaveKey('pin_hash')
        ->and(json_encode($employee))->not->toContain('pin_hash');

    $this->actingAs($admin)
        ->post(route('employees.face.enrollment.store', $employee), securityEnrollmentPayload())
        ->assertSessionHas('success');

    $auditPayloads = AuditLog::query()->get()->toJson();

    expect($auditPayloads)->not->toContain('pin_hash')
        ->not->toContain('123456')
        ->not->toContain('$2y$');
});
