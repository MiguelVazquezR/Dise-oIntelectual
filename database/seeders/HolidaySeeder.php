<?php

namespace Database\Seeders;

use App\Services\Holidays\HolidayGenerator;
use App\Services\Holidays\HolidayLockGuard;
use Illuminate\Database\Seeder;

/**
 * Genera el calendario legal de festivos del año actual y del siguiente con
 * `HolidayGenerator` (LFT art. 74).
 *
 * Idempotente: ejecutar el seeder de nuevo no duplica ni sobrescribe festivos.
 * No se ejecuta automáticamente desde `DatabaseSeeder` para no mezclar datos
 * legales con los operativos; se invoca explícitamente:
 *
 *     php artisan db:seed --class=HolidaySeeder
 */
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $generator = app(HolidayGenerator::class);
        $lockGuard = app(HolidayLockGuard::class);
        $currentYear = $lockGuard->currentYear();

        foreach ([$currentYear, $currentYear + 1] as $year) {
            if ($lockGuard->isLocked($year)) {
                $this->command?->warn("El año {$year} está cerrado; se omite la generación de festivos.");

                continue;
            }

            $result = $generator->forYear($year);

            $this->command?->info(
                "Festivos {$year}: {$result['created']} nuevos, {$result['refreshed']} actualizados, {$result['skipped']} ya existentes (total {$result['total']}).",
            );
        }

        $this->command?->info('Calendario de festivos pendiente de validación en la fuente oficial de la LFT.');
    }
}
