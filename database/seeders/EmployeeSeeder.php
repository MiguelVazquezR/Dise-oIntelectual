<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Colaboradores ficticios de demostración (D-12).
 *
 * PIN demo: los últimos 4 dígitos del número de empleado.
 * Ejemplo: EMP-0007 → PIN 0007. Se guarda siempre con `Hash::make()`.
 *
 * Idempotente: colaboradores por `employee_number`; compensaciones por
 * (employee_id, effective_from). El historial de cinco colaboradores incluye
 * el salario inicial cerrado y la compensación vigente.
 */
class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::query()
            ->whereIn('code', ['MATRIZ', 'NORTE', 'SUR'])
            ->get()
            ->keyBy('code');

        if ($branches->isEmpty()) {
            $this->command?->warn('No hay sucursales; ejecuta primero BranchSeeder.');

            return;
        }

        foreach ($this->employees() as $index => $definition) {
            $branch = $branches->get($definition['branch']);

            if ($branch === null) {
                continue;
            }

            $email = Str::slug($definition['first'].' '.$definition['last'], '.').'@diseno-intelectual.test';
            $pin = substr($definition['number'], -4);

            $employee = Employee::query()->updateOrCreate(
                ['employee_number' => $definition['number']],
                [
                    'first_name' => $definition['first'],
                    'last_name' => $definition['last'],
                    'second_last_name' => $definition['second'],
                    'branch_id' => $branch->id,
                    'position' => $definition['position'],
                    'department' => $definition['department'],
                    'email' => $email,
                    'phone' => '55'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                    'hire_date' => $definition['hire_date'],
                    'termination_date' => $definition['termination_date'] ?? null,
                    'status' => $definition['status'],
                ],
            );

            // El PIN no es asignable en masa (`Employee::$fillable`): se asigna
            // siempre con hash explícito (D-12).
            $employee->forceFill([
                'pin_hash' => Hash::make($pin),
                'pin_updated_at' => now(),
            ])->save();

            $history = $definition['history'] ?? null;
            $currentFrom = $definition['hire_date'];

            if (is_array($history)) {
                EmployeeCompensation::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'effective_from' => CarbonImmutable::parse($definition['hire_date'])->startOfDay(),
                    ],
                    [
                        'base_salary' => $history['salary'],
                        'pay_frequency' => $definition['frequency'],
                        'effective_to' => $history['until'],
                        'notes' => 'Salario inicial',
                    ],
                );

                $currentFrom = CarbonImmutable::parse($history['until'])->addDay()->toDateString();
            }

            EmployeeCompensation::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'effective_from' => CarbonImmutable::parse($currentFrom)->startOfDay(),
                ],
                [
                    'base_salary' => $definition['salary'],
                    'pay_frequency' => $definition['frequency'],
                    'effective_to' => null,
                    'notes' => $history === null ? 'Compensación inicial' : 'Ajuste salarial',
                ],
            );
        }

        $this->command?->info('24 colaboradores ficticios listos. PIN demo: últimos 4 dígitos del número de empleado.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function employees(): array
    {
        return [
            [
                'number' => 'EMP-0001', 'first' => 'Ana', 'last' => 'López', 'second' => 'García',
                'branch' => 'MATRIZ', 'position' => 'Gerente de sucursal', 'department' => 'Dirección',
                'hire_date' => '2019-03-04', 'status' => 'active', 'frequency' => 'monthly', 'salary' => '32000.00',
                'history' => ['salary' => '27000.00', 'until' => '2024-12-31'],
            ],
            [
                'number' => 'EMP-0002', 'first' => 'Luis', 'last' => 'Hernández', 'second' => 'Ramírez',
                'branch' => 'MATRIZ', 'position' => 'Coordinador de operaciones', 'department' => 'Operaciones',
                'hire_date' => '2020-07-13', 'status' => 'active', 'frequency' => 'monthly', 'salary' => '24000.00',
            ],
            [
                'number' => 'EMP-0003', 'first' => 'María Fernanda', 'last' => 'Torres', 'second' => 'Vega',
                'branch' => 'MATRIZ', 'position' => 'Ejecutiva de ventas', 'department' => 'Ventas',
                'hire_date' => '2021-02-01', 'status' => 'active', 'frequency' => 'monthly', 'salary' => '18000.00',
                'history' => ['salary' => '15500.00', 'until' => '2025-06-30'],
            ],
            [
                'number' => 'EMP-0004', 'first' => 'Carlos', 'last' => 'Mendoza', 'second' => 'Ruiz',
                'branch' => 'MATRIZ', 'position' => 'Cajero', 'department' => 'Atención a clientes',
                'hire_date' => '2022-05-16', 'status' => 'active', 'frequency' => 'biweekly', 'salary' => '6500.00',
            ],
            [
                'number' => 'EMP-0005', 'first' => 'Sofía', 'last' => 'Ramírez', 'second' => 'Cruz',
                'branch' => 'MATRIZ', 'position' => 'Auxiliar administrativa', 'department' => 'Administración',
                'hire_date' => '2023-01-09', 'status' => 'active', 'frequency' => 'biweekly', 'salary' => '5200.00',
                'history' => ['salary' => '4200.00', 'until' => '2025-01-31'],
            ],
            [
                'number' => 'EMP-0006', 'first' => 'Jorge Alberto', 'last' => 'Castillo', 'second' => 'Núñez',
                'branch' => 'MATRIZ', 'position' => 'Almacenista', 'department' => 'Almacén',
                'hire_date' => '2023-08-21', 'status' => 'active', 'frequency' => 'weekly', 'salary' => '2900.00',
            ],
            [
                'number' => 'EMP-0007', 'first' => 'Valeria', 'last' => 'Flores', 'second' => 'Núñez',
                'branch' => 'MATRIZ', 'position' => 'Recepcionista', 'department' => 'Atención a clientes',
                'hire_date' => '2024-01-15', 'status' => 'active', 'frequency' => 'biweekly', 'salary' => '4700.00',
            ],
            [
                'number' => 'EMP-0008', 'first' => 'Miguel Ángel', 'last' => 'Sánchez', 'second' => 'Pérez',
                'branch' => 'MATRIZ', 'position' => 'Técnico de soporte', 'department' => 'Sistemas',
                'hire_date' => '2024-04-08', 'status' => 'active', 'frequency' => 'monthly', 'salary' => '16000.00',
            ],
            [
                'number' => 'EMP-0009', 'first' => 'Daniela', 'last' => 'Ortiz', 'second' => 'Romero',
                'branch' => 'MATRIZ', 'position' => 'Contadora', 'department' => 'Finanzas',
                'hire_date' => '2025-02-03', 'status' => 'active', 'frequency' => 'monthly', 'salary' => '22000.00',
            ],
            [
                'number' => 'EMP-0010', 'first' => 'Ricardo', 'last' => 'Medina', 'second' => 'Silva',
                'branch' => 'NORTE', 'position' => 'Gerente de sucursal', 'department' => 'Dirección',
                'hire_date' => '2020-09-07', 'status' => 'active', 'frequency' => 'monthly', 'salary' => '30000.00',
                'history' => ['salary' => '25000.00', 'until' => '2025-12-31'],
            ],
            [
                'number' => 'EMP-0011', 'first' => 'Paola', 'last' => 'Jiménez', 'second' => 'Rojas',
                'branch' => 'NORTE', 'position' => 'Coordinadora de operaciones', 'department' => 'Operaciones',
                'hire_date' => '2021-11-22', 'status' => 'active', 'frequency' => 'monthly', 'salary' => '23000.00',
            ],
            [
                'number' => 'EMP-0012', 'first' => 'Fernando', 'last' => 'Aguilar', 'second' => 'Peña',
                'branch' => 'NORTE', 'position' => 'Cajero', 'department' => 'Atención a clientes',
                'hire_date' => '2022-03-14', 'status' => 'active', 'frequency' => 'biweekly', 'salary' => '6300.00',
            ],
            [
                'number' => 'EMP-0013', 'first' => 'Claudia', 'last' => 'Reyes', 'second' => 'Molina',
                'branch' => 'NORTE', 'position' => 'Ejecutiva de ventas', 'department' => 'Ventas',
                'hire_date' => '2023-05-02', 'status' => 'active', 'frequency' => 'monthly', 'salary' => '17500.00',
            ],
            [
                'number' => 'EMP-0014', 'first' => 'Óscar', 'last' => 'Domínguez', 'second' => 'Lara',
                'branch' => 'NORTE', 'position' => 'Almacenista', 'department' => 'Almacén',
                'hire_date' => '2024-02-19', 'status' => 'active', 'frequency' => 'weekly', 'salary' => '2850.00',
            ],
            [
                'number' => 'EMP-0015', 'first' => 'Gabriela', 'last' => 'Vargas', 'second' => 'Solís',
                'branch' => 'NORTE', 'position' => 'Auxiliar de limpieza', 'department' => 'Servicios',
                'hire_date' => '2024-07-01', 'status' => 'active', 'frequency' => 'weekly', 'salary' => '2400.00',
            ],
            [
                'number' => 'EMP-0016', 'first' => 'Héctor', 'last' => 'Navarro', 'second' => 'Castro',
                'branch' => 'NORTE', 'position' => 'Recepcionista', 'department' => 'Atención a clientes',
                'hire_date' => '2025-03-10', 'status' => 'active', 'frequency' => 'biweekly', 'salary' => '4600.00',
            ],
            [
                'number' => 'EMP-0017', 'first' => 'Lucía', 'last' => 'Salazar', 'second' => 'Herrera',
                'branch' => 'NORTE', 'position' => 'Técnica de soporte', 'department' => 'Sistemas',
                'hire_date' => '2025-06-16', 'status' => 'active', 'frequency' => 'monthly', 'salary' => '15500.00',
            ],
            [
                'number' => 'EMP-0018', 'first' => 'Andrés', 'last' => 'Gutiérrez', 'second' => 'Ríos',
                'branch' => 'SUR', 'position' => 'Gerente de sucursal', 'department' => 'Dirección',
                'hire_date' => '2020-01-20', 'status' => 'active', 'frequency' => 'monthly', 'salary' => '29000.00',
                'history' => ['salary' => '24000.00', 'until' => '2026-03-31'],
            ],
            [
                'number' => 'EMP-0019', 'first' => 'Mariana', 'last' => 'Cabrera', 'second' => 'Luna',
                'branch' => 'SUR', 'position' => 'Coordinadora de operaciones', 'department' => 'Operaciones',
                'hire_date' => '2021-06-07', 'status' => 'active', 'frequency' => 'monthly', 'salary' => '22500.00',
            ],
            [
                'number' => 'EMP-0020', 'first' => 'Raúl', 'last' => 'Espinoza', 'second' => 'Vega',
                'branch' => 'SUR', 'position' => 'Ejecutivo de ventas', 'department' => 'Ventas',
                'hire_date' => '2022-10-03', 'status' => 'active', 'frequency' => 'monthly', 'salary' => '17000.00',
            ],
            [
                'number' => 'EMP-0021', 'first' => 'Verónica', 'last' => 'Delgado', 'second' => 'Ponce',
                'branch' => 'SUR', 'position' => 'Cajera', 'department' => 'Atención a clientes',
                'hire_date' => '2023-11-13', 'status' => 'active', 'frequency' => 'biweekly', 'salary' => '6200.00',
            ],
            [
                'number' => 'EMP-0022', 'first' => 'Alfonso', 'last' => 'Rivas', 'second' => 'Camacho',
                'branch' => 'SUR', 'position' => 'Almacenista', 'department' => 'Almacén',
                'hire_date' => '2024-09-09', 'status' => 'inactive', 'frequency' => 'weekly', 'salary' => '2800.00',
            ],
            [
                'number' => 'EMP-0023', 'first' => 'Natalia', 'last' => 'Serrano', 'second' => 'Ibarra',
                'branch' => 'SUR', 'position' => 'Recepcionista', 'department' => 'Atención a clientes',
                'hire_date' => '2025-08-04', 'status' => 'inactive', 'frequency' => 'biweekly', 'salary' => '4500.00',
            ],
            [
                'number' => 'EMP-0024', 'first' => 'Emilio', 'last' => 'Cortés', 'second' => 'Miranda',
                'branch' => 'SUR', 'position' => 'Auxiliar administrativo', 'department' => 'Administración',
                'hire_date' => '2026-02-02', 'status' => 'terminated', 'frequency' => 'biweekly', 'salary' => '5100.00',
                'termination_date' => '2026-08-31',
            ],
        ];
    }
}
