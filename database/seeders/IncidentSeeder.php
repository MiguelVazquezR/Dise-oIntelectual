<?php

namespace Database\Seeders;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\AttendanceIncident;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Incidencias ficticias de la última quincena cerrada (D-12).
 *
 * Incluye al menos una vacación aprobada que coincide con días sin marcajes
 * (EMP-0006) y variedad de tipos y estados para ejercitar el motor de jornada
 * y la pantalla de incidencias.
 *
 * Idempotente: la clave es (employee_id, type, start_date, end_date), igual que
 * la restricción única de la tabla.
 */
class IncidentSeeder extends Seeder
{
    public function run(): void
    {
        [$from] = AttendanceEventSeeder::currentPeriod();

        /** @var User|null $admin */
        $admin = User::query()->orderBy('id')->first();

        if (! $admin instanceof User) {
            $this->command?->warn('No hay usuarios; ejecuta primero DemoUserSeeder.');

            return;
        }

        $definitions = $this->definitions($from);

        $employees = Employee::query()
            ->whereIn('employee_number', array_values(array_unique(array_column($definitions, 'employee'))))
            ->get()
            ->keyBy('employee_number');

        if ($employees->isEmpty()) {
            $this->command?->warn('No hay colaboradores demo; ejecuta primero EmployeeSeeder.');

            return;
        }

        $created = 0;
        $updated = 0;

        foreach ($definitions as $definition) {
            /** @var Employee|null $employee */
            $employee = $employees->get($definition['employee']);

            if (! $employee instanceof Employee) {
                continue;
            }

            $decided = in_array($definition['status'], [IncidentStatus::Approved, IncidentStatus::Rejected], true);

            $startDate = CarbonImmutable::parse($definition['start'], 'America/Mexico_City')->startOfDay();
            $endDate = CarbonImmutable::parse($definition['end'], 'America/Mexico_City')->startOfDay();

            $incident = AttendanceIncident::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'type' => $definition['type'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                [
                    'branch_id' => $employee->branch_id,
                    'quantity' => $definition['quantity'],
                    'reason' => $definition['reason'],
                    'status' => $definition['status'],
                    'created_by' => $admin->id,
                    'decided_by' => $decided ? $admin->id : null,
                    'decided_at' => $decided ? CarbonImmutable::now() : null,
                    'notes' => $decided ? 'Decisión registrada en datos de demostración.' : null,
                ],
            );

            if ($incident->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $this->command?->info(sprintf(
            'Incidencias demo listas (%d nuevas, %d actualizadas), periodo desde %s.',
            $created,
            $updated,
            $from->toDateString(),
        ));
    }

    /**
     * @return array<int, array{employee: string, type: IncidentType, start: string, end: string, quantity: string, status: IncidentStatus, reason: string}>
     */
    private function definitions(CarbonImmutable $from): array
    {
        $day = fn (int $offset): string => $from->addDays($offset)->toDateString();

        return [
            [
                'employee' => 'EMP-0006',
                'type' => IncidentType::Vacation,
                'start' => $day(9),
                'end' => $day(11),
                'quantity' => '3.00',
                'status' => IncidentStatus::Approved,
                'reason' => 'Vacaciones programadas (demostración).',
            ],
            [
                'employee' => 'EMP-0008',
                'type' => IncidentType::SickLeave,
                'start' => $day(12),
                'end' => $day(12),
                'quantity' => '1.00',
                'status' => IncidentStatus::Approved,
                'reason' => 'Incapacidad temporal (demostración).',
            ],
            [
                'employee' => 'EMP-0005',
                'type' => IncidentType::PaidLeave,
                'start' => $day(4),
                'end' => $day(4),
                'quantity' => '1.00',
                'status' => IncidentStatus::Approved,
                'reason' => 'Permiso con goce de sueldo (demostración).',
            ],
            [
                'employee' => 'EMP-0003',
                'type' => IncidentType::JustifiedAbsence,
                'start' => $day(6),
                'end' => $day(6),
                'quantity' => '1.00',
                'status' => IncidentStatus::Pending,
                'reason' => 'Trámite personal por validar (demostración).',
            ],
            [
                'employee' => 'EMP-0004',
                'type' => IncidentType::WorkedHoliday,
                'start' => $day(6),
                'end' => $day(6),
                'quantity' => '8.00',
                'status' => IncidentStatus::Pending,
                'reason' => 'Apoyo en inventario (demostración).',
            ],
            [
                'employee' => 'EMP-0019',
                'type' => IncidentType::Other,
                'start' => $day(2),
                'end' => $day(2),
                'quantity' => '2.00',
                'status' => IncidentStatus::Pending,
                'reason' => 'Comisión de servicio (demostración).',
            ],
            [
                'employee' => 'EMP-0002',
                'type' => IncidentType::UnpaidLeave,
                'start' => $day(13),
                'end' => $day(13),
                'quantity' => '1.00',
                'status' => IncidentStatus::Cancelled,
                'reason' => 'Solicitud cancelada (demostración).',
            ],
            [
                'employee' => 'EMP-0005',
                'type' => IncidentType::UnjustifiedAbsence,
                'start' => $day(5),
                'end' => $day(5),
                'quantity' => '1.00',
                'status' => IncidentStatus::Rejected,
                'reason' => 'No procedente (demostración).',
            ],
        ];
    }
}
