<?php

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\AttendanceIncident;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollItemConcept;
use App\Models\PayrollPeriod;
use App\Models\VacationPeriod;
use App\Models\VacationRequest;
use App\Services\Payroll\PayrollCalculator;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PayrollFixtures;

/**
 * Flujo de extremo a extremo de las exclusiones de faltas (§3.8–3.11):
 * incidencia aprobada y vacaciones aprobadas deben quitar la falta del listado
 * de asistencias y del descuento de la pre-nómina, sin duplicar conceptos.
 *
 * Semana del contrato de prueba: lunes 2 a sábado 7 de noviembre de 2026 con
 * horario 09:00–17:00 (domingo descanso). El jueves 5 no hay marcajes.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-19 09:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Colaborador con cinco de seis días trabajados: falta el jueves 2026-11-05.
 *
 * @return array{0: Branch, 1: Employee, 2: PayrollPeriod}
 */
function exclusionFlowEmployee(): array
{
    $branch = PayrollFixtures::branch('EXC-A', 'Sucursal Exclusiones');
    $employee = PayrollFixtures::employee($branch, 'EMP-EXC-01', '2900.00', 'weekly');

    foreach (['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-06', '2026-11-07'] as $workDate) {
        PayrollFixtures::clock($employee, $branch, $workDate);
    }

    $schedule = PayrollFixtures::schedule('weekly', 'Esquema exclusiones');

    return [$branch, $employee, PayrollFixtures::period($schedule, '2026-11-02', '2026-11-08')];
}

test('una incidencia aprobada quita la falta del listado y del descuento de la pre-nómina', function (): void {
    [$branch, $employee, $period] = exclusionFlowEmployee();
    $admin = PayrollFixtures::admin();

    $this->actingAs($admin)
        ->get("/attendance/employee/{$employee->id}?from=2026-11-02&to=2026-11-08")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('attendance/Employee', false)
            ->where('days.3.work_date', '2026-11-05')
            ->where('days.3.status', 'absence')
            ->where('totals.absences', 1));

    $this->actingAs($admin)->post(route('payroll.periods.calculate', $period))->assertSessionHas('success');

    expect(PayrollItem::query()->sole()->absence_days)->toBe('1.00')
        ->and(PayrollItemConcept::query()->where('code', PayrollCalculator::CONCEPT_ABSENCE)->count())->toBe(1);

    $this->actingAs($admin)
        ->post(route('incidents.store'), [
            'employee_id' => $employee->id,
            'type' => IncidentType::JustifiedAbsence->value,
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-05',
            'quantity' => '1',
            'reason' => 'Cita médica con comprobante (dato ficticio).',
        ])
        ->assertSessionHas('success');

    $incident = AttendanceIncident::query()->sole();

    expect($incident->branch_id)->toBe($branch->id)
        ->and($incident->status)->toBe(IncidentStatus::Pending);

    $this->actingAs($admin)
        ->post(route('incidents.decide', $incident), [
            'decision' => 'approved',
            'notes' => 'Comprobante validado (dato ficticio).',
        ])
        ->assertSessionHas('success');

    expect($incident->refresh()->status)->toBe(IncidentStatus::Approved);

    $this->actingAs($admin)
        ->get("/attendance/employee/{$employee->id}?from=2026-11-02&to=2026-11-08")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('days.3.status', 'incident')
            ->where('days.3.incidents.0.type', 'justified_absence')
            ->where('totals.absences', 0));

    $this->actingAs($admin)
        ->get(route('attendance.index', ['from' => '2026-11-02', 'to' => '2026-11-08']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->where('summary.absence_count', 0));

    $this->actingAs($admin)->post(route('payroll.periods.calculate', $period))->assertSessionHas('success');

    $item = PayrollItem::query()->sole();

    expect($item->absence_days)->toBe('0.00')
        ->and($item->absence_deduction)->toBe('0.00')
        ->and($item->base_amount)->toBe('2900.00')
        ->and($item->net_amount)->toBe('2900.00')
        ->and(PayrollItemConcept::query()->where('code', PayrollCalculator::CONCEPT_ABSENCE)->count())->toBe(0)
        ->and(PayrollItemConcept::query()->count())->toBe(1);
});

test('una vacación aprobada excluye la falta de la pre-nómina y queda trazada en el snapshot', function (): void {
    [$branch, $employee, $period] = exclusionFlowEmployee();
    $admin = PayrollFixtures::admin();

    VacationPeriod::query()->create([
        'employee_id' => $employee->id,
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
        'anniversary_years' => 1,
        'days_granted' => '12.00',
        'days_adjustment' => '0.00',
        'legal_reference' => 'LFT art. 76 (prueba)',
    ]);

    $this->actingAs($admin)
        ->post(route('vacations.requests.store'), [
            'employee_id' => $employee->id,
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-05',
            'notes' => 'Trámite personal (dato ficticio).',
        ])
        ->assertSessionHas('success');

    $vacationRequest = VacationRequest::query()->sole();

    expect($vacationRequest->days)->toBe('1.00');

    $this->actingAs($admin)
        ->post(route('vacations.requests.decide', $vacationRequest), [
            'decision' => 'approved',
            'notes' => 'Aprobada para la prueba.',
        ])
        ->assertSessionHas('success');

    $incident = AttendanceIncident::query()->sole();

    expect($incident->type)->toBe(IncidentType::Vacation)
        ->and($incident->status)->toBe(IncidentStatus::Approved)
        ->and($incident->branch_id)->toBe($branch->id);

    $this->actingAs($admin)
        ->get("/attendance/employee/{$employee->id}?from=2026-11-02&to=2026-11-08")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('days.3.work_date', '2026-11-05')
            ->where('days.3.status', 'vacation')
            ->where('totals.absences', 0)
            ->where('totals.vacations', 1));

    $this->actingAs($admin)->post(route('payroll.periods.calculate', $period))->assertSessionHas('success');

    $item = PayrollItem::query()->sole()->refresh();
    $snapshotIncidents = collect($item->run->rules_snapshot['incidents'] ?? []);

    expect($item->absence_days)->toBe('0.00')
        ->and($item->absence_deduction)->toBe('0.00')
        ->and($item->net_amount)->toBe('2900.00')
        ->and($snapshotIncidents->pluck('id'))->toContain($incident->id);
});
