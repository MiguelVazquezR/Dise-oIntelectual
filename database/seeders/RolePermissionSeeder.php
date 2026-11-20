<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Roles y permisos del contrato (ARCHITECTURE.md §4).
 *
 * Idempotente: se puede ejecutar varias veces sin duplicar filas ni asignaciones.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Permisos por slug: [grupo, nombre en español].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PERMISSIONS = [
        'dashboard.view' => ['dashboard', 'Ver panel'],
        'branches.view' => ['branches', 'Ver sucursales'],
        'branches.manage' => ['branches', 'Administrar sucursales'],
        'schedules.view' => ['schedules', 'Ver horarios'],
        'schedules.manage' => ['schedules', 'Administrar horarios'],
        'employees.view' => ['employees', 'Ver colaboradores'],
        'employees.manage' => ['employees', 'Administrar colaboradores'],
        'attendance.view' => ['attendance', 'Ver asistencia'],
        'attendance.manage' => ['attendance', 'Administrar asistencia'],
        'attendance.record' => ['attendance', 'Registrar marcajes en checador'],
        'incidents.view' => ['incidents', 'Ver incidencias'],
        'incidents.manage' => ['incidents', 'Administrar incidencias'],
        'incidents.approve' => ['incidents', 'Aprobar incidencias'],
        'holidays.view' => ['holidays', 'Ver días festivos'],
        'holidays.manage' => ['holidays', 'Administrar días festivos'],
        'vacations.view' => ['vacations', 'Ver vacaciones'],
        'vacations.manage' => ['vacations', 'Administrar vacaciones'],
        'vacations.approve' => ['vacations', 'Aprobar vacaciones'],
        'payroll.view' => ['payroll', 'Ver pre-nómina'],
        'payroll.calculate' => ['payroll', 'Calcular pre-nómina'],
        'payroll.review' => ['payroll', 'Revisar pre-nómina'],
        'payroll.close' => ['payroll', 'Cerrar periodos de pre-nómina'],
        'adjustments.view' => ['adjustments', 'Ver ajustes de nómina'],
        'adjustments.manage' => ['adjustments', 'Administrar ajustes de nómina'],
        'adjustments.approve' => ['adjustments', 'Aprobar ajustes de nómina'],
        'concepts.view' => ['concepts', 'Ver conceptos de nómina'],
        'concepts.manage' => ['concepts', 'Administrar conceptos de nómina'],
        'users.view' => ['users', 'Ver usuarios'],
        'users.manage' => ['users', 'Administrar usuarios'],
        'roles.manage' => ['roles', 'Administrar roles y permisos'],
        'aws.settings.view' => ['aws', 'Ver configuración de AWS'],
        'aws.settings.manage' => ['aws', 'Administrar configuración de AWS'],
        'aws.usage.view' => ['aws', 'Ver consumo de AWS'],
        'audit.view' => ['audit', 'Ver bitácora de auditoría'],
        'data.all_branches' => ['data', 'Ver datos de todas las sucursales'],
    ];

    /**
     * Permisos de los que el rol `hr` queda excluido (administración de cuentas y AWS).
     *
     * @var array<int, string>
     */
    private const HR_EXCLUDED = [
        'users.view',
        'users.manage',
        'roles.manage',
        'aws.settings.view',
        'aws.settings.manage',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $permissionIds = $this->syncPermissions();
            $this->syncRoles($permissionIds);
        });
    }

    /**
     * @return array<string, int>
     */
    private function syncPermissions(): array
    {
        foreach (self::PERMISSIONS as $slug => [$group, $name]) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['group' => $group, 'name' => $name],
            );
        }

        return Permission::query()->pluck('id', 'slug')->all();
    }

    /**
     * @param  array<string, int>  $permissionIds
     */
    private function syncRoles(array $permissionIds): void
    {
        $roles = [
            'admin' => [
                'name' => 'Administrador',
                'description' => 'Acceso total al sistema.',
                'permissions' => array_keys(self::PERMISSIONS),
            ],
            'hr' => [
                'name' => 'Recursos Humanos',
                'description' => 'Operación completa de asistencia y pre-nómina en todas las sucursales.',
                'permissions' => array_values(array_diff(array_keys(self::PERMISSIONS), self::HR_EXCLUDED)),
            ],
            'branch_manager' => [
                'name' => 'Encargado de sucursal',
                'description' => 'Operación de sus sucursales, sin administración de usuarios ni AWS.',
                'permissions' => [
                    'dashboard.view',
                    'branches.view',
                    'schedules.view',
                    'schedules.manage',
                    'employees.view',
                    'employees.manage',
                    'attendance.view',
                    'attendance.manage',
                    'attendance.record',
                    'incidents.view',
                    'incidents.manage',
                    'incidents.approve',
                    'holidays.view',
                    'vacations.view',
                    'vacations.manage',
                    'vacations.approve',
                    'payroll.view',
                    'payroll.calculate',
                    'adjustments.view',
                    'adjustments.manage',
                    'concepts.view',
                ],
            ],
            'supervisor' => [
                'name' => 'Supervisor',
                'description' => 'Consulta operativa y gestión de incidencias de sus sucursales.',
                'permissions' => [
                    'dashboard.view',
                    'branches.view',
                    'schedules.view',
                    'employees.view',
                    'attendance.view',
                    'attendance.record',
                    'incidents.view',
                    'incidents.manage',
                    'incidents.approve',
                    'holidays.view',
                    'vacations.view',
                ],
            ],
            'viewer' => [
                'name' => 'Consulta',
                'description' => 'Solo lectura de la operación.',
                'permissions' => [
                    'dashboard.view',
                    'branches.view',
                    'schedules.view',
                    'employees.view',
                    'attendance.view',
                    'incidents.view',
                    'holidays.view',
                    'vacations.view',
                    'payroll.view',
                    'adjustments.view',
                    'concepts.view',
                ],
            ],
        ];

        foreach ($roles as $slug => $definition) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_system' => $slug === 'admin',
                ],
            );

            $role->permissions()->sync(
                array_map(fn (string $permissionSlug): int => $permissionIds[$permissionSlug], $definition['permissions']),
            );
        }
    }
}
