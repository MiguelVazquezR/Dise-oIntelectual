<?php

namespace Database\Seeders;

use App\Enums\AdjustmentStatus;
use App\Enums\PayrollPeriodStatus;
use App\Models\Employee;
use App\Models\EmployeePayrollAdjustment;
use App\Models\PayrollConcept;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\PayrollCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Ajustes de demostración: bonos y penalizaciones (D-12).
 *
 * Incluye bonos aprobados y pendientes, una penalización sensible aprobada
 * (solo visible para quienes pueden aprobar ajustes), una penalización
 * pendiente y un rechazo. Los motivos son ficticios y de negocio; no contienen
 * datos personales.
 *
 * Idempotente por (`employee_id`, `payroll_concept_id`, `reason`): no duplica y
 * conserva el vínculo `applied_payroll_item_id` de corridas anteriores.
 * Si el periodo quincenal de demostración sigue `calculated` y hay ajustes
 * aprobados sin aplicar, recalcula la pre-nómina para que aparezcan.
 */
class AdjustmentSeeder extends Seeder
{
    public function run(): void
    {
        $actor = PayrollPeriodSeeder::demoActor();

        if (! $actor instanceof User) {
            $this->command?->warn('No hay usuarios; ejecuta primero DemoUserSeeder.');

            return;
        }

        $period = PayrollPeriodSeeder::currentBiweeklyPeriod();

        $employees = Employee::query()
            ->whereIn('employee_number', ['EMP-0001', 'EMP-0004', 'EMP-0007', 'EMP-0012', 'EMP-0013', 'EMP-0016'])
            ->get()
            ->keyBy('employee_number');

        $concepts = PayrollConcept::query()
            ->whereIn('code', ['BONO_PUNTUALIDAD', 'PENALIZACION_ADMIN'])
            ->get()
            ->keyBy('code');

        if ($employees->isEmpty() || $concepts->count() < 2) {
            $this->command?->warn('Faltan colaboradores o conceptos de demostración; ejecuta EmployeeSeeder y PayrollConceptSeeder.');

            return;
        }

        foreach ($this->definitions() as $definition) {
            $employee = $employees->get($definition['employee']);
            $concept = $concepts->get($definition['concept']);

            if (! $employee instanceof Employee || ! $concept instanceof PayrollConcept) {
                continue;
            }

            $targetPeriod = $definition['in_period'] && $period instanceof PayrollPeriod ? $period : null;
            $status = $definition['status'];
            $decided = in_array($status, [AdjustmentStatus::Approved, AdjustmentStatus::Rejected], true);

            EmployeePayrollAdjustment::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'payroll_concept_id' => $concept->id,
                    'reason' => $definition['reason'],
                ],
                [
                    'payroll_period_id' => $targetPeriod?->id,
                    'amount' => $definition['amount'],
                    'quantity' => '1.00',
                    'status' => $status,
                    'created_by' => $actor->id,
                    'approved_by' => $decided ? $actor->id : null,
                    'approved_at' => $decided ? CarbonImmutable::now() : null,
                    'is_sensitive' => $concept->is_sensitive,
                ],
            );
        }

        $this->applyToCalculatedPeriod($period, $actor);

        $this->command?->info('Ajustes de demostración listos (bonos y penalizaciones ficticios).');
    }

    /**
     * Recalcula el periodo quincenal de demostración si tiene ajustes aprobados
     * aún no aplicados, para que la pre-nómina los muestre.
     */
    private function applyToCalculatedPeriod(?PayrollPeriod $period, User $actor): void
    {
        if (! $period instanceof PayrollPeriod || $period->status !== PayrollPeriodStatus::Calculated) {
            return;
        }

        $pending = EmployeePayrollAdjustment::query()
            ->where('payroll_period_id', $period->id)
            ->where('status', AdjustmentStatus::Approved)
            ->whereNull('applied_payroll_item_id')
            ->exists();

        if (! $pending) {
            return;
        }

        try {
            /** @var PayrollCalculator $calculator */
            $calculator = app(PayrollCalculator::class);
            $outcome = $calculator->calculate(
                period: $period,
                actor: $actor,
                note: 'Ajustes de demostración aplicados por el seeder.',
            );

            $this->command?->info(sprintf(
                'Pre-nómina del periodo %s recalculada con ajustes: %d colaborador(es), neto %s.',
                $period->start_date->toDateString(),
                $outcome->items,
                $outcome->totals()['net'],
            ));
        } catch (Throwable $exception) {
            $this->command?->warn('No se pudo recalcular el periodo con ajustes: '.$exception->getMessage());
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
                'employee' => 'EMP-0001',
                'concept' => 'BONO_PUNTUALIDAD',
                'in_period' => true,
                'amount' => '500.00',
                'reason' => 'Bono de puntualidad del periodo (demostración).',
                'status' => AdjustmentStatus::Approved,
            ],
            [
                'employee' => 'EMP-0007',
                'concept' => 'PENALIZACION_ADMIN',
                'in_period' => true,
                'amount' => '300.00',
                'reason' => 'Penalización administrativa por equipo extraviado (ficticio).',
                'status' => AdjustmentStatus::Approved,
            ],
            [
                'employee' => 'EMP-0004',
                'concept' => 'PENALIZACION_ADMIN',
                'in_period' => true,
                'amount' => '250.00',
                'reason' => 'Revisión de caja pendiente de aprobación (ficticio).',
                'status' => AdjustmentStatus::Pending,
            ],
            [
                'employee' => 'EMP-0012',
                'concept' => 'BONO_PUNTUALIDAD',
                'in_period' => false,
                'amount' => '500.00',
                'reason' => 'Bono extraordinario sin periodo asignado (demostración).',
                'status' => AdjustmentStatus::Approved,
            ],
            [
                'employee' => 'EMP-0013',
                'concept' => 'BONO_PUNTUALIDAD',
                'in_period' => false,
                'amount' => '400.00',
                'reason' => 'Bono de productividad pendiente (demostración).',
                'status' => AdjustmentStatus::Pending,
            ],
            [
                'employee' => 'EMP-0016',
                'concept' => 'PENALIZACION_ADMIN',
                'in_period' => false,
                'amount' => '200.00',
                'reason' => 'Penalización rechazada tras revisión (ficticio).',
                'status' => AdjustmentStatus::Rejected,
            ],
        ];
    }
}
