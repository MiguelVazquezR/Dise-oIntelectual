<?php

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceMethod;
use App\Enums\HolidayType;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\AttendanceEvent;
use App\Models\AttendanceIncident;
use App\Models\Branch;
use App\Models\BranchSchedule;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use App\Services\Attendance\AttendanceDayCalculator;
use App\Services\Attendance\AttendanceDayResult;
use App\Services\Attendance\ScheduleWindowResolver;
use App\Services\Attendance\WorkDateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @param  array<int, array{is_rest_day?: bool, tolerance?: int, segments?: array<int, array{0: string, 1: string, 2: string}>}>  $days
 */
function unitAttendanceBranchWith(array $days, string $code = 'UNIT-A', string $timezone = 'America/Mexico_City'): Branch
{
    $branch = Branch::factory()->create(['code' => $code, 'timezone' => $timezone]);

    $schedule = BranchSchedule::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Horario unitario',
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'is_active' => true,
    ]);

    foreach ($days as $weekday => $definition) {
        $day = $schedule->days()->create([
            'weekday' => $weekday,
            'is_rest_day' => $definition['is_rest_day'] ?? false,
            'late_tolerance_minutes' => $definition['tolerance'] ?? 10,
        ]);

        foreach (array_values($definition['segments'] ?? []) as $index => $segment) {
            $day->segments()->create([
                'start_time' => $segment[0],
                'end_time' => $segment[1],
                'kind' => $segment[2],
                'sort_order' => $index,
            ]);
        }
    }

    return $branch;
}

function unitAttendanceEmployee(Branch $branch, array $overrides = []): Employee
{
    return Employee::factory()->create(array_merge(['branch_id' => $branch->id], $overrides));
}

function unitAttendanceEvent(Employee $employee, AttendanceEventType $type, CarbonImmutable $localMoment, ?string $workDate = null): AttendanceEvent
{
    return AttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'type' => $type,
        'occurred_at' => $localMoment->utc(),
        'work_date' => $workDate ?? $localMoment->toDateString(),
        'method' => AttendanceMethod::Pin,
    ]);
}

function unitAttendanceCalculator(): AttendanceDayCalculator
{
    return new AttendanceDayCalculator(new ScheduleWindowResolver);
}

function unitAttendanceDate(string $date = '2026-11-02'): CarbonImmutable
{
    return CarbonImmutable::parse($date, 'America/Mexico_City');
}

/**
 * @return array<int, array{0: string, 1: string, 2: string}>
 */
function unitAttendanceOfficeSegments(): array
{
    return [
        ['09:00', '14:00', 'work'],
        ['14:00', '15:00', 'break'],
        ['15:00', '19:00', 'work'],
    ];
}

test('calcula el retardo descontando la tolerancia del día', function (): void {
    $branch = unitAttendanceBranchWith([1 => ['tolerance' => 10, 'segments' => unitAttendanceOfficeSegments()]]);
    $employee = unitAttendanceEmployee($branch);
    $date = unitAttendanceDate();

    unitAttendanceEvent($employee, AttendanceEventType::ClockIn, $date->setTime(9, 20));
    unitAttendanceEvent($employee, AttendanceEventType::BreakStart, $date->setTime(14, 0));
    unitAttendanceEvent($employee, AttendanceEventType::BreakEnd, $date->setTime(15, 0));
    unitAttendanceEvent($employee, AttendanceEventType::ClockOut, $date->setTime(19, 0));

    $result = unitAttendanceCalculator()->calculate($employee, $date);

    expect($result->status)->toBe(AttendanceDayResult::STATUS_COMPLETE)
        ->and($result->lateMinutes)->toBe(10)
        ->and($result->workedMinutes)->toBe(520)
        ->and($result->breakMinutes)->toBe(60)
        ->and($result->overtimeMinutes)->toBe(0)
        ->and($result->scheduledStart)->toBe('09:00')
        ->and($result->scheduledEnd)->toBe('19:00');
});

test('una entrada anticipada nunca produce retardo negativo', function (): void {
    $branch = unitAttendanceBranchWith([1 => ['segments' => unitAttendanceOfficeSegments()]]);
    $employee = unitAttendanceEmployee($branch);
    $date = unitAttendanceDate();

    unitAttendanceEvent($employee, AttendanceEventType::ClockIn, $date->setTime(8, 40));
    unitAttendanceEvent($employee, AttendanceEventType::ClockOut, $date->setTime(19, 0));

    $result = unitAttendanceCalculator()->calculate($employee, $date);

    expect($result->lateMinutes)->toBe(0)
        ->and($result->workedMinutes)->toBe(560);
});

test('descuenta el descanso real cuando existen eventos de descanso', function (): void {
    $branch = unitAttendanceBranchWith([1 => ['segments' => unitAttendanceOfficeSegments()]]);
    $employee = unitAttendanceEmployee($branch, ['employee_number' => 'EMP-UNIT-REAL']);
    $date = unitAttendanceDate();

    unitAttendanceEvent($employee, AttendanceEventType::ClockIn, $date->setTime(9, 0));
    unitAttendanceEvent($employee, AttendanceEventType::BreakStart, $date->setTime(14, 10));
    unitAttendanceEvent($employee, AttendanceEventType::BreakEnd, $date->setTime(15, 20));
    unitAttendanceEvent($employee, AttendanceEventType::ClockOut, $date->setTime(19, 0));

    $result = unitAttendanceCalculator()->calculate($employee, $date);

    expect($result->breakMinutes)->toBe(70)
        ->and($result->workedMinutes)->toBe(530)
        ->and($result->overtimeMinutes)->toBe(0);
});

test('usa el descanso programado cuando no hay eventos de descanso (S-06)', function (): void {
    $branch = unitAttendanceBranchWith([1 => ['segments' => unitAttendanceOfficeSegments()]]);
    $employee = unitAttendanceEmployee($branch, ['employee_number' => 'EMP-UNIT-SCHED']);
    $date = unitAttendanceDate();

    unitAttendanceEvent($employee, AttendanceEventType::ClockIn, $date->setTime(9, 0));
    unitAttendanceEvent($employee, AttendanceEventType::ClockOut, $date->setTime(19, 0));

    $result = unitAttendanceCalculator()->calculate($employee, $date);

    expect($result->breakMinutes)->toBe(60)
        ->and($result->workedMinutes)->toBe(540)
        ->and($result->overtimeMinutes)->toBe(0);
});

test('evalúa un turno nocturno que cruza medianoche', function (): void {
    $branch = unitAttendanceBranchWith([
        1 => ['tolerance' => 15, 'segments' => [['22:00', '06:00', 'work']]],
    ]);
    $employee = unitAttendanceEmployee($branch, ['employee_number' => 'EMP-UNIT-NOCHE']);
    $date = unitAttendanceDate();

    unitAttendanceEvent($employee, AttendanceEventType::ClockIn, $date->setTime(22, 20), $date->toDateString());
    unitAttendanceEvent(
        $employee,
        AttendanceEventType::ClockOut,
        $date->addDay()->setTime(6, 10),
        $date->toDateString(),
    );

    $result = unitAttendanceCalculator()->calculate($employee, $date);

    expect($result->scheduledStart)->toBe('22:00')
        ->and($result->scheduledEnd)->toBe('06:00')
        ->and($result->lateMinutes)->toBe(5)
        ->and($result->workedMinutes)->toBe(470)
        ->and($result->breakMinutes)->toBe(0)
        ->and($result->status)->toBe(AttendanceDayResult::STATUS_COMPLETE);
});

test('aplica la gracia del turno nocturno a la salida tardía', function (): void {
    $branch = unitAttendanceBranchWith([
        1 => ['tolerance' => 15, 'segments' => [['22:00', '06:00', 'work']]],
    ]);
    $employee = unitAttendanceEmployee($branch, ['employee_number' => 'EMP-UNIT-GRACIA']);
    $workDate = unitAttendanceDate();
    $clockIn = $workDate->setTime(22, 0);
    $clockOut = $workDate->addDay()->setTime(6, 45);

    $workDateResolver = new WorkDateResolver(new ScheduleWindowResolver);

    expect($workDateResolver->resolve($branch, $clockOut)->toDateString())
        ->toBe($workDate->toDateString())
        ->and($workDateResolver->resolve($branch, $clockOut->addMinutes(WorkDateResolver::DEFAULT_GRACE_MINUTES + 15))->toDateString())
        ->toBe($workDate->addDay()->toDateString());

    unitAttendanceEvent($employee, AttendanceEventType::ClockIn, $clockIn, $workDate->toDateString());
    unitAttendanceEvent($employee, AttendanceEventType::ClockOut, $clockOut, $workDate->toDateString());

    $result = unitAttendanceCalculator()->calculate($employee, $workDate);

    expect($result->status)->toBe(AttendanceDayResult::STATUS_COMPLETE)
        ->and($result->clockOut)->not->toBeNull()
        ->and($result->workedMinutes)->toBe(525)
        ->and($result->lateMinutes)->toBe(0);
});

test('propone horas extra solo cuando la jornada está completa y sobran minutos', function (): void {
    $branch = unitAttendanceBranchWith([1 => ['segments' => unitAttendanceOfficeSegments()]]);
    $employee = unitAttendanceEmployee($branch, ['employee_number' => 'EMP-UNIT-EXTRA']);
    $date = unitAttendanceDate();

    unitAttendanceEvent($employee, AttendanceEventType::ClockIn, $date->setTime(9, 0));
    unitAttendanceEvent($employee, AttendanceEventType::BreakStart, $date->setTime(14, 0));
    unitAttendanceEvent($employee, AttendanceEventType::BreakEnd, $date->setTime(15, 0));
    unitAttendanceEvent($employee, AttendanceEventType::ClockOut, $date->setTime(20, 30));

    $result = unitAttendanceCalculator()->calculate($employee, $date);

    expect($result->workedMinutes)->toBe(630)
        ->and($result->overtimeMinutes)->toBe(90)
        ->and($result->ruleApplied)->toContain('Horas extra')
        ->and($result->status)->toBe(AttendanceDayResult::STATUS_COMPLETE);
});

test('marca falta cuando hay día laborable sin marcajes ni exclusiones', function (): void {
    $branch = unitAttendanceBranchWith([1 => ['segments' => unitAttendanceOfficeSegments()]]);
    $employee = unitAttendanceEmployee($branch, ['employee_number' => 'EMP-UNIT-FALTA']);

    $result = unitAttendanceCalculator()->calculate($employee, unitAttendanceDate());

    expect($result->status)->toBe(AttendanceDayResult::STATUS_ABSENCE)
        ->and($result->workedMinutes)->toBe(0)
        ->and($result->lateMinutes)->toBe(0)
        ->and($result->ruleApplied)->toBeNull();
});

test('un día de descanso no se evalúa como falta', function (): void {
    $branch = unitAttendanceBranchWith([1 => ['is_rest_day' => true, 'segments' => []]]);
    $employee = unitAttendanceEmployee($branch, ['employee_number' => 'EMP-UNIT-DESCANSO']);

    $result = unitAttendanceCalculator()->calculate($employee, unitAttendanceDate());

    expect($result->status)->toBe(AttendanceDayResult::STATUS_REST_DAY)
        ->and($result->isRestDay)->toBeTrue()
        ->and($result->scheduledStart)->toBeNull();
});

test('un festivo de la sucursal excluye la falta', function (): void {
    $branch = unitAttendanceBranchWith([1 => ['segments' => unitAttendanceOfficeSegments()]]);
    $employee = unitAttendanceEmployee($branch, ['employee_number' => 'EMP-UNIT-FESTIVO']);
    $date = unitAttendanceDate();

    Holiday::query()->create([
        'name' => 'Festivo de prueba',
        'date' => $date,
        'type' => HolidayType::Mandatory,
        'branch_id' => null,
    ]);

    $result = unitAttendanceCalculator()->calculate($employee, $date);

    expect($result->status)->toBe(AttendanceDayResult::STATUS_HOLIDAY);
});

test('una vacación aprobada y una incidencia aprobada excluyen la falta', function (): void {
    $branch = unitAttendanceBranchWith([
        1 => ['segments' => unitAttendanceOfficeSegments()],
        2 => ['segments' => unitAttendanceOfficeSegments()],
        3 => ['segments' => unitAttendanceOfficeSegments()],
    ]);
    $creator = User::factory()->create();
    $employee = unitAttendanceEmployee($branch, ['employee_number' => 'EMP-UNIT-INCIDENCIA']);
    $calendar = unitAttendanceDate();

    AttendanceIncident::query()->create([
        'employee_id' => $employee->id,
        'branch_id' => $branch->id,
        'type' => IncidentType::Vacation,
        'start_date' => $calendar,
        'end_date' => $calendar,
        'quantity' => '1.00',
        'status' => IncidentStatus::Approved,
        'created_by' => $creator->id,
    ]);

    $sickDay = $calendar->addDay();
    AttendanceIncident::query()->create([
        'employee_id' => $employee->id,
        'branch_id' => $branch->id,
        'type' => IncidentType::SickLeave,
        'start_date' => $sickDay,
        'end_date' => $sickDay,
        'quantity' => '1.00',
        'status' => IncidentStatus::Approved,
        'created_by' => $creator->id,
    ]);

    $unjustifiedDay = $calendar->addDays(2);
    AttendanceIncident::query()->create([
        'employee_id' => $employee->id,
        'branch_id' => $branch->id,
        'type' => IncidentType::UnjustifiedAbsence,
        'start_date' => $unjustifiedDay,
        'end_date' => $unjustifiedDay,
        'quantity' => '1.00',
        'status' => IncidentStatus::Approved,
        'created_by' => $creator->id,
    ]);

    $calculator = unitAttendanceCalculator();

    expect($calculator->calculate($employee, $calendar)->status)->toBe(AttendanceDayResult::STATUS_VACATION)
        ->and($calculator->calculate($employee, $sickDay)->status)->toBe(AttendanceDayResult::STATUS_INCIDENT)
        ->and($calculator->calculate($employee, $unjustifiedDay)->status)->toBe(AttendanceDayResult::STATUS_ABSENCE);
});

test('una incidencia pendiente no excluye la falta', function (): void {
    $branch = unitAttendanceBranchWith([1 => ['segments' => unitAttendanceOfficeSegments()]]);
    $creator = User::factory()->create();
    $employee = unitAttendanceEmployee($branch, ['employee_number' => 'EMP-UNIT-PENDIENTE']);
    $date = unitAttendanceDate();

    AttendanceIncident::query()->create([
        'employee_id' => $employee->id,
        'branch_id' => $branch->id,
        'type' => IncidentType::Vacation,
        'start_date' => $date,
        'end_date' => $date,
        'quantity' => '1.00',
        'status' => IncidentStatus::Pending,
        'created_by' => $creator->id,
    ]);

    $result = unitAttendanceCalculator()->calculate($employee, $date);

    expect($result->status)->toBe(AttendanceDayResult::STATUS_ABSENCE)
        ->and($result->incidents)->toHaveCount(1);
});
