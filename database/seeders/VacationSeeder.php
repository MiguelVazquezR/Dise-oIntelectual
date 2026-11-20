<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Vacations\VacationPeriodGenerator;
use App\Services\Vacations\VacationRequestService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * Datos ficticios de vacaciones por aniversario (LFT art. 76, D-11).
 *
 * Genera los periodos de todos los colaboradores existentes y registra
 * solicitudes de ejemplo en distintos estados usando los servicios de dominio,
 * de modo que las aprobadas crean también su incidencia de asistencia.
 *
 * Idempotente: los periodos se crean por `(employee_id, period_start)` y las
 * solicitudes se omiten si ya existe una con el mismo colaborador y rango.
 *
 *     php artisan db:seed --class=VacationSeeder
 */
class VacationSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::query()->orderBy('employee_number')->get();

        if ($employees->isEmpty()) {
            $this->command?->warn('No hay colaboradores; ejecuta primero EmployeeSeeder.');

            return;
        }

        $generator = app(VacationPeriodGenerator::class);
        $periodsCreated = 0;

        foreach ($employees as $employee) {
            $periodsCreated += $generator->forEmployee($employee)['created'];
        }

        $this->command?->info("Periodos de vacaciones: {$periodsCreated} nuevos para {$employees->count()} colaboradores.");

        $actor = $this->resolveActor();

        if (! $actor instanceof User) {
            $this->command?->warn('No hay usuarios registrados; se omiten las solicitudes de ejemplo.');

            return;
        }

        $service = app(VacationRequestService::class);
        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->requests() as $definition) {
            $employee = Employee::query()->where('employee_number', $definition['employee'])->first();

            if (! $employee instanceof Employee) {
                continue;
            }

            $exists = VacationRequest::query()
                ->where('employee_id', $employee->id)
                ->whereDate('start_date', $definition['start_date'])
                ->whereDate('end_date', $definition['end_date'])
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            try {
                $request = $service->request($employee, [
                    'start_date' => $definition['start_date'],
                    'end_date' => $definition['end_date'],
                    'notes' => $definition['notes'] ?? null,
                ], $actor);

                if ($definition['status'] === 'approved') {
                    $service->approve($request, $actor, $definition['decision_notes'] ?? null);
                } elseif ($definition['status'] === 'rejected') {
                    $service->reject($request, $actor, $definition['decision_notes'] ?? null);
                }

                $created++;
            } catch (ValidationException $exception) {
                $failed++;
                $this->command?->warn(
                    "Solicitud {$definition['employee']} {$definition['start_date']}: ".implode(' ', $exception->validator->errors()->all()),
                );
            }
        }

        $this->command?->info(
            "Solicitudes de vacaciones ficticias: {$created} nuevas, {$skipped} ya existentes, {$failed} omitidas por validación.",
        );
    }

    /**
     * Usuario que registra las solicitudes demo (administrador si existe).
     */
    private function resolveActor(): ?User
    {
        return User::query()->where('email', DemoUserSeeder::DEMO_EMAIL)->first()
            ?? User::query()->orderBy('id')->first();
    }

    /**
     * Solicitudes ficticias: aprobadas, pendientes y rechazadas en periodos con
     * aniversarios distintos.
     *
     * @return array<int, array<string, mixed>>
     */
    private function requests(): array
    {
        return [
            [
                'employee' => 'EMP-0001',
                'start_date' => '2026-12-21',
                'end_date' => '2026-12-24',
                'status' => 'approved',
                'notes' => 'Vacaciones de fin de año.',
                'decision_notes' => 'Aprobadas por Recursos Humanos (demo).',
            ],
            [
                'employee' => 'EMP-0002',
                'start_date' => '2027-01-11',
                'end_date' => '2027-01-15',
                'status' => 'pending',
                'notes' => 'Trámite personal.',
            ],
            [
                'employee' => 'EMP-0003',
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-18',
                'status' => 'rejected',
                'notes' => 'Solicitud en temporada alta.',
                'decision_notes' => 'Rechazada por carga operativa (demo).',
            ],
            [
                'employee' => 'EMP-0005',
                'start_date' => '2026-11-02',
                'end_date' => '2026-11-06',
                'status' => 'approved',
                'notes' => 'Vacaciones ya disfrutadas.',
                'decision_notes' => 'Aprobadas por Recursos Humanos (demo).',
            ],
            [
                'employee' => 'EMP-0010',
                'start_date' => '2027-02-15',
                'end_date' => '2027-02-19',
                'status' => 'pending',
                'notes' => 'Viaje familiar.',
            ],
            [
                'employee' => 'EMP-0013',
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-03',
                'status' => 'approved',
                'notes' => 'Asunto personal.',
                'decision_notes' => 'Aprobadas por Recursos Humanos (demo).',
            ],
        ];
    }
}
