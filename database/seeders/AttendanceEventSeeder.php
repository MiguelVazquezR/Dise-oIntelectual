<?php

namespace Database\Seeders;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Services\Attendance\ScheduleWindow;
use App\Services\Attendance\ScheduleWindowResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Marcajes ficticios de la última quincena cerrada (D-12).
 *
 * Genera la jornada base de cada colaborador activo a partir de su horario
 * vigente y aplica variaciones: retardos, descanso sin eventos (S-06), horas
 * extra propuestas, faltas y un turno nocturno que cruza medianoche (D-09).
 *
 * Idempotente: cada evento se identifica por (employee_id, type, occurred_at),
 * por lo que ejecutar el seeder varias veces no duplica filas. Las fechas se
 * anclan a la última quincena completa, así que la corrida es estable durante
 * el mes en curso.
 */
class AttendanceEventSeeder extends Seeder
{
    private int $created = 0;

    private int $updated = 0;

    public function run(): void
    {
        [$from, $to] = self::currentPeriod();

        $employees = Employee::query()
            ->with('branch')
            ->where('status', EmployeeStatus::Active)
            ->orderBy('employee_number')
            ->get();

        if ($employees->isEmpty()) {
            $this->command?->warn('No hay colaboradores demo; ejecuta primero EmployeeSeeder.');

            return;
        }

        /** @var ScheduleWindowResolver $resolver */
        $resolver = app(ScheduleWindowResolver::class);
        $dates = $this->dates($from, $to);
        $overrides = $this->overrides();
        $absences = $this->absences();

        foreach ($employees as $employee) {
            $timezone = $employee->branch->timezone;
            $absenceOffsets = $absences[$employee->employee_number] ?? [];

            foreach ($dates as $offset => $date) {
                if (in_array($offset, $absenceOffsets, true)) {
                    continue;
                }

                $workDate = CarbonImmutable::parse($date->toDateString(), $timezone);
                $window = $resolver->forBranchDate($employee->branch, $workDate);

                if (! $window->isWorkingDay()) {
                    continue;
                }

                $this->seedDay(
                    $employee,
                    $workDate,
                    $window,
                    $overrides[$employee->employee_number][$offset] ?? [],
                );
            }
        }

        $this->command?->info(sprintf(
            'Marcajes demo listos (%d nuevos, %d actualizados) del %s al %s.',
            $this->created,
            $this->updated,
            $from->toDateString(),
            $to->toDateString(),
        ));
    }

    /**
     * Última quincena cerrada (día 1–15 o 16–fin de mes).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function currentPeriod(): array
    {
        $today = CarbonImmutable::now('America/Mexico_City')->startOfDay();

        if ($today->day >= 16) {
            return [$today->startOfMonth(), $today->startOfMonth()->addDays(14)];
        }

        $previousMonth = $today->subMonthNoOverflow();

        return [
            $previousMonth->startOfMonth()->addDays(15),
            $previousMonth->endOfMonth()->startOfDay(),
        ];
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function dates(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [];

        for ($cursor = $from; $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            $dates[] = $cursor;
        }

        return $dates;
    }

    /**
     * @param  array{late_minutes?: int, overtime_minutes?: int, skip_break?: bool}  $override
     */
    private function seedDay(Employee $employee, CarbonImmutable $date, ScheduleWindow $window, array $override): void
    {
        $workDate = $date->toDateString();
        $start = $window->scheduledStart();
        $end = $window->scheduledEnd();

        if ($start === null || $end === null) {
            return;
        }

        $this->record(
            $employee,
            AttendanceEventType::ClockIn,
            $start->addMinutes((int) ($override['late_minutes'] ?? 0)),
            $workDate,
        );

        if (! (bool) ($override['skip_break'] ?? false)) {
            foreach ($window->breakSegments as $segment) {
                $this->record($employee, AttendanceEventType::BreakStart, $segment['start'], $workDate);
                $this->record($employee, AttendanceEventType::BreakEnd, $segment['end'], $workDate);
            }
        }

        $this->record(
            $employee,
            AttendanceEventType::ClockOut,
            $end->addMinutes((int) ($override['overtime_minutes'] ?? 0)),
            $workDate,
        );
    }

    private function record(Employee $employee, AttendanceEventType $type, CarbonImmutable $moment, string $workDate): void
    {
        $occurredAt = $moment->utc()->toDateTimeString();

        $event = AttendanceEvent::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'type' => $type,
                'occurred_at' => $occurredAt,
            ],
            [
                'branch_id' => $employee->branch_id,
                'work_date' => $workDate,
                'method' => AttendanceMethod::Pin,
                'validation_status' => AttendanceStatus::Valid,
                'device_label' => 'Checador demo',
                'metadata' => [
                    'gps_missing' => true,
                    'source' => 'demo',
                ],
            ],
        );

        if ($event->wasRecentlyCreated) {
            $this->created++;
        } else {
            $this->updated++;
        }
    }

    /**
     * Variaciones por colaborador y desplazamiento de día dentro del periodo.
     *
     * @return array<string, array<int, array{late_minutes?: int, overtime_minutes?: int, skip_break?: bool}>>
     */
    private function overrides(): array
    {
        return [
            'EMP-0002' => [3 => ['late_minutes' => 25]],
            'EMP-0003' => [9 => ['skip_break' => true]],
            'EMP-0004' => [10 => ['overtime_minutes' => 95]],
            'EMP-0010' => [1 => ['late_minutes' => 20]],
            'EMP-0021' => [8 => ['late_minutes' => 20]],
        ];
    }

    /**
     * Días sin marcajes (faltas y vacaciones de demostración).
     *
     * @return array<string, array<int, int>>
     */
    private function absences(): array
    {
        return [
            'EMP-0005' => [4, 5],
            'EMP-0006' => [9, 10, 11],
            'EMP-0007' => [8],
            'EMP-0008' => [12],
        ];
    }
}
