<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Usuarios de demostración con roles distintos al administrador.
 *
 * Credenciales (contraseña común): NominaDemo2026!
 * - rrhh@diseno-intelectual.test       → hr (data.all_branches)
 * - norte@diseno-intelectual.test      → branch_manager solo Sucursal Norte
 * - supervisor@diseno-intelectual.test → supervisor solo Sucursal Sur
 * - consulta@diseno-intelectual.test   → viewer solo Matriz Centro
 *
 * Requiere RolePermissionSeeder y BranchSeeder. Idempotente por correo.
 */
class DemoStaffUserSeeder extends Seeder
{
    /**
     * @var array<int, array{email: string, name: string, role: string, branch: string}>
     */
    private const STAFF = [
        [
            'email' => 'rrhh@diseno-intelectual.test',
            'name' => 'Rosa Elena Márquez',
            'role' => 'hr',
            'branch' => 'MATRIZ',
        ],
        [
            'email' => 'norte@diseno-intelectual.test',
            'name' => 'Sergio Nava Ponce',
            'role' => 'branch_manager',
            'branch' => 'NORTE',
        ],
        [
            'email' => 'supervisor@diseno-intelectual.test',
            'name' => 'Patricia Solano Reyes',
            'role' => 'supervisor',
            'branch' => 'SUR',
        ],
        [
            'email' => 'consulta@diseno-intelectual.test',
            'name' => 'Diego Fuentes Aranda',
            'role' => 'viewer',
            'branch' => 'MATRIZ',
        ],
    ];

    public function run(): void
    {
        $roles = Role::query()
            ->whereIn('slug', array_column(self::STAFF, 'role'))
            ->get()
            ->keyBy('slug');

        $branches = Branch::query()
            ->whereIn('code', array_column(self::STAFF, 'branch'))
            ->get()
            ->keyBy('code');

        foreach (self::STAFF as $staff) {
            $role = $roles->get($staff['role']);
            $branch = $branches->get($staff['branch']);

            if ($role === null || $branch === null) {
                throw new RuntimeException(
                    'Faltan roles o sucursales demo. Ejecuta RolePermissionSeeder y BranchSeeder antes.',
                );
            }

            $user = User::query()->updateOrCreate(
                ['email' => $staff['email']],
                [
                    'name' => $staff['name'],
                    'password' => DemoUserSeeder::DEMO_PASSWORD,
                ],
            );

            $user->forceFill(['email_verified_at' => now()])->save();

            $user->roles()->sync([$role->id]);
            $user->branches()->sync([$branch->id => ['is_primary' => true]]);
        }

        $this->command?->info('Usuarios demo de staff listos (contraseña: '.DemoUserSeeder::DEMO_PASSWORD.').');
    }
}
