<?php

use App\Enums\AdjustmentStatus;
use App\Models\EmployeePayrollAdjustment;
use App\Models\PayrollConcept;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PayrollConceptSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PayrollFixtures;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PayrollConceptSeeder::class);
    Carbon::setTestNow('2026-11-19 09:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array<string, mixed>
 */
function payrollConceptPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'BONO_ESPECIAL',
        'name' => 'Bono especial de demostración',
        'type' => 'earning',
        'calculation' => 'fixed',
        'default_amount' => '750.00',
        'is_sensitive' => false,
        'is_active' => true,
        'notes' => 'Concepto ficticio de prueba.',
    ], $overrides);
}

test('el catálogo exige el permiso concepts.view', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('concepts.index'))->assertForbidden();
});

test('crea un concepto y lo lista con el permiso de administración', function (): void {
    $admin = PayrollFixtures::admin();

    $this->actingAs($admin)
        ->post(route('concepts.store'), payrollConceptPayload())
        ->assertSessionHas('success');

    $concept = PayrollConcept::query()->where('code', 'BONO_ESPECIAL')->sole();

    expect($concept->name)->toBe('Bono especial de demostración')
        ->and($concept->type->value)->toBe('earning')
        ->and($concept->calculation->value)->toBe('fixed')
        ->and($concept->default_amount)->toBe('750.00')
        ->and($concept->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->get(route('concepts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('concepts/Index', false)
            ->where('notice', 'Los conceptos son internos de control; no representan cálculos fiscales.')
            ->where('can.manage', true)
            ->where('concepts', fn ($concepts): bool => collect($concepts)->contains(
                fn (array $row): bool => $row['code'] === 'BONO_ESPECIAL' && $row['default_amount'] === '750.00',
            )));
});

test('rechaza un código duplicado', function (): void {
    $admin = PayrollFixtures::admin();

    $this->actingAs($admin)
        ->post(route('concepts.store'), payrollConceptPayload(['code' => 'SUELDO_BASE']))
        ->assertSessionHasErrors('code');

    expect(PayrollConcept::query()->where('code', 'SUELDO_BASE')->count())->toBe(1);
});

test('actualiza un concepto sin duplicar el código de otro', function (): void {
    $admin = PayrollFixtures::admin();
    $concept = PayrollConcept::query()->where('code', 'BONO_PUNTUALIDAD')->sole();

    $this->actingAs($admin)
        ->put(route('concepts.update', $concept), payrollConceptPayload([
            'code' => 'BONO_PUNTUALIDAD',
            'name' => 'Bono de puntualidad ajustado',
            'default_amount' => '600.00',
            'calculation' => 'per_quantity',
        ]))
        ->assertSessionHas('success');

    expect($concept->refresh()->name)->toBe('Bono de puntualidad ajustado')
        ->and($concept->default_amount)->toBe('600.00')
        ->and($concept->calculation->value)->toBe('per_quantity');

    $other = PayrollConcept::query()->where('code', 'HORAS_EXTRA')->sole();

    $this->actingAs($admin)
        ->put(route('concepts.update', $other), payrollConceptPayload(['code' => 'PENALIZACION_ADMIN']))
        ->assertSessionHasErrors('code');
});

test('elimina un concepto sin uso y bloquea el que tiene ajustes', function (): void {
    $admin = PayrollFixtures::admin();
    $branch = PayrollFixtures::branch('CONC-A', 'Sucursal Conceptos');
    $employee = PayrollFixtures::employee($branch, 'EMP-CONC-01', '2900.00');

    $unused = PayrollConcept::query()->create([
        'code' => 'CONCEPTO_TEMPORAL',
        'name' => 'Concepto temporal',
        'type' => 'earning',
        'calculation' => 'fixed',
        'default_amount' => '100.00',
        'is_sensitive' => false,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('concepts.destroy', $unused))
        ->assertSessionHas('success');

    expect(PayrollConcept::query()->whereKey($unused->id)->exists())->toBeFalse();

    $used = PayrollConcept::query()->where('code', 'BONO_PUNTUALIDAD')->sole();

    EmployeePayrollAdjustment::query()->create([
        'employee_id' => $employee->id,
        'payroll_period_id' => null,
        'payroll_concept_id' => $used->id,
        'amount' => '500.00',
        'quantity' => '1.00',
        'reason' => 'Bono ficticio de prueba.',
        'status' => AdjustmentStatus::Pending,
        'created_by' => $admin->id,
        'is_sensitive' => false,
    ]);

    $this->actingAs($admin)
        ->delete(route('concepts.destroy', $used))
        ->assertSessionHas('error');

    expect(PayrollConcept::query()->whereKey($used->id)->exists())->toBeTrue();
});

test('un usuario sin concepts.manage no puede crear ni editar conceptos', function (): void {
    $branch = PayrollFixtures::branch('CONC-B', 'Sucursal Conceptos 2');
    $manager = PayrollFixtures::user([$branch->id]);
    $concept = PayrollConcept::query()->where('code', 'BONO_PUNTUALIDAD')->sole();

    $this->actingAs($manager)
        ->post(route('concepts.store'), payrollConceptPayload())
        ->assertForbidden();

    $this->actingAs($manager)
        ->put(route('concepts.update', $concept), payrollConceptPayload(['code' => 'BONO_PUNTUALIDAD']))
        ->assertForbidden();

    $this->actingAs($manager)
        ->get(route('concepts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('concepts/Index', false)
            ->where('can.manage', false));
});
