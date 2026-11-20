<?php

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollRunStatus;
use App\Models\AuditLog;
use App\Models\EmployeeCompensation;
use App\Models\PayrollItem;
use App\Models\PayrollItemConcept;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollCalculator;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PayrollFixtures;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-19 09:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('calcula sueldo, horas extra y falta injustificada con desglose trazable', function (): void {
    $branch = PayrollFixtures::branch('CALC-A', 'Sucursal Cálculo');
    $employee = PayrollFixtures::employee($branch, 'EMP-CALC-01', '2900.00', 'weekly');
    $admin = PayrollFixtures::admin();

    PayrollFixtures::clock($employee, $branch, '2026-11-02');
    PayrollFixtures::clock($employee, $branch, '2026-11-03', '09:00', '19:00');
    PayrollFixtures::clock($employee, $branch, '2026-11-05');
    PayrollFixtures::clock($employee, $branch, '2026-11-06');
    PayrollFixtures::clock($employee, $branch, '2026-11-07');

    $schedule = PayrollFixtures::schedule('weekly');
    $period = PayrollFixtures::period($schedule, '2026-11-02', '2026-11-08');

    $this->actingAs($admin)
        ->post(route('payroll.periods.calculate', $period))
        ->assertSessionHas('success');

    expect($period->refresh()->status)->toBe(PayrollPeriodStatus::Calculated);

    $run = PayrollRun::query()->sole();
    $item = PayrollItem::query()->sole();

    expect($run->branch_id)->toBe($branch->id)
        ->and($run->status)->toBe(PayrollRunStatus::Calculated)
        ->and($run->calculated_by)->toBe($admin->id)
        ->and($item->employee_id)->toBe($employee->id)
        ->and($item->base_amount)->toBe('2900.00')
        ->and($item->overtime_minutes)->toBe(120)
        ->and($item->overtime_amount)->toBe('120.83')
        ->and($item->absence_days)->toBe('1.00')
        ->and($item->absence_deduction)->toBe('483.33')
        ->and($item->late_minutes)->toBe(0)
        ->and($item->net_amount)->toBe('2537.50')
        ->and($run->total_earnings)->toBe('3020.83')
        ->and($run->total_deductions)->toBe('483.33')
        ->and($run->total_net)->toBe('2537.50');

    $concepts = $item->concepts->sortBy('id')->values();

    expect($concepts)->toHaveCount(3)
        ->and($concepts[0]->code)->toBe(PayrollCalculator::CONCEPT_BASE)
        ->and($concepts[0]->amount)->toBe('2900.00')
        ->and($concepts[0]->source_type)->toBe(EmployeeCompensation::class)
        ->and($concepts[1]->code)->toBe(PayrollCalculator::CONCEPT_OVERTIME)
        ->and($concepts[1]->amount)->toBe('120.83')
        ->and($concepts[1]->source_type)->toBe('attendance_period_summary')
        ->and($concepts[2]->code)->toBe(PayrollCalculator::CONCEPT_ABSENCE)
        ->and($concepts[2]->amount)->toBe('483.33')
        ->and($concepts[0]->rule_applied)->toContain('2900.00');

    $snapshot = $run->rules_snapshot;

    expect($snapshot)->toBeArray()
        ->and($snapshot['rules_version'])->toBe(config('payroll.rules_version'))
        ->and($snapshot['factors']['overtime'])->toEqual(1.0)
        ->and($snapshot['factors']['absence'])->toEqual(1.0)
        ->and($snapshot['period']['natural_days'])->toBe(7)
        ->and($snapshot['items'][0]['overtime_minutes'])->toBe(120)
        ->and($snapshot['items'][0]['absence_days'])->toBe(1);

    $audit = AuditLog::query()->where('action', 'payroll.period.calculated')->latest('id')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->auditable_id)->toBe($period->id)
        ->and($audit->user_id)->toBe($admin->id);
});

test('el recálculo reemplaza conceptos y no los duplica', function (): void {
    $branch = PayrollFixtures::branch('CALC-B', 'Sucursal Recálculo');
    $employee = PayrollFixtures::employee($branch, 'EMP-CALC-02', '2900.00', 'weekly');
    $admin = PayrollFixtures::admin();

    PayrollFixtures::clock($employee, $branch, '2026-11-02');
    PayrollFixtures::clock($employee, $branch, '2026-11-03', '09:00', '19:00');
    PayrollFixtures::clock($employee, $branch, '2026-11-05');

    $schedule = PayrollFixtures::schedule('weekly');
    $period = PayrollFixtures::period($schedule, '2026-11-02', '2026-11-08');

    $this->actingAs($admin)->post(route('payroll.periods.calculate', $period))->assertSessionHas('success');

    $firstItemId = PayrollItem::query()->sole()->id;
    $firstConceptCount = PayrollItemConcept::query()->count();

    $this->actingAs($admin)->post(route('payroll.periods.calculate', $period))->assertSessionHas('success');

    expect($firstConceptCount)->toBe(3)
        ->and(PayrollRun::query()->count())->toBe(1)
        ->and(PayrollItem::query()->count())->toBe(1)
        ->and(PayrollItemConcept::query()->count())->toBe($firstConceptCount)
        ->and(PayrollItem::query()->sole()->id)->not->toBe($firstItemId)
        ->and($period->refresh()->status)->toBe(PayrollPeriodStatus::Calculated)
        ->and(AuditLog::query()->where('action', 'payroll.period.calculated')->count())->toBe(2);
});

test('un periodo cerrado rechaza el recálculo con 409 sin tocar los importes', function (): void {
    $branch = PayrollFixtures::branch('CALC-C', 'Sucursal Cierre');
    $employee = PayrollFixtures::employee($branch, 'EMP-CALC-03', '2900.00', 'weekly');
    $admin = PayrollFixtures::admin();

    PayrollFixtures::fullWeek($employee, $branch);

    $schedule = PayrollFixtures::schedule('weekly');
    $period = PayrollFixtures::period($schedule, '2026-11-02', '2026-11-08');

    $this->actingAs($admin)->post(route('payroll.periods.calculate', $period))->assertSessionHas('success');
    $this->actingAs($admin)->post(route('payroll.periods.review', $period))->assertSessionHas('success');
    $this->actingAs($admin)
        ->post(route('payroll.periods.close', $period), ['confirm' => true])
        ->assertSessionHas('success');

    expect($period->refresh()->status)->toBe(PayrollPeriodStatus::Closed);

    $itemCountBefore = PayrollItem::query()->count();
    $netBefore = PayrollItem::query()->sole()->net_amount;

    $response = $this->actingAs($admin)->post(route('payroll.periods.calculate', $period), ['force' => true]);

    $response->assertStatus(409);
    $response->assertSessionHas('error');

    $run = PayrollRun::query()->sole();

    expect($period->refresh()->status)->toBe(PayrollPeriodStatus::Closed)
        ->and(PayrollItem::query()->count())->toBe($itemCountBefore)
        ->and(PayrollItem::query()->sole()->net_amount)->toBe($netBefore)
        ->and($run->status)->toBe(PayrollRunStatus::Closed)
        ->and($run->rules_snapshot['frozen']['totals']['net'])->toBe($run->total_net)
        ->and($run->rules_snapshot['frozen']['items'])->toHaveCount(1);
});

test('crea un periodo manual y rechaza el duplicado del esquema y la fecha de inicio', function (): void {
    $schedule = PayrollFixtures::schedule('weekly', 'Esquema alta manual');
    $admin = PayrollFixtures::admin();

    $payload = [
        'payroll_schedule_id' => $schedule->id,
        'start_date' => '2026-11-02',
        'end_date' => '2026-11-08',
        'payment_date' => '2026-11-10',
    ];

    $this->actingAs($admin)->post(route('payroll.periods.store'), $payload)->assertSessionHas('success');

    $period = PayrollPeriod::query()->sole();

    expect($period->start_date->toDateString())->toBe('2026-11-02')
        ->and($period->status)->toBe(PayrollPeriodStatus::Open);

    $this->actingAs($admin)
        ->post(route('payroll.periods.store'), $payload)
        ->assertSessionHasErrors('start_date');

    expect(PayrollPeriod::query()->count())->toBe(1);
});

test('un periodo revisado exige force para recalcularse', function (): void {
    $branch = PayrollFixtures::branch('CALC-D', 'Sucursal Revisión');
    $employee = PayrollFixtures::employee($branch, 'EMP-CALC-04', '2900.00', 'weekly');
    $admin = PayrollFixtures::admin();

    PayrollFixtures::fullWeek($employee, $branch);

    $schedule = PayrollFixtures::schedule('weekly', 'Esquema revisión');
    $period = PayrollFixtures::period($schedule, '2026-11-02', '2026-11-08');

    $this->actingAs($admin)->post(route('payroll.periods.calculate', $period))->assertSessionHas('success');
    $this->actingAs($admin)->post(route('payroll.periods.review', $period))->assertSessionHas('success');

    $itemCount = PayrollItem::query()->count();

    $this->actingAs($admin)
        ->post(route('payroll.periods.calculate', $period))
        ->assertSessionHas('error');

    expect($period->refresh()->status)->toBe(PayrollPeriodStatus::Reviewed)
        ->and(PayrollItem::query()->count())->toBe($itemCount);

    $this->actingAs($admin)
        ->post(route('payroll.periods.calculate', $period), ['force' => true, 'note' => 'Recálculo autorizado en la prueba.'])
        ->assertSessionHas('success');

    expect($period->refresh()->status)->toBe(PayrollPeriodStatus::Calculated)
        ->and(PayrollRun::query()->sole()->rules_snapshot['forced'])->toBeTrue()
        ->and(PayrollRun::query()->sole()->rules_snapshot['note'])->toBe('Recálculo autorizado en la prueba.');
});

test('un usuario de sucursal solo calcula y ve sus sucursales', function (): void {
    $north = PayrollFixtures::branch('CALC-NORTE', 'Sucursal Norte');
    $south = PayrollFixtures::branch('CALC-SUR', 'Sucursal Sur');

    $northEmployee = PayrollFixtures::employee($north, 'EMP-N-01', '3000.00', 'weekly');
    $southEmployee = PayrollFixtures::employee($south, 'EMP-S-01', '7000.00', 'weekly');

    PayrollFixtures::fullWeek($northEmployee, $north);
    PayrollFixtures::fullWeek($southEmployee, $south);

    $schedule = PayrollFixtures::schedule('weekly');
    $period = PayrollFixtures::period($schedule, '2026-11-02', '2026-11-08');

    $manager = PayrollFixtures::user([$north->id]);
    $southManager = PayrollFixtures::user([$south->id]);

    $this->actingAs($manager)
        ->post(route('payroll.periods.calculate', $period))
        ->assertSessionHas('success');

    expect(PayrollRun::query()->count())->toBe(1)
        ->and(PayrollRun::query()->sole()->branch_id)->toBe($north->id)
        ->and(PayrollItem::query()->count())->toBe(1)
        ->and(PayrollItem::query()->sole()->employee_id)->toBe($northEmployee->id);

    $this->actingAs($manager)
        ->get(route('payroll.periods.show', $period))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('payroll/Show', false)
            ->has('runs', 1)
            ->has('items', 1)
            ->where('runs.0.branch.id', $north->id)
            ->where('items.0.employee.id', $northEmployee->id)
            ->where('items.0.totals.net', '3000.00')
            ->where('totals.earnings', '3000.00')
            ->where('totals.net', '3000.00')
            ->where('can.calculate', true)
            ->where('notice', fn (string $notice): bool => str_contains(
                $notice,
                'Pre-nómina para revisión; no constituye CFDI ni cálculo fiscal definitivo',
            )));

    $this->actingAs($manager)
        ->get(route('payroll.periods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('payroll/Index', false)
            ->where('periods.meta.total', 1)
            ->where('periods.data.0.runs_count', 1)
            ->where('periods.data.0.totals.earnings', '3000.00')
            ->where('notice', fn (string $notice): bool => str_contains(
                $notice,
                'Pre-nómina para revisión; no constituye CFDI ni cálculo fiscal definitivo',
            )));

    $this->actingAs($southManager)
        ->get(route('payroll.periods.show', $period))
        ->assertForbidden();
});
