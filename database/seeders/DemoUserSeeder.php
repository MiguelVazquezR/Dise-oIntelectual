<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Usuario administrador de demostración (D-12).
 *
 * Credenciales de demostración:
 *   correo:     admin@diseno-intelectual.test
 *   contraseña: NominaDemo2026!
 *
 * Requiere que `RolePermissionSeeder` se haya ejecutado antes. Idempotente.
 */
class DemoUserSeeder extends Seeder
{
    public const DEMO_EMAIL = 'admin@diseno-intelectual.test';

    public const DEMO_PASSWORD = 'NominaDemo2026!';

    public function run(): void
    {
        $adminRole = Role::query()->where('slug', User::ADMIN_ROLE)->first();

        if ($adminRole === null) {
            throw new RuntimeException(
                'Falta el rol "admin". Ejecuta primero: php artisan db:seed --class=RolePermissionSeeder',
            );
        }

        $user = User::updateOrCreate(
            ['email' => self::DEMO_EMAIL],
            [
                'name' => 'Administrador Demo',
                'password' => self::DEMO_PASSWORD,
            ],
        );

        $user->forceFill(['email_verified_at' => now()])->save();

        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        $this->command?->info('Usuario demo listo: '.self::DEMO_EMAIL.' / '.self::DEMO_PASSWORD);
    }
}
