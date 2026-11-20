<?php

namespace Database\Seeders;

use App\Enums\PayrollScheduleFrequency;
use App\Enums\Weekday;
use App\Models\PayrollSchedule;
use Illuminate\Database\Seeder;

/**
 * Esquemas de pago de demostración (S-04).
 *
 * - Semanal: inicia lunes y cierra domingo; pago dos días después del cierre.
 * - Quincenal: días 1–15 y 16–fin de mes; pago dos días después del cierre.
 *
 * Idempotente por `name`: se actualizan los parámetros sin crear duplicados.
 */
class PayrollScheduleSeeder extends Seeder
{
    public function run(): void
    {
        PayrollSchedule::query()->updateOrCreate(
            ['name' => 'Nómina semanal'],
            [
                'frequency' => PayrollScheduleFrequency::Weekly,
                'start_weekday' => Weekday::Monday,
                'cutoff_weekday' => Weekday::Sunday,
                'payment_offset_days' => 2,
                'is_active' => true,
            ],
        );

        PayrollSchedule::query()->updateOrCreate(
            ['name' => 'Nómina quincenal'],
            [
                'frequency' => PayrollScheduleFrequency::Biweekly,
                'start_weekday' => Weekday::Monday,
                'cutoff_weekday' => Weekday::Sunday,
                'payment_offset_days' => 2,
                'is_active' => true,
            ],
        );

        $this->command?->info('Esquemas de pago de demostración listos: semanal y quincenal.');
    }
}
