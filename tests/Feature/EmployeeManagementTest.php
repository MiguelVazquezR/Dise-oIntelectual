<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
});

function employeeManagementAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', User::ADMIN_ROLE)->value('id'));

    return $user;
}

function employeeManagementBranchManager(array $branchIds): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('slug', 'branch_manager')->value('id'));
    $user->branches()->sync($branchIds);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function employeeManagementPayload(Branch $branch, array $overrides = []): array
{
    return array_merge([
        'employee_number' => 'EMP-TEST-9001',
        'first_name' => 'Lía',
        'last_name' => 'Prado',
        'second_last_name' => 'Mena',
        'branch_id' => $branch->id,
        'position' => 'Cajera',
        'department' => 'Atención a clientes',
        'email' => 'lia.prado@ejemplo.test',
        'phone' => '5512345678',
        'hire_date' => '2026-01-05',
        'status' => 'active',
        'compensation' => [
            'base_salary' => '6500.00',
            'pay_frequency' => 'biweekly',
            'effective_from' => '2026-01-05',
            'notes' => 'Compensación inicial de prueba',
        ],
    ], $overrides);
}

test('el alta de un colaborador guarda su compensación inicial y permite consultarlo', function (): void {
    $branch = Branch::factory()->create();
    $admin = employeeManagementAdmin();

    $response = $this->actingAs($admin)
        ->post(route('employees.store'), employeeManagementPayload($branch));

    $employee = Employee::query()->where('employee_number', 'EMP-TEST-9001')->firstOrFail();
    $compensation = EmployeeCompensation::query()->where('employee_id', $employee->id)->sole();

    $response->assertRedirect(route('employees.show', $employee));
    expect($employee->branch_id)->toBe($branch->id)
        ->and($employee->status->value)->toBe('active')
        ->and((string) $compensation->base_salary)->toBe('6500.00')
        ->and($compensation->pay_frequency->value)->toBe('biweekly')
        ->and($compensation->effective_from->toDateString())->toBe('2026-01-05');

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('employees/Show')
            ->where('employee.employee_number', 'EMP-TEST-9001')
            ->where('employee.has_pin', false)
            ->where('branch.id', $branch->id)
            ->where('branch.timezone', $branch->timezone)
            ->where('compensations.0.base_salary', '6500.00')
            ->where('can.manage', true));

    $this->actingAs($admin)
        ->get(route('employees.edit', $employee))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('employees/Edit')
            ->where('employee.has_pin', false)
            ->where('employee.pin_updated_at', null)
            ->missing('compensations'));
});

test('rechaza un número de empleado duplicado', function (): void {
    $branch = Branch::factory()->create();
    $admin = employeeManagementAdmin();
    Employee::factory()->create([
        'employee_number' => 'EMP-DUPLICADO',
        'branch_id' => $branch->id,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('employees.store'), employeeManagementPayload($branch, [
            'employee_number' => 'EMP-DUPLICADO',
        ]));

    $response->assertSessionHasErrors('employee_number');
    expect(Employee::query()->where('employee_number', 'EMP-DUPLICADO')->count())->toBe(1);
});

test('el cambio de PIN se audita sin guardar el PIN ni su hash', function (): void {
    $branch = Branch::factory()->create();
    $admin = employeeManagementAdmin();
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->from(route('employees.show', $employee))
        ->post(route('employees.pin', $employee), [
            'pin' => '4821',
            'pin_confirmation' => '4821',
        ])
        ->assertSessionHas('success');

    $employee->refresh();
    $log = AuditLog::query()->where('action', 'employee.pin.updated')->sole();

    expect($employee->pin_hash)->not->toBeNull()
        ->and(Hash::check('4821', (string) $employee->pin_hash))->toBeTrue()
        ->and($employee->pin_hash)->not->toBe('4821')
        ->and($log->auditable_type)->toBe(Employee::class)
        ->and($log->auditable_id)->toBe($employee->id)
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->after)->toBe(['employee_id' => $employee->id]);

    $payload = (string) json_encode([$log->before, $log->after]);

    expect($payload)
        ->not->toContain('4821')
        ->not->toContain('pin_hash')
        ->not->toContain('$2y$');
});

test('el hash del PIN no es asignable en masa', function (): void {
    expect((new Employee)->isFillable('pin_hash'))->toBeFalse()
        ->and((new Employee)->isFillable('pin_updated_at'))->toBeTrue();
});

test('la exportación CSV respeta el aislamiento por sucursal', function (): void {
    $north = Branch::factory()->create(['code' => 'NORTE-T']);
    $south = Branch::factory()->create(['code' => 'SUR-T']);

    Employee::factory()->create([
        'employee_number' => 'EMP-NORTE-01',
        'branch_id' => $north->id,
        'first_name' => 'Norte',
        'last_name' => 'Visible',
    ]);

    Employee::factory()->create([
        'employee_number' => 'EMP-SUR-01',
        'branch_id' => $south->id,
        'first_name' => 'Sur',
        'last_name' => 'Oculto',
    ]);

    $manager = employeeManagementBranchManager([$north->id]);

    $response = $this->actingAs($manager)->get(route('employees.export'));

    $response->assertOk();
    $response->assertDownload();

    $content = $response->streamedContent();

    expect($content)
        ->toContain('EMP-NORTE-01')
        ->not->toContain('EMP-SUR-01');
});
