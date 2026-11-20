<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
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

/**
 * Escenario base del recibo: sucursal con horario lunes–sábado, colaborador con
 * sueldo semanal de 2900.00, asistencia completa y periodo semanal calculado.
 *
 * @return array{branch: Branch, employee: Employee, period: PayrollPeriod, admin: User}
 */
function payrollReceiptScenario(string $code = 'REC-A'): array
{
    $branch = PayrollFixtures::branch($code, 'Sucursal Recibos '.$code);
    $employee = PayrollFixtures::employee($branch, 'EMP-'.$code, '2900.00', 'weekly');
    PayrollFixtures::fullWeek($employee, $branch);

    $schedule = PayrollFixtures::schedule('weekly', 'Esquema recibos '.$code);

    return [
        'branch' => $branch,
        'employee' => $employee,
        'period' => PayrollFixtures::period($schedule, '2026-11-02', '2026-11-08'),
        'admin' => PayrollFixtures::admin(),
    ];
}

test('el recibo imprime conceptos y totales con aviso de documento preliminar', function (): void {
    $scenario = payrollReceiptScenario('REC-A');

    $this->actingAs($scenario['admin'])
        ->post(route('payroll.periods.calculate', $scenario['period']))
        ->assertSessionHas('success');

    $item = PayrollItem::query()->sole();

    $response = $this->actingAs($scenario['admin'])
        ->get(route('payroll.receipt', $item));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('payroll/Receipt', false)
        ->where('item.employee.employee_number', 'EMP-REC-A')
        ->where('item.employee.full_name', $scenario['employee']->fullName())
        ->where('item.employee.position', $scenario['employee']->position)
        ->where('item.branch.id', $scenario['branch']->id)
        ->where('item.period.start_date', '2026-11-02')
        ->where('item.period.end_date', '2026-11-08')
        ->where('item.totals.earnings', '2900.00')
        ->where('item.totals.deductions', '0.00')
        ->where('item.totals.net', '2900.00')
        ->where('item.concepts', fn ($concepts): bool => count($concepts) === 1
            && $concepts[0]['code'] === 'SUELDO_BASE'
            && $concepts[0]['type'] === 'earning'
            && $concepts[0]['amount'] === '2900.00'
            && $concepts[0]['unit_amount'] === '2900.00')
        ->where('company.name', config('app.name'))
        ->where('company.logo_url', '/images/logo.jpeg')
        ->where('notice', config('payroll.receipt_notice'))
        ->where('notice', fn (string $notice): bool => str_contains($notice, 'preliminar') && str_contains($notice, 'CFDI'))
        ->has('signature_lines', 2)
        ->missing('item.uuid')
        ->missing('uuid'));

    // El recibo no contiene identificadores fiscales inventados.
    $content = $response->getContent();

    expect($content)->not->toContain('uuid')
        ->and($content)->not->toContain('UUID')
        ->and($content)->not->toContain('RFC')
        ->and($content)->not->toContain('timbre');
});

test('un usuario de otra sucursal no puede abrir el recibo', function (): void {
    $scenario = payrollReceiptScenario('REC-B');

    $this->actingAs($scenario['admin'])
        ->post(route('payroll.periods.calculate', $scenario['period']))
        ->assertSessionHas('success');

    $item = PayrollItem::query()->sole();
    $south = PayrollFixtures::branch('REC-SUR', 'Sucursal Recibos Sur');
    $manager = PayrollFixtures::user([$south->id]);

    $this->actingAs($manager)
        ->get(route('payroll.receipt', $item))
        ->assertForbidden();
});

test('el recibo exige el permiso payroll.view', function (): void {
    $scenario = payrollReceiptScenario('REC-C');

    $this->actingAs($scenario['admin'])
        ->post(route('payroll.periods.calculate', $scenario['period']))
        ->assertSessionHas('success');

    $item = PayrollItem::query()->sole();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('payroll.receipt', $item))
        ->assertForbidden();
});
