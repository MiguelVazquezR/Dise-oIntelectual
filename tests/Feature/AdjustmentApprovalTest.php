<?php

use App\Enums\AdjustmentStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeePayrollAdjustment;
use App\Models\PayrollConcept;
use App\Models\PayrollItem;
use App\Models\PayrollItemConcept;
use App\Models\PayrollPeriod;
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
function adjustmentPayload(int $employeeId, int $conceptId, ?int $periodId, array $overrides = []): array
{
    return array_merge([
        'employee_id' => $employeeId,
        'payroll_concept_id' => $conceptId,
        'payroll_period_id' => $periodId,
        'amount' => '500.00',
        'quantity' => '1.00',
        'reason' => 'Ajuste ficticio de prueba.',
    ], $overrides);
}

/**
 * @return array{branch: Branch, employee: Employee, period: PayrollPeriod, admin: User}
 */
function adjustmentScenario(string $code = 'ADJ-A'): array
{
    $branch = PayrollFixtures::branch($code, 'Sucursal Ajustes '.$code);
    $employee = PayrollFixtures::employee($branch, 'EMP-'.$code, '2900.00', 'weekly');
    PayrollFixtures::fullWeek($employee, $branch);

    $schedule = PayrollFixtures::schedule('weekly', 'Esquema ajustes '.$code);
    $period = PayrollFixtures::period($schedule, '2026-11-02', '2026-11-08');

    return [
        'branch' => $branch,
        'employee' => $employee,
        'period' => $period,
        'admin' => PayrollFixtures::admin(),
    ];
}

test('un bono aprobado aparece en la pre-nómina y no se duplica al recalcular', function (): void {
    $scenario = adjustmentScenario('ADJ-A');
    $bonusConcept = PayrollConcept::query()->where('code', 'BONO_PUNTUALIDAD')->sole();

    $this->actingAs($scenario['admin'])
        ->post(route('payroll.periods.calculate', $scenario['period']))
        ->assertSessionHas('success');

    $this->actingAs($scenario['admin'])
        ->post(route('adjustments.store'), adjustmentPayload(
            $scenario['employee']->id,
            $bonusConcept->id,
            $scenario['period']->id,
            ['reason' => 'Bono de puntualidad ficticio para la prueba.'],
        ))
        ->assertSessionHas('success');

    $adjustment = EmployeePayrollAdjustment::query()->sole();

    expect($adjustment->status)->toBe(AdjustmentStatus::Pending)
        ->and($adjustment->payroll_period_id)->toBe($scenario['period']->id);

    $this->actingAs($scenario['admin'])
        ->post(route('adjustments.decide', $adjustment), ['decision' => 'approved'])
        ->assertSessionHas('success');

    expect($adjustment->refresh()->status)->toBe(AdjustmentStatus::Approved);

    $this->actingAs($scenario['admin'])
        ->post(route('payroll.periods.calculate', $scenario['period']))
        ->assertSessionHas('success');

    $item = PayrollItem::query()->sole();
    $bonusRows = PayrollItemConcept::query()->where('code', 'BONO_PUNTUALIDAD')->get();

    expect($item->bonus_amount)->toBe('500.00')
        ->and($item->base_amount)->toBe('2900.00')
        ->and($item->net_amount)->toBe('3400.00')
        ->and($bonusRows)->toHaveCount(1)
        ->and($bonusRows[0]->amount)->toBe('500.00')
        ->and($bonusRows[0]->source_type)->toBe(EmployeePayrollAdjustment::class)
        ->and($bonusRows[0]->source_id)->toBe($adjustment->id)
        ->and($adjustment->refresh()->applied_payroll_item_id)->toBe($item->id);

    $this->actingAs($scenario['admin'])
        ->post(route('payroll.periods.calculate', $scenario['period']))
        ->assertSessionHas('success');

    expect(PayrollItem::query()->count())->toBe(1)
        ->and(PayrollItemConcept::query()->where('code', 'BONO_PUNTUALIDAD')->count())->toBe(1)
        ->and(PayrollItem::query()->sole()->bonus_amount)->toBe('500.00')
        ->and(PayrollItem::query()->sole()->net_amount)->toBe('3400.00');
});

test('un bono pendiente no se aplica a la pre-nómina', function (): void {
    $scenario = adjustmentScenario('ADJ-B');
    $bonusConcept = PayrollConcept::query()->where('code', 'BONO_PUNTUALIDAD')->sole();

    EmployeePayrollAdjustment::query()->create([
        'employee_id' => $scenario['employee']->id,
        'payroll_period_id' => $scenario['period']->id,
        'payroll_concept_id' => $bonusConcept->id,
        'amount' => '500.00',
        'quantity' => '1.00',
        'reason' => 'Bono pendiente ficticio.',
        'status' => AdjustmentStatus::Pending,
        'created_by' => $scenario['admin']->id,
        'is_sensitive' => false,
    ]);

    $this->actingAs($scenario['admin'])
        ->post(route('payroll.periods.calculate', $scenario['period']))
        ->assertSessionHas('success');

    expect(PayrollItem::query()->sole()->bonus_amount)->toBe('0.00')
        ->and(PayrollItemConcept::query()->where('code', 'BONO_PUNTUALIDAD')->count())->toBe(0)
        ->and(EmployeePayrollAdjustment::query()->sole()->applied_payroll_item_id)->toBeNull();
});

test('una penalización sensible exige adjustments.approve para registrarse y decidirse', function (): void {
    $scenario = adjustmentScenario('ADJ-C');
    $penaltyConcept = PayrollConcept::query()->where('code', 'PENALIZACION_ADMIN')->sole();
    $bonusConcept = PayrollConcept::query()->where('code', 'BONO_PUNTUALIDAD')->sole();
    $manager = PayrollFixtures::user([$scenario['branch']->id]);

    $this->actingAs($manager)
        ->post(route('adjustments.store'), adjustmentPayload(
            $scenario['employee']->id,
            $penaltyConcept->id,
            $scenario['period']->id,
            ['amount' => '300.00', 'reason' => 'Penalización sensible ficticia.'],
        ))
        ->assertForbidden();

    expect(EmployeePayrollAdjustment::query()->count())->toBe(0);

    $this->actingAs($manager)
        ->post(route('adjustments.store'), adjustmentPayload(
            $scenario['employee']->id,
            $bonusConcept->id,
            $scenario['period']->id,
            ['reason' => 'Bono propuesto por la sucursal (ficticio).'],
        ))
        ->assertSessionHas('success');

    $this->actingAs($scenario['admin'])
        ->post(route('adjustments.store'), adjustmentPayload(
            $scenario['employee']->id,
            $penaltyConcept->id,
            $scenario['period']->id,
            ['amount' => '300.00', 'reason' => 'Penalización sensible ficticia.'],
        ))
        ->assertSessionHas('success');

    $penalty = EmployeePayrollAdjustment::query()
        ->where('reason', 'Penalización sensible ficticia.')
        ->sole();

    expect($penalty->is_sensitive)->toBeTrue()
        ->and($penalty->status)->toBe(AdjustmentStatus::Pending);

    $this->actingAs($manager)
        ->post(route('adjustments.decide', $penalty), ['decision' => 'approved'])
        ->assertForbidden();

    expect($penalty->refresh()->status)->toBe(AdjustmentStatus::Pending);

    $this->actingAs($scenario['admin'])
        ->post(route('adjustments.decide', $penalty), ['decision' => 'approved'])
        ->assertSessionHas('success');

    expect($penalty->refresh()->status)->toBe(AdjustmentStatus::Approved)
        ->and($penalty->approved_by)->toBe($scenario['admin']->id);
});

test('un ajuste capturado desde el periodo hereda el periodo de la ruta', function (): void {
    $scenario = adjustmentScenario('ADJ-D');
    $bonusConcept = PayrollConcept::query()->where('code', 'BONO_PUNTUALIDAD')->sole();

    $this->actingAs($scenario['admin'])
        ->post(route('payroll.periods.adjustments.store', $scenario['period']), [
            'employee_id' => $scenario['employee']->id,
            'payroll_concept_id' => $bonusConcept->id,
            'amount' => '250.00',
            'quantity' => '1.00',
            'reason' => 'Bono capturado desde el detalle del periodo (ficticio).',
        ])
        ->assertSessionHas('success');

    $adjustment = EmployeePayrollAdjustment::query()->sole();

    expect($adjustment->payroll_period_id)->toBe($scenario['period']->id)
        ->and($adjustment->status)->toBe(AdjustmentStatus::Pending)
        ->and($adjustment->is_sensitive)->toBeFalse();
});

test('el listado de ajustes respeta el aislamiento por sucursal', function (): void {
    $north = PayrollFixtures::branch('ADJ-NORTE', 'Sucursal Ajustes Norte');
    $south = PayrollFixtures::branch('ADJ-SUR', 'Sucursal Ajustes Sur');
    $northEmployee = PayrollFixtures::employee($north, 'EMP-ADJ-N', '3000.00');
    $southEmployee = PayrollFixtures::employee($south, 'EMP-ADJ-S', '7000.00');
    $admin = PayrollFixtures::admin();
    $bonusConcept = PayrollConcept::query()->where('code', 'BONO_PUNTUALIDAD')->sole();

    foreach ([$northEmployee, $southEmployee] as $index => $employee) {
        EmployeePayrollAdjustment::query()->create([
            'employee_id' => $employee->id,
            'payroll_period_id' => null,
            'payroll_concept_id' => $bonusConcept->id,
            'amount' => (string) (500 + $index),
            'quantity' => '1.00',
            'reason' => 'Bono ficticio de aislamiento '.$index,
            'status' => AdjustmentStatus::Pending,
            'created_by' => $admin->id,
            'is_sensitive' => false,
        ]);
    }

    $manager = PayrollFixtures::user([$north->id]);

    $this->actingAs($manager)
        ->get(route('adjustments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('adjustments/Index', false)
            ->where('adjustments.meta.total', 1)
            ->where('adjustments.data.0.employee.id', $northEmployee->id)
            ->where('can.manage', true)
            ->where('can.approve', false)
            ->has('warning'));
});
