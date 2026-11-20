<?php

use App\Enums\AwsFaceOperation;
use App\Enums\AwsUsageSource;
use App\Enums\AwsUsageStatus;
use App\Models\AwsUsageRecord;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Consumo AWS (`/aws/usage`, §3.6 y §7.4): el listado y los totales se aíslan
 * por sucursal y el origen demo/real se conserva y se filtra.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    CarbonImmutable::setTestNow('2026-11-19 10:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * Usuario de prueba con solo el permiso de consumo AWS en una sucursal.
 */
function awsUsageIsolationUser(Branch $branch): User
{
    $role = Role::query()->create([
        'name' => 'QA consumo AWS',
        'slug' => 'qa_aws_usage',
        'description' => 'Rol de prueba (no productivo)',
        'is_system' => false,
    ]);

    $role->permissions()->sync(
        Permission::query()->whereIn('slug', ['aws.usage.view'])->pluck('id')->all(),
    );

    $user = User::factory()->create();
    $user->roles()->sync([$role->id]);
    $user->branches()->sync([$branch->id => ['is_primary' => true]]);

    return $user;
}

function awsUsageIsolationRecord(
    Branch $branch,
    int $quantity,
    AwsUsageSource $source,
    string $key,
): AwsUsageRecord {
    return AwsUsageRecord::query()->create([
        'branch_id' => $branch->id,
        'operation' => AwsFaceOperation::SearchFacesByImage,
        'quantity' => $quantity,
        'status' => AwsUsageStatus::Succeeded,
        'source' => $source,
        'idempotency_key' => $key,
        'unit_price_mxn' => '24.0000',
        'amount_mxn' => number_format($quantity * 24 / 1000, 6, '.', ''),
        'occurred_at' => CarbonImmutable::now('UTC'),
    ]);
}

test('el consumo AWS se aísla por sucursal y conserva el origen demo/real', function (): void {
    $north = Branch::factory()->create(['code' => 'AWS-NORTE', 'name' => 'Sucursal Norte AWS']);
    $south = Branch::factory()->create(['code' => 'AWS-SUR', 'name' => 'Sucursal Sur AWS']);

    awsUsageIsolationRecord($north, 1000, AwsUsageSource::Real, 'iso-norte-real');
    awsUsageIsolationRecord($north, 500, AwsUsageSource::Demo, 'iso-norte-demo');
    awsUsageIsolationRecord($south, 300, AwsUsageSource::Real, 'iso-sur-real');

    $user = awsUsageIsolationUser($north);

    $this->actingAs($user)
        ->get(route('aws.usage', ['from' => '2026-11-19', 'to' => '2026-11-19']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('aws/Usage', false)
            ->where('totals.faces', 1500)
            ->where('totals.amount_mxn', '36.000000')
            ->where('records.meta.total', 2)
            ->where('records.data.0.branch.id', $north->id)
            ->where('records.data.1.branch.id', $north->id)
            ->where('pricing.currency', 'MXN'));

    $this->actingAs($user)
        ->get(route('aws.usage', [
            'from' => '2026-11-19',
            'to' => '2026-11-19',
            'source' => 'demo',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.source', 'demo')
            ->where('totals.faces', 500)
            ->where('records.meta.total', 1)
            ->where('records.data.0.source', 'demo'));

    $this->actingAs($user)
        ->get(route('aws.usage', ['branch_id' => $south->id]))
        ->assertForbidden();
});
