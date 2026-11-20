<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

/**
 * Sucursales ficticias de demostración (CDMX).
 *
 * Idempotente: la clave natural es `code`.
 */
class BranchSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->branches() as $branch) {
            Branch::query()->updateOrCreate(
                ['code' => $branch['code']],
                $branch,
            );
        }

        $this->command?->info('Sucursales de demostración listas: Matriz Centro, Sucursal Norte y Sucursal Sur.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function branches(): array
    {
        return [
            [
                'name' => 'Matriz Centro',
                'code' => 'MATRIZ',
                'address' => 'Av. Insurgentes Sur 1234, Col. Del Valle, Benito Juárez, CDMX',
                'latitude' => '19.3875000',
                'longitude' => '-99.1790000',
                'geofence_radius_m' => 250,
                'timezone' => 'America/Mexico_City',
                'is_active' => true,
            ],
            [
                'name' => 'Sucursal Norte',
                'code' => 'NORTE',
                'address' => 'Calz. Vallejo 880, Col. Industrial Vallejo, Azcapotzalco, CDMX',
                'latitude' => '19.4870000',
                'longitude' => '-99.1560000',
                'geofence_radius_m' => 200,
                'timezone' => 'America/Mexico_City',
                'is_active' => true,
            ],
            [
                'name' => 'Sucursal Sur',
                'code' => 'SUR',
                'address' => 'Av. Universidad 2100, Col. Copilco, Coyoacán, CDMX',
                'latitude' => '19.3300000',
                'longitude' => '-99.1850000',
                'geofence_radius_m' => 180,
                'timezone' => 'America/Mexico_City',
                'is_active' => true,
            ],
        ];
    }
}
