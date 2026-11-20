<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Edición y baja de colaboradores (§3.3): actualizar datos sin tocar el correo,
 * registrar nueva compensación y dar de baja conservando el historial.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

function employeeEditBranch(): Branch
{
    return Branch::factory()->create(['code' => 'EDIT-A', 'name' => 'Sucursal Edición']);
}

function employeeEditAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', User::ADMIN_ROLE)->value('id'));

    return $user;
}

/**
 * @return array<string, mixed>
 */
function employeeEditPayload(Employee $employee, array $overrides = []): array
{
    return array_merge([
        'employee_number' => $employee->employee_number,
        'first_name' => 'Lía',
        'last_name' => 'Prado',
        'second_last_name' => 'Mena',
        'branch_id' => $employee->branch_id,
        'position' => 'Cajera',
        'department' => 'Atención a clientes',
        'email' => $employee->email,
        'phone' => '5512345678',
        'hire_date' => $employee->hire_date->toDateString(),
        'status' => 'active',
    ], $overrides);
}

test('actualiza un colaborador conservando su correo y registra nueva compensación', function (): void {
    $branch = employeeEditBranch();
    $admin = employeeEditAdmin();

    $employee = Employee::factory()->create([
        'employee_number' => 'EMP-EDIT-01',
        'branch_id' => $branch->id,
        'email' => 'lia.prado@ejemplo.test',
        'hire_date' => '2025-01-06',
        'status' => 'active',
    ]);

    EmployeeCompensation::query()->create([
        'employee_id' => $employee->id,
        'base_salary' => '6000.00',
        'pay_frequency' => 'biweekly',
        'effective_from' => '2025-01-06',
        'effective_to' => null,
        'notes' => 'Compensación inicial de prueba',
    ]);

    $this->actingAs($admin)
        ->put(route('employees.update', $employee), employeeEditPayload($employee, [
            'position' => 'Cajera senior',
            'compensation' => [
                'base_salary' => '6800.00',
                'pay_frequency' => 'biweekly',
                'effective_from' => '2026-11-01',
                'notes' => 'Ajuste salarial de prueba',
            ],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('employees.show', $employee));

    $employee->refresh();

    expect($employee->position)->toBe('Cajera senior')
        ->and($employee->email)->toBe('lia.prado@ejemplo.test')
        ->and($employee->compensations()->count())->toBe(2);
});

test('da de baja al colaborador sin borrar su historial de compensación', function (): void {
    $branch = employeeEditBranch();
    $admin = employeeEditAdmin();

    $employee = Employee::factory()->create([
        'employee_number' => 'EMP-EDIT-02',
        'branch_id' => $branch->id,
        'email' => 'baja.ficticia@ejemplo.test',
        'hire_date' => '2025-02-03',
        'status' => 'active',
    ]);

    EmployeeCompensation::query()->create([
        'employee_id' => $employee->id,
        'base_salary' => '5000.00',
        'pay_frequency' => 'weekly',
        'effective_from' => '2025-02-03',
        'effective_to' => null,
        'notes' => 'Compensación inicial de prueba',
    ]);

    $this->actingAs($admin)
        ->put(route('employees.update', $employee), employeeEditPayload($employee, [
            'status' => 'terminated',
            'termination_date' => '2026-11-15',
        ]))
        ->assertSessionHasNoErrors();

    $employee->refresh();

    expect($employee->status->value)->toBe('terminated')
        ->and($employee->termination_date?->toDateString())->toBe('2026-11-15')
        ->and(EmployeeCompensation::query()->where('employee_id', $employee->id)->count())->toBe(1);
});
