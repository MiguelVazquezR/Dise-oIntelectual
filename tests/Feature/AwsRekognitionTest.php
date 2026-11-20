<?php

use App\Contracts\FaceRecognitionService;
use App\Enums\AttendanceEventType;
use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\AwsFaceOperation;
use App\Enums\AwsUsageSource;
use App\Enums\AwsUsageStatus;
use App\Enums\BiometricStatus;
use App\Models\AttendanceEvent;
use App\Models\AwsUsageRecord;
use App\Models\BiometricEnrollment;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\Aws\AwsClientFactory;
use App\Services\Aws\AwsCostCalculator;
use Aws\Command;
use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Aws\Result;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    awsRekognitionConfigure();
});

/**
 * Cliente de Rekognition sin red: devuelve respuestas preparadas o lanza el
 * fallo indicado. Sustituye al SDK en todas las pruebas de esta ola.
 */
final class AwsRekognitionFakeClient extends RekognitionClient
{
    /**
     * @param  array<string, Result>  $responses
     */
    public function __construct(
        private readonly array $responses = [],
        private readonly ?Throwable $failure = null,
    ) {
        parent::__construct([
            'service' => 'rekognition',
            'region' => 'us-east-1',
            'version' => 'latest',
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'http' => ['timeout' => 1, 'connect_timeout' => 1],
            'retries' => 0,
        ]);
    }

    public function indexFaces(array $args = []): Result
    {
        return $this->respond('indexFaces');
    }

    public function searchFacesByImage(array $args = []): Result
    {
        return $this->respond('searchFacesByImage');
    }

    public function deleteFaces(array $args = []): Result
    {
        return $this->respond('deleteFaces');
    }

    private function respond(string $operation): Result
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return $this->responses[$operation] ?? new Result([]);
    }
}

/**
 * @param  array<string, mixed>  $overrides
 */
function awsRekognitionConfigure(array $overrides = []): void
{
    config(array_merge([
        'rekognition.enabled' => true,
        'rekognition.driver' => 'aws',
        'rekognition.region' => 'us-east-1',
        'rekognition.collection_id' => 'dni-faces-test',
        'rekognition.access_key' => 'test-key',
        'rekognition.secret_key' => 'test-secret',
        'rekognition.price_per_1000' => '24',
        'rekognition.timeout' => 5,
        'rekognition.retries' => 0,
    ], $overrides));
}

function awsRekognitionFactory(RekognitionClient $client): AwsClientFactory
{
    return new class($client) extends AwsClientFactory
    {
        public function __construct(private readonly RekognitionClient $client) {}

        public function rekognition(): RekognitionClient
        {
            return $this->client;
        }
    };
}

function awsRekognitionAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', User::ADMIN_ROLE)->value('id'));

    return $user;
}

function awsRekognitionBranch(string $code = 'AWS-REAL'): Branch
{
    return Branch::factory()->create([
        'code' => $code,
        'name' => 'Sucursal '.$code,
        'timezone' => 'America/Mexico_City',
    ]);
}

test('una operación exitosa registra consumo real con aws_request_id', function (): void {
    $branch = awsRekognitionBranch();
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);
    $admin = awsRekognitionAdmin();

    $client = new AwsRekognitionFakeClient([
        'indexFaces' => new Result([
            'FaceRecords' => [[
                'Face' => ['FaceId' => 'face-abc-123', 'ExternalImageId' => (string) $employee->id],
            ]],
            '@metadata' => ['requestId' => 'req-index-001'],
        ]),
    ]);

    $this->app->instance(AwsClientFactory::class, awsRekognitionFactory($client));

    $this->actingAs($admin)
        ->post(route('employees.face.enrollment.store', $employee), [
            'consent_given' => true,
            'consent_version' => 'v1',
            'image' => base64_encode('imagen-ficticia'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $enrollment = BiometricEnrollment::query()->where('employee_id', $employee->id)->firstOrFail();
    expect($enrollment->status)->toBe(BiometricStatus::Enrolled)
        ->and($enrollment->external_face_id)->toBe('face-abc-123')
        ->and($enrollment->consent_version)->toBe('v1')
        ->and($enrollment->consent_given_at)->not->toBeNull()
        ->and($enrollment->enrolled_at)->not->toBeNull();

    $usage = AwsUsageRecord::query()->firstOrFail();
    expect($usage->operation)->toBe(AwsFaceOperation::IndexFaces)
        ->and($usage->status)->toBe(AwsUsageStatus::Succeeded)
        ->and($usage->source)->toBe(AwsUsageSource::Real)
        ->and($usage->aws_request_id)->toBe('req-index-001')
        ->and($usage->branch_id)->toBe($branch->id)
        ->and($usage->employee_id)->toBe($employee->id)
        ->and((string) $usage->amount_mxn)->toBe('0.024000');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'biometric.enrolled',
        'auditable_id' => $employee->id,
    ]);
});

test('reintentar con la misma idempotency_key no duplica el consumo', function (): void {
    $branch = awsRekognitionBranch('AWS-REAL-2');
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);

    $client = new AwsRekognitionFakeClient([
        'indexFaces' => new Result([
            'FaceRecords' => [[
                'Face' => ['FaceId' => 'face-abc-123', 'ExternalImageId' => (string) $employee->id],
            ]],
            '@metadata' => ['requestId' => 'req-index-002'],
        ]),
    ]);

    $this->app->instance(AwsClientFactory::class, awsRekognitionFactory($client));

    $service = $this->app->make(FaceRecognitionService::class);

    $first = $service->indexFace($employee->id, 'imagen', $branch->id, 'intento-logico-1');
    $second = $service->indexFace($employee->id, 'imagen', $branch->id, 'intento-logico-1');

    expect($first->success)->toBeTrue()
        ->and($second->success)->toBeTrue()
        ->and(AwsUsageRecord::query()->count())->toBe(1);
});

test('una excepción de AWS no rompe el checador y no cobra', function (): void {
    $branch = awsRekognitionBranch('AWS-REAL-3');
    $admin = awsRekognitionAdmin();

    $failure = new AwsException('Rate exceeded', new Command('SearchFacesByImage'), [
        'code' => 'ThrottlingException',
    ]);

    $this->app->instance(AwsClientFactory::class, awsRekognitionFactory(new AwsRekognitionFakeClient([], $failure)));

    $this->actingAs($admin)
        ->post(route('kiosk.face'), [
            'branch_id' => $branch->id,
            'event_type' => 'clock_in',
            'image' => base64_encode('imagen-ficticia'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', fn (string $error): bool => Str::contains($error, 'limitó las solicitudes'));

    $usage = AwsUsageRecord::query()->firstOrFail();
    expect($usage->operation)->toBe(AwsFaceOperation::SearchFacesByImage)
        ->and($usage->status)->toBe(AwsUsageStatus::Failed)
        ->and($usage->source)->toBe(AwsUsageSource::Real)
        ->and($usage->aws_request_id)->toBeNull()
        ->and((string) $usage->amount_mxn)->toBe('0.000000');

    expect(AttendanceEvent::query()->count())->toBe(0);
});

test('la baja del enrolamiento revoca el consentimiento y registra el consumo', function (): void {
    $branch = awsRekognitionBranch('AWS-REAL-4');
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);
    $admin = awsRekognitionAdmin();

    $client = new AwsRekognitionFakeClient([
        'indexFaces' => new Result([
            'FaceRecords' => [[
                'Face' => ['FaceId' => 'face-abc-123', 'ExternalImageId' => (string) $employee->id],
            ]],
            '@metadata' => ['requestId' => 'req-index-003'],
        ]),
        'deleteFaces' => new Result([
            'DeletedFaces' => ['face-abc-123'],
            '@metadata' => ['requestId' => 'req-delete-001'],
        ]),
    ]);

    $this->app->instance(AwsClientFactory::class, awsRekognitionFactory($client));

    $this->actingAs($admin)
        ->post(route('employees.face.enrollment.store', $employee), [
            'consent_given' => true,
            'consent_version' => 'v1',
            'image' => base64_encode('imagen-ficticia'),
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->delete(route('employees.face.enrollment.destroy', $employee))
        ->assertRedirect()
        ->assertSessionHas('success');

    $enrollment = BiometricEnrollment::query()->where('employee_id', $employee->id)->firstOrFail();
    expect($enrollment->status)->toBe(BiometricStatus::Revoked)
        ->and($enrollment->revoked_at)->not->toBeNull()
        ->and($enrollment->external_face_id)->toBeNull();

    $usage = AwsUsageRecord::query()->where('operation', AwsFaceOperation::DeleteFaces)->firstOrFail();
    expect($usage->source)->toBe(AwsUsageSource::Real)
        ->and($usage->aws_request_id)->toBe('req-delete-001')
        ->and((string) $usage->amount_mxn)->toBe('0.024000');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'biometric.revoked',
        'auditable_id' => $employee->id,
    ]);
});

test('el modo simulación etiqueta el consumo como demo y no reconoce identidades', function (): void {
    awsRekognitionConfigure([
        'rekognition.enabled' => false,
        'rekognition.driver' => 'fake',
    ]);

    $branch = awsRekognitionBranch('AWS-DEMO');
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);
    $admin = awsRekognitionAdmin();

    $this->actingAs($admin)
        ->post(route('employees.face.enrollment.store', $employee), [
            'consent_given' => true,
            'consent_version' => 'v1',
            'image' => base64_encode('imagen-ficticia'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $enrollment = BiometricEnrollment::query()->where('employee_id', $employee->id)->firstOrFail();
    expect($enrollment->status)->toBe(BiometricStatus::Enrolled)
        ->and($enrollment->provider)->toBe('demo_rekognition')
        ->and($enrollment->collection_id)->toBe('demo');

    $usage = AwsUsageRecord::query()->firstOrFail();
    expect($usage->source)->toBe(AwsUsageSource::Demo)
        ->and($usage->operation)->toBe(AwsFaceOperation::IndexFaces)
        ->and((string) $usage->amount_mxn)->toBe('0.024000');

    $this->actingAs($admin)
        ->post(route('kiosk.face'), [
            'branch_id' => $branch->id,
            'event_type' => 'clock_in',
            'image' => base64_encode('imagen-ficticia'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', fn (string $error): bool => Str::contains($error, 'Modo demostración'));

    expect(AttendanceEvent::query()->count())->toBe(0);
});

test('el panel de consumo muestra totales internos y el origen de cada registro', function (): void {
    $branch = awsRekognitionBranch('AWS-USAGE');
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);
    $admin = awsRekognitionAdmin();
    $calculator = app(AwsCostCalculator::class);
    $today = CarbonImmutable::now('America/Mexico_City');

    AwsUsageRecord::query()->create([
        'branch_id' => $branch->id,
        'employee_id' => $employee->id,
        'operation' => AwsFaceOperation::SearchFacesByImage,
        'quantity' => 2,
        'status' => AwsUsageStatus::Succeeded,
        'source' => AwsUsageSource::Demo,
        'aws_request_id' => 'demo-request-1',
        'idempotency_key' => (string) Str::uuid(),
        'unit_price_mxn' => $calculator->pricePerThousand(),
        'amount_mxn' => $calculator->amountFor(2),
        'occurred_at' => $today,
        'metadata' => ['simulated' => true],
    ]);

    $this->actingAs($admin)
        ->get(route('aws.usage', ['from' => $today->toDateString(), 'to' => $today->toDateString()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('aws/Usage')
            ->where('totals.faces', 2)
            ->where('totals.amount_mxn', '0.048000')
            ->where('records.meta.total', 1)
            ->where('records.data.0.source', 'demo')
            ->where('records.data.0.branch.name', $branch->name)
            ->where('pricing.price_per_1000', fn ($price): bool => $price === '24.00'
                && preg_match('/^\d+\.\d{2}$/', (string) $price) === 1)
            ->where('pricing.currency', 'MXN')
            ->where('pricing.note', 'Cargo interno de demostración; no es la tarifa oficial de AWS.')
            ->where('options.operations', fn ($operations): bool => count($operations) === 4)
            ->where('options.sources', fn ($sources): bool => count($sources) === 2));
});

test('un rostro reconocido registra el marcaje facial con el recorder compartido del PIN', function (): void {
    $branch = awsRekognitionBranch('AWS-FACE-1');
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);
    $admin = awsRekognitionAdmin();

    BiometricEnrollment::query()->create([
        'employee_id' => $employee->id,
        'provider' => 'aws_rekognition',
        'collection_id' => 'dni-faces-test',
        'external_face_id' => 'face-abc-123',
        'status' => BiometricStatus::Enrolled,
        'consent_given_at' => now(),
        'consent_version' => 'v1',
        'enrolled_at' => now(),
    ]);

    $client = new AwsRekognitionFakeClient([
        'searchFacesByImage' => new Result([
            'FaceMatches' => [[
                'Similarity' => 98.5,
                'Face' => ['FaceId' => 'face-abc-123', 'ExternalImageId' => (string) $employee->id],
            ]],
            '@metadata' => ['requestId' => 'req-search-001'],
        ]),
    ]);

    $this->app->instance(AwsClientFactory::class, awsRekognitionFactory($client));

    $this->actingAs($admin)
        ->post(route('kiosk.face'), [
            'branch_id' => $branch->id,
            'event_type' => 'clock_in',
            'image' => base64_encode('imagen-ficticia'),
            'gps' => ['latitude' => 19.4326, 'longitude' => -99.1332, 'accuracy' => 12.5],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', fn (string $message): bool => Str::contains($message, 'reconocimiento facial'));

    $event = AttendanceEvent::query()->firstOrFail();
    expect($event->method)->toBe(AttendanceMethod::Face)
        ->and($event->validation_status)->toBe(AttendanceStatus::Flagged)
        ->and($event->validation_notes)->toContain('verificación de vida')
        ->and($event->type)->toBe(AttendanceEventType::ClockIn)
        ->and($event->employee_id)->toBe($employee->id)
        ->and($event->branch_id)->toBe($branch->id)
        ->and($event->gps_latitude)->toBe('19.4326000')
        ->and($event->gps_longitude)->toBe('-99.1332000')
        ->and($event->metadata['provider'])->toBe('aws_rekognition')
        ->and($event->metadata['similarity'])->toBe(98.5)
        ->and($event->metadata['collection_id'])->toBe('****test')
        ->and($event->metadata['gps_missing'])->toBeFalse();

    $usage = AwsUsageRecord::query()->where('operation', AwsFaceOperation::SearchFacesByImage)->firstOrFail();
    expect($usage->status)->toBe(AwsUsageStatus::Succeeded)
        ->and($usage->source)->toBe(AwsUsageSource::Real)
        ->and($usage->aws_request_id)->toBe('req-search-001');
});

test('una identificación sin coincidencia no crea marcaje', function (): void {
    $branch = awsRekognitionBranch('AWS-FACE-2');
    $admin = awsRekognitionAdmin();

    $client = new AwsRekognitionFakeClient([
        'searchFacesByImage' => new Result([
            'FaceMatches' => [],
            '@metadata' => ['requestId' => 'req-search-002'],
        ]),
    ]);

    $this->app->instance(AwsClientFactory::class, awsRekognitionFactory($client));

    $this->actingAs($admin)
        ->post(route('kiosk.face'), [
            'branch_id' => $branch->id,
            'event_type' => 'clock_in',
            'image' => base64_encode('imagen-ficticia'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', fn (string $error): bool => Str::contains($error, 'No se reconoció el rostro'));

    expect(AttendanceEvent::query()->count())->toBe(0);
});

test('un colaborador inactivo identificado no registra marcaje facial', function (): void {
    $branch = awsRekognitionBranch('AWS-FACE-3');
    $employee = Employee::factory()->inactive()->create(['branch_id' => $branch->id]);
    $admin = awsRekognitionAdmin();

    $client = new AwsRekognitionFakeClient([
        'searchFacesByImage' => new Result([
            'FaceMatches' => [[
                'Similarity' => 97.0,
                'Face' => ['FaceId' => 'face-abc-999', 'ExternalImageId' => (string) $employee->id],
            ]],
            '@metadata' => ['requestId' => 'req-search-003'],
        ]),
    ]);

    $this->app->instance(AwsClientFactory::class, awsRekognitionFactory($client));

    $this->actingAs($admin)
        ->post(route('kiosk.face'), [
            'branch_id' => $branch->id,
            'event_type' => 'clock_in',
            'image' => base64_encode('imagen-ficticia'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'El colaborador no está activo; no se registró el marcaje.');

    expect(AttendanceEvent::query()->count())->toBe(0);
});

test('el marcaje facial no sustituye ni duplica un marcaje por PIN reciente', function (): void {
    $branch = awsRekognitionBranch('AWS-FACE-4');
    $employee = Employee::factory()->create([
        'branch_id' => $branch->id,
        'employee_number' => 'AWS-FACE-PIN',
        'pin_hash' => bcrypt('1234'),
    ]);
    $admin = awsRekognitionAdmin();

    $client = new AwsRekognitionFakeClient([
        'searchFacesByImage' => new Result([
            'FaceMatches' => [[
                'Similarity' => 96.5,
                'Face' => ['FaceId' => 'face-abc-123', 'ExternalImageId' => (string) $employee->id],
            ]],
            '@metadata' => ['requestId' => 'req-search-004'],
        ]),
    ]);

    $this->app->instance(AwsClientFactory::class, awsRekognitionFactory($client));

    $this->actingAs($admin)
        ->post(route('kiosk.pin'), [
            'employee_number' => 'AWS-FACE-PIN',
            'pin' => '1234',
            'branch_id' => $branch->id,
            'event_type' => 'clock_in',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->post(route('kiosk.face'), [
            'branch_id' => $branch->id,
            'event_type' => 'clock_in',
            'image' => base64_encode('imagen-ficticia'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', fn (string $error): bool => Str::contains($error, 'Doble marcaje'));

    expect(AttendanceEvent::query()->count())->toBe(1);

    $event = AttendanceEvent::query()->firstOrFail();
    expect($event->method)->toBe(AttendanceMethod::Pin)
        ->and($event->validation_status)->toBe(AttendanceStatus::Flagged)
        ->and($event->validation_notes)->toContain('doble marcaje');
});
