<?php

namespace Database\Seeders;

use App\Enums\PayrollConceptCalculation;
use App\Enums\PayrollConceptType;
use App\Models\PayrollConcept;
use Illuminate\Database\Seeder;

/**
 * Catálogo de conceptos de pre-nómina (demostración, D-12).
 *
 * Idempotente por `code` (columna única). Los importes son de ejemplo y no
 * representan prestaciones de ley: la aplicación no calcula ISR, IMSS ni
 * INFONAVIT (D-14). `PENALIZACION_ADMIN` está marcada `is_sensitive` porque
 * solo puede registrarla o aprobarla quien tiene `adjustments.approve`.
 */
class PayrollConceptSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'code' => 'SUELDO_BASE',
                'name' => 'Sueldo base',
                'type' => PayrollConceptType::Earning,
                'calculation' => PayrollConceptCalculation::Fixed,
                'default_amount' => null,
                'is_sensitive' => false,
                'is_active' => true,
                'notes' => 'Sueldo del periodo prorrateado desde la compensación vigente del colaborador.',
            ],
            [
                'code' => 'HORAS_EXTRA',
                'name' => 'Horas extra (propuesta interna)',
                'type' => PayrollConceptType::Earning,
                'calculation' => PayrollConceptCalculation::PerQuantity,
                'default_amount' => '0.00',
                'is_sensitive' => false,
                'is_active' => true,
                'notes' => 'Minutos extra del motor de asistencias × tarifa horaria × factor configurable. Sin multiplicadores legales.',
            ],
            [
                'code' => 'BONO_PUNTUALIDAD',
                'name' => 'Bono de puntualidad',
                'type' => PayrollConceptType::Earning,
                'calculation' => PayrollConceptCalculation::Fixed,
                'default_amount' => '500.00',
                'is_sensitive' => false,
                'is_active' => true,
                'notes' => 'Bono discrecional de demostración; no es prestación de ley.',
            ],
            [
                'code' => 'FALTA_INJUSTIFICADA',
                'name' => 'Descuento por falta injustificada',
                'type' => PayrollConceptType::Deduction,
                'calculation' => PayrollConceptCalculation::PerQuantity,
                'default_amount' => null,
                'is_sensitive' => false,
                'is_active' => true,
                'notes' => 'Días de falta × tarifa diaria × factor configurable.',
            ],
            [
                'code' => 'PENALIZACION_ADMIN',
                'name' => 'Penalización administrativa',
                'type' => PayrollConceptType::Deduction,
                'calculation' => PayrollConceptCalculation::Fixed,
                'default_amount' => null,
                'is_sensitive' => true,
                'is_active' => true,
                'notes' => 'Descuento sensible: requiere permiso adjustments.approve para registrarse y aprobarse.',
            ],
        ];

        foreach ($definitions as $definition) {
            PayrollConcept::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'calculation' => $definition['calculation'],
                    'default_amount' => $definition['default_amount'],
                    'is_sensitive' => $definition['is_sensitive'],
                    'is_active' => $definition['is_active'],
                    'notes' => $definition['notes'],
                ],
            );
        }

        $this->command?->info('Catálogo de conceptos de pre-nómina listo (5 conceptos).');
    }
}
