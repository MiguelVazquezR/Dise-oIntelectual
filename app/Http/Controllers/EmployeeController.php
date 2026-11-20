<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Enums\PayFrequency;
use App\Enums\VacationRequestStatus;
use App\Http\Requests\Employees\IndexEmployeeRequest;
use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeePinRequest;
use App\Http\Requests\Employees\UpdateEmployeeRequest;
use App\Models\AttendanceEvent;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\User;
use App\Services\Employees\EmployeeAuditRecorder;
use App\Support\BranchAccess;
use App\Support\Branches\BranchOptions;
use App\Support\Employees\CompensationRecorder;
use App\Support\Pagination\PaginatedPayload;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeAuditRecorder $auditor) {}

    /**
     * Listado de colaboradores visibles para el usuario.
     */
    public function index(IndexEmployeeRequest $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);

        $employees = $this->filteredQuery($request)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Employee $employee): array => $this->listRow($employee));

        return Inertia::render('employees/Index', [
            'filters' => [
                'search' => $filters['search'] ?? null,
                'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
                'status' => $filters['status'] ?? null,
                'per_page' => $perPage,
            ],
            'employees' => PaginatedPayload::from($employees),
            'options' => $this->options($user),
            'can' => [
                'manage' => $user->hasPermission('employees.manage'),
            ],
        ]);
    }

    /**
     * Formulario de alta de colaborador.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Employee::class);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('employees/Create', [
            'employee' => null,
            'options' => $this->options($user),
            'can' => [
                'manage' => $user->hasPermission('employees.manage'),
            ],
        ]);
    }

    /**
     * Registra un colaborador con su compensación inicial.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $data = $request->validated();
        $branch = Branch::query()->whereKey($data['branch_id'])->firstOrFail();
        $status = $data['status'] ?? EmployeeStatus::Active->value;

        $terminationDate = $status === EmployeeStatus::Terminated->value
            ? ($data['termination_date'] ?? CarbonImmutable::now($branch->timezone)->toDateString())
            : null;

        $employee = DB::transaction(function () use ($data, $branch, $status, $terminationDate): Employee {
            $employee = Employee::query()->create([
                'employee_number' => $data['employee_number'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'second_last_name' => $data['second_last_name'] ?? null,
                'branch_id' => $branch->id,
                'position' => $data['position'],
                'department' => $data['department'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'hire_date' => $data['hire_date'],
                'termination_date' => $terminationDate,
                'status' => $status,
            ]);

            CompensationRecorder::record($employee, [
                'base_salary' => (string) $data['compensation']['base_salary'],
                'pay_frequency' => $data['compensation']['pay_frequency'],
                'effective_from' => $data['compensation']['effective_from'] ?? $data['hire_date'],
                'notes' => $data['compensation']['notes'] ?? 'Compensación inicial',
            ]);

            return $employee;
        });

        return to_route('employees.show', $employee)
            ->with('success', "Colaborador «{$employee->fullName()}» registrado correctamente.");
    }

    /**
     * Ficha del colaborador con compensaciones, marcajes recientes y vacaciones.
     */
    public function show(Request $request, Employee $employee): Response
    {
        $this->authorize('view', $employee);

        /** @var User $user */
        $user = $request->user();

        $employee->load([
            'branch:id,name,timezone',
            'compensations' => fn ($query) => $query->orderByDesc('effective_from')->orderByDesc('id'),
        ]);

        $recentEvents = $employee->attendanceEvents()
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get(['id', 'type', 'occurred_at', 'method', 'validation_status']);

        return Inertia::render('employees/Show', [
            'employee' => $this->employeePayload($employee),
            'branch' => [
                'id' => $employee->branch->id,
                'name' => $employee->branch->name,
                'timezone' => $employee->branch->timezone,
            ],
            'compensations' => $employee->compensations
                ->map(fn (EmployeeCompensation $compensation): array => $this->compensationRow($compensation))
                ->values()
                ->all(),
            'recent_events' => $recentEvents
                ->map(fn (AttendanceEvent $event): array => $this->eventRow($event))
                ->values()
                ->all(),
            'vacation_summary' => $this->vacationSummary($employee),
            'can' => [
                'manage' => $user->hasPermission('employees.manage'),
            ],
        ]);
    }

    /**
     * Formulario de edición de colaborador.
     */
    public function edit(Request $request, Employee $employee): Response
    {
        $this->authorize('update', $employee);

        /** @var User $user */
        $user = $request->user();
        $employee->load([
            'branch:id,name',
            'compensations' => fn ($query) => $query->orderByDesc('effective_from')->orderByDesc('id'),
        ]);

        return Inertia::render('employees/Edit', [
            'employee' => $this->employeePayload($employee),
            'options' => $this->options($user),
            'can' => [
                'manage' => $user->hasPermission('employees.manage'),
            ],
        ]);
    }

    /**
     * Actualiza al colaborador y, si se envía, registra nueva compensación.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $data = $request->validated();
        $branch = Branch::query()->whereKey($data['branch_id'])->firstOrFail();
        $status = $data['status'] ?? $employee->status->value;

        if ($status === EmployeeStatus::Terminated->value) {
            $terminationDate = $data['termination_date']
                ?? $employee->termination_date?->toDateString()
                ?? CarbonImmutable::now($branch->timezone)->toDateString();
        } else {
            $terminationDate = null;
        }

        DB::transaction(function () use ($employee, $data, $branch, $status, $terminationDate): void {
            $employee->update([
                'employee_number' => $data['employee_number'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'second_last_name' => $data['second_last_name'] ?? null,
                'branch_id' => $branch->id,
                'position' => $data['position'],
                'department' => $data['department'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'hire_date' => $data['hire_date'],
                'termination_date' => $terminationDate,
                'status' => $status,
            ]);

            $compensation = $data['compensation'] ?? null;

            if (is_array($compensation) && $compensation !== []) {
                CompensationRecorder::record($employee, [
                    'base_salary' => (string) $compensation['base_salary'],
                    'pay_frequency' => $compensation['pay_frequency'],
                    'effective_from' => $compensation['effective_from']
                        ?? CarbonImmutable::now($branch->timezone)->toDateString(),
                    'notes' => $compensation['notes'] ?? null,
                ]);
            }
        });

        return to_route('employees.show', $employee)
            ->with('success', 'Colaborador actualizado correctamente.');
    }

    /**
     * Actualiza el PIN del checador (hash, 4–6 dígitos, nunca se devuelve) y
     * deja constancia en `audit_logs` sin guardar el PIN ni su hash.
     */
    public function updatePin(UpdateEmployeePinRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($employee, $request, $user): void {
            $employee->forceFill([
                'pin_hash' => Hash::make((string) $request->validated('pin')),
                'pin_updated_at' => now(),
            ])->save();

            $this->auditor->pinUpdated($employee, $user, $request->ip(), $request->userAgent());
        });

        return back()->with('success', 'PIN del colaborador actualizado correctamente.');
    }

    /**
     * Exporta el listado filtrado en CSV (UTF-8 con BOM) respetando el aislamiento.
     */
    public function export(IndexEmployeeRequest $request): StreamedResponse
    {
        $this->authorize('viewAny', Employee::class);

        $query = $this->filteredQuery($request)
            ->select(['employees.*'])
            ->addSelect('branches.name as branch_name')
            ->join('branches', 'branches.id', '=', 'employees.branch_id');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Número de empleado',
                'Nombre completo',
                'Sucursal',
                'Puesto',
                'Departamento',
                'Correo',
                'Fecha de ingreso',
                'Estatus',
            ]);

            foreach ($query->cursor() as $employee) {
                /** @var Employee $employee */
                fputcsv($handle, [
                    $this->csvCell($employee->employee_number),
                    $this->csvCell($employee->fullName()),
                    $this->csvCell($employee->getAttribute('branch_name')),
                    $this->csvCell($employee->position),
                    $this->csvCell($employee->department),
                    $this->csvCell($employee->email),
                    $employee->hire_date->toDateString(),
                    $employee->status->label(),
                ]);
            }

            fclose($handle);
        }, 'colaboradores-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return Builder<Employee>
     */
    private function filteredQuery(IndexEmployeeRequest $request): Builder
    {
        $filters = $request->validated();

        return BranchAccess::scope(Employee::query()->with('branch:id,name'))
            ->when(
                $filters['branch_id'] ?? null,
                fn (Builder $query, int|string $branchId) => $query->where('branch_id', (int) $branchId),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status),
            )
            ->when(
                $filters['search'] ?? null,
                function (Builder $query, string $search): void {
                    $query->where(function (Builder $inner) use ($search): void {
                        $inner->where('employee_number', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('second_last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                },
            )
            ->orderBy('employee_number');
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->fullName(),
            'branch' => [
                'id' => $employee->branch->id,
                'name' => $employee->branch->name,
            ],
            'position' => $employee->position,
            'hire_date' => $employee->hire_date->toDateString(),
            'status' => $employee->status->value,
            'has_pin' => $employee->pin_hash !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(Employee $employee): array
    {
        $compensation = $employee->relationLoaded('compensations')
            ? ($employee->compensations->first(
                fn (EmployeeCompensation $item): bool => $item->effective_to === null,
            ) ?? $employee->compensations->first())
            : $employee->compensations()
                ->whereNull('effective_to')
                ->orderByDesc('effective_from')
                ->first();

        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'second_last_name' => $employee->second_last_name,
            'branch_id' => $employee->branch_id,
            'position' => $employee->position,
            'department' => $employee->department,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'hire_date' => $employee->hire_date->toDateString(),
            'status' => $employee->status->value,
            'pin_updated_at' => $employee->pin_updated_at?->toIso8601String(),
            'has_pin' => $employee->pin_hash !== null,
            'compensation' => $compensation instanceof EmployeeCompensation
                ? [
                    'base_salary' => (string) $compensation->base_salary,
                    'pay_frequency' => $compensation->pay_frequency->value,
                    'effective_from' => $compensation->effective_from->toDateString(),
                    'notes' => $compensation->notes,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compensationRow(EmployeeCompensation $compensation): array
    {
        return [
            'id' => $compensation->id,
            'base_salary' => (string) $compensation->base_salary,
            'pay_frequency' => $compensation->pay_frequency->value,
            'effective_from' => $compensation->effective_from->toDateString(),
            'effective_to' => $compensation->effective_to?->toDateString(),
            'notes' => $compensation->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventRow(AttendanceEvent $event): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type->value,
            'type_label' => $event->type->label(),
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'method' => $event->method->value,
            'validation_status' => $event->validation_status->value,
        ];
    }

    /**
     * @return array{days_granted: string, days_taken: string, days_pending: string}
     */
    private function vacationSummary(Employee $employee): array
    {
        $granted = (float) $employee->vacationPeriods()->sum('days_granted')
            + (float) $employee->vacationPeriods()->sum('days_adjustment');

        $taken = (float) $employee->vacationRequests()
            ->where('status', VacationRequestStatus::Approved->value)
            ->sum('days');

        $pending = (float) $employee->vacationRequests()
            ->where('status', VacationRequestStatus::Pending->value)
            ->sum('days');

        return [
            'days_granted' => $this->decimal2($granted),
            'days_taken' => $this->decimal2($taken),
            'days_pending' => $this->decimal2($pending),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(User $user): array
    {
        return [
            'branches' => BranchOptions::forUser($user),
            'statuses' => array_map(
                fn (EmployeeStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                EmployeeStatus::cases(),
            ),
            'pay_frequencies' => array_map(
                fn (PayFrequency $frequency): array => [
                    'value' => $frequency->value,
                    'label' => $frequency->label(),
                ],
                PayFrequency::cases(),
            ),
        ];
    }

    private function decimal2(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    /**
     * Neutraliza la inyección de fórmulas en hojas de cálculo (CSV injection):
     * un valor que empieza con `=`, `+`, `-`, `@`, tabulador o retorno se
     * escribe como texto.
     */
    private function csvCell(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
