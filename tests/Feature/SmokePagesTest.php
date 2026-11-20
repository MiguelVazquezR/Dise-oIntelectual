<?php

use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Smoke funcional por rol: cada página Inertia debe responder 200/403 según los
 * permisos del usuario demo sembrado y entregar el componente y las props
 * mínimas del contrato (ARCHITECTURE.md §7.x).
 *
 * Se usa el sembrado completo de demostración para que las páginas se abran con
 * datos reales (periodos calculados, recibos, festivos, vacaciones, consumo AWS)
 * y así detectar props nulas o consultas rotas que las pruebas por módulo no
 * ejercitan en conjunto.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(DatabaseSeeder::class);
});

/**
 * Usuario demo sembrado por correo.
 */
function smokeUser(string $email): User
{
    return User::query()->where('email', $email)->firstOrFail();
}

/**
 * Colaborador demo por número de empleado.
 */
function smokeEmployee(string $number): Employee
{
    return Employee::query()->where('employee_number', $number)->firstOrFail();
}

/**
 * Páginas del administrador con el componente y las props obligatorias.
 *
 * @return array<int, array{0: string, 1: string, 2: array<int, string>}>
 */
function smokeAdminPages(): array
{
    $matrizEmployee = smokeEmployee('EMP-0001');
    $period = PayrollPeriod::query()->orderBy('id')->firstOrFail();
    $item = PayrollItem::query()->orderBy('id')->firstOrFail();

    return [
        ['/dashboard', 'Dashboard', [
            'filters', 'metrics.active_employees', 'metrics.late_count', 'metrics.overtime_minutes',
            'by_branch', 'trend', 'recent_incidents', 'payroll', 'options.periods', 'options.branches',
            'can.payroll', 'can.incidents', 'can.attendance', 'can.aws_usage', 'can.audit',
        ]],
        ['/branches', 'branches/Index', ['filters', 'branches.data', 'branches.meta', 'can.manage']],
        ['/branches/create', 'branches/Create', ['branch', 'timezones', 'can.manage']],
        ['/schedules', 'schedules/Index', ['filters', 'schedules.data', 'options.branches', 'can.manage']],
        ['/schedules/create', 'schedules/Create', ['schedule', 'options.branches', 'weekdays', 'can.manage']],
        ['/employees', 'employees/Index', [
            'filters', 'employees.data', 'employees.meta', 'options.branches', 'options.statuses',
            'options.pay_frequencies', 'can.manage',
        ]],
        ['/employees/create', 'employees/Create', ['employee', 'options.branches', 'can.manage']],
        ["/employees/{$matrizEmployee->id}", 'employees/Show', [
            'employee.employee_number', 'employee.branch_id', 'branch.timezone', 'compensations',
            'recent_events', 'vacation_summary', 'can.manage',
        ]],
        ["/employees/{$matrizEmployee->id}/edit", 'employees/Edit', ['employee.id', 'options.branches', 'can.manage']],
        ['/attendance', 'attendance/Index', ['filters', 'rows.data', 'rows.meta', 'summary', 'options.branches', 'can.manage']],
        ["/attendance/employee/{$matrizEmployee->id}", 'attendance/Employee', [
            'employee.branch.timezone', 'period', 'days', 'totals',
        ]],
        ['/incidents', 'incidents/Index', ['filters', 'incidents.data', 'options.types', 'options.statuses', 'can.approve']],
        ['/holidays', 'holidays/Index', ['filters', 'holidays', 'summary', 'options.years', 'legal_notice', 'can.manage']],
        ['/vacations', 'vacations/Index', ['filters', 'periods.data', 'requests.data', 'options.years', 'rules', 'can.approve']],
        ['/payroll/periods', 'payroll/Index', ['filters', 'periods.data', 'options.schedules', 'notice', 'can.calculate']],
        ["/payroll/periods/{$period->id}", 'payroll/Show', ['period', 'runs', 'items', 'totals', 'notice', 'can.close']],
        ["/payroll/items/{$item->id}/receipt", 'payroll/Receipt', ['item', 'company', 'notice', 'signature_lines']],
        ['/adjustments', 'adjustments/Index', ['filters', 'adjustments.data', 'options.concepts', 'can.approve', 'warning']],
        ['/concepts', 'concepts/Index', ['filters', 'concepts', 'options.types', 'can.manage']],
        ['/users', 'users/Index', ['filters', 'users.data', 'options.roles', 'options.branches', 'can.manage']],
        ['/users/create', 'users/Create', ['user', 'options.roles', 'can.manage']],
        ['/roles', 'roles/Index', ['roles', 'permission_groups', 'can.manage']],
        ['/kiosk', 'kiosk/Index', ['branch.timezone', 'face.configured', 'recent_events', 'can.face', 'options.branches']],
        ['/aws/usage', 'aws/Usage', ['filters', 'totals', 'records.data', 'pricing.note', 'options.operations', 'options.sources']],
        ['/settings/aws', 'settings/Aws', ['configuration', 'checks', 'notice', 'can.manage']],
        ['/audit', 'audit/Index', ['filters', 'logs.data', 'options.actions', 'options.users']],
        ['/my/attendance', 'my/Attendance', ['period', 'days', 'totals']],
        ['/my/vacations', 'my/Vacations', ['linked', 'periods', 'requests', 'notice']],
    ];
}

test('el administrador abre todas las páginas con el componente y las props del contrato', function (): void {
    $admin = smokeUser('admin@diseno-intelectual.test');

    foreach (smokeAdminPages() as [$uri, $component, $props]) {
        $response = $this->actingAs($admin)->get($uri);

        $response->assertOk();

        $response->assertInertia(function (Assert $page) use ($component, $props): void {
            $page->component($component);

            foreach ($props as $prop) {
                // Las props nulas (`branch` en checador, `employee` en «Mi asistencia»)
                // se validan aparte; aquí se exige presencia sin error de consulta.
                $page->has($prop);
            }
        });
    }
});

test('los listados principales entregan filas y catálogos no vacíos con datos demo', function (): void {
    $admin = smokeUser('admin@diseno-intelectual.test');

    $expectations = [
        '/branches' => 'branches.data',
        '/schedules' => 'schedules.data',
        '/employees' => 'employees.data',
        '/incidents' => 'incidents.data',
        '/vacations' => 'periods.data',
        '/payroll/periods' => 'periods.data',
        '/adjustments' => 'adjustments.data',
        '/concepts' => 'concepts',
        '/users' => 'users.data',
        '/roles' => 'roles',
        '/audit' => 'logs.data',
    ];

    foreach ($expectations as $uri => $prop) {
        $response = $this->actingAs($admin)->get($uri);

        $response->assertOk();

        $response->assertInertia(
            fn (Assert $page): Assert => $page->has($prop, fn (Assert $rows): Assert => $rows->etc()),
        );
    }

    $this->actingAs($admin)
        ->get('/holidays')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('summary.mandatory', fn (int $mandatory): bool => $mandatory >= 7)
            ->where('summary.total', fn (int $total): bool => $total >= 7));

    $this->actingAs($admin)
        ->get('/aws/usage')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('totals.faces', fn (int $faces): bool => $faces > 0)
            // La tarifa se envía tal como está configurada (string); el frontend la formatea.
            ->where('pricing.price_per_1000', fn ($price): bool => (float) $price === 24.0)
            ->where('pricing.currency', 'MXN'));
});

test('el checador expone la sucursal y el estado facial sin secretos', function (): void {
    $this->actingAs(smokeUser('norte@diseno-intelectual.test'))
        ->get('/kiosk')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('kiosk/Index')
            ->where('branch.name', 'Sucursal Norte')
            ->where('face.configured', false)
            ->where('face.enabled', false)
            ->missing('face.access_key')
            ->missing('face.secret'));
});

test('cada rol demo solo alcanza las páginas de su alcance', function (): void {
    $allowed = [
        'rrhh@diseno-intelectual.test' => [
            '/dashboard', '/branches', '/schedules', '/employees', '/employees/create',
            '/attendance', '/incidents', '/holidays', '/vacations', '/payroll/periods',
            '/adjustments', '/concepts', '/kiosk', '/aws/usage', '/audit',
            '/my/attendance', '/my/vacations',
        ],
        'norte@diseno-intelectual.test' => [
            '/dashboard', '/branches', '/schedules', '/employees', '/employees/create',
            '/attendance', '/incidents', '/holidays', '/vacations', '/payroll/periods',
            '/adjustments', '/concepts', '/kiosk', '/my/attendance', '/my/vacations',
        ],
        'supervisor@diseno-intelectual.test' => [
            '/dashboard', '/branches', '/schedules', '/employees', '/attendance',
            '/incidents', '/holidays', '/vacations', '/kiosk', '/my/attendance', '/my/vacations',
        ],
        'consulta@diseno-intelectual.test' => [
            '/dashboard', '/branches', '/schedules', '/employees', '/attendance',
            '/incidents', '/holidays', '/vacations', '/payroll/periods', '/adjustments',
            '/concepts', '/my/attendance', '/my/vacations',
        ],
    ];

    $forbidden = [
        'rrhh@diseno-intelectual.test' => ['/users', '/roles', '/settings/aws'],
        'norte@diseno-intelectual.test' => ['/users', '/roles', '/settings/aws', '/aws/usage', '/audit', '/branches/create'],
        'supervisor@diseno-intelectual.test' => [
            '/users', '/roles', '/settings/aws', '/aws/usage', '/audit', '/employees/create',
            '/payroll/periods', '/adjustments', '/concepts',
        ],
        'consulta@diseno-intelectual.test' => ['/users', '/roles', '/settings/aws', '/aws/usage', '/audit', '/kiosk', '/employees/create'],
    ];

    foreach ($allowed as $email => $uris) {
        $user = smokeUser($email);

        foreach ($uris as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }

        foreach ($forbidden[$email] as $uri) {
            $this->actingAs($user)->get($uri)->assertForbidden();
        }
    }
});

test('el aislamiento por sucursal bloquea fichas y recibos de otra sucursal', function (): void {
    $norte = smokeUser('norte@diseno-intelectual.test');
    $surEmployee = smokeEmployee('EMP-0018');
    $matrizItem = PayrollItem::query()
        ->whereHas('run.branch', fn ($query) => $query->where('code', 'MATRIZ'))
        ->firstOrFail();

    $this->actingAs($norte)->get("/employees/{$surEmployee->id}")->assertForbidden();
    $this->actingAs($norte)->get("/attendance/employee/{$surEmployee->id}")->assertForbidden();
    $this->actingAs($norte)->get("/payroll/items/{$matrizItem->id}/receipt")->assertForbidden();
});

test('el recibo imprime conceptos, totales y el aviso de documento preliminar', function (): void {
    $admin = smokeUser('admin@diseno-intelectual.test');
    $item = PayrollItem::query()
        ->whereHas('concepts')
        ->orderBy('id')
        ->firstOrFail();

    $this->actingAs($admin)
        ->get("/payroll/items/{$item->id}/receipt")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('payroll/Receipt')
            ->has('item.concepts')
            ->where('item.totals.net', fn (string $net): bool => is_numeric($net))
            ->where('notice', fn (string $notice): bool => str_contains($notice, 'CFDI'))
            ->missing('item.uuid')
            ->missing('item.rfc'));
});
