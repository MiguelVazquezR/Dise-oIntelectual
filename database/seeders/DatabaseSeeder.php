<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos de demostración del sistema de nómina.
     *
     * Orden: RBAC → administrador → sucursales → horarios → festivos →
     * colaboradores → staff con roles distintos → marcajes e incidencias de la
     * última quincena → vacaciones → conceptos, esquemas, periodos y ajustes de
     * pre-nómina → consumo AWS simulado.
     * Todos los seeders son idempotentes, por lo que `php artisan db:seed` se
     * puede ejecutar varias veces sin duplicar filas.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
            BranchSeeder::class,
            ScheduleSeeder::class,
            HolidaySeeder::class,
            EmployeeSeeder::class,
            DemoStaffUserSeeder::class,
            AttendanceEventSeeder::class,
            IncidentSeeder::class,
            VacationSeeder::class,
            PayrollConceptSeeder::class,
            PayrollScheduleSeeder::class,
            PayrollPeriodSeeder::class,
            AdjustmentSeeder::class,
            AwsUsageSeeder::class,
        ]);
    }
}
