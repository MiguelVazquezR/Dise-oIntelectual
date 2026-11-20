<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Enums\VacationRequestStatus;
use App\Http\Requests\Vacations\GenerateVacationPeriodsRequest;
use App\Http\Requests\Vacations\IndexVacationRequest;
use App\Models\Employee;
use App\Models\User;
use App\Models\VacationPeriod;
use App\Models\VacationRequest;
use App\Services\Vacations\VacationAccrualRules;
use App\Services\Vacations\VacationAuditRecorder;
use App\Services\Vacations\VacationBalanceService;
use App\Services\Vacations\VacationPeriodGenerator;
use App\Support\BranchAccess;
use App\Support\Branches\BranchOptions;
use App\Support\Pagination\PaginatedPayload;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Periodos y saldos de vacaciones por aniversario (LFT art. 76, D-11).
 *
 * Contrato de props en ARCHITECTURE.md §7.3: `filters`, `periods` paginado con
 * saldos, `requests` paginado, `options` y `can`.
 */
class VacationController extends Controller
{
    public function __construct(
        private readonly VacationPeriodGenerator $generator,
        private readonly VacationBalanceService $balances,
        private readonly VacationAuditRecorder $audit,
    ) {}

    /**
     * Listado de periodos de vacaciones con su saldo.
     */
    public function index(IndexVacationRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();
        $year = (int) ($filters['year'] ?? CarbonImmutable::now()->year);
        $branchFilter = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $employeeFilter = isset($filters['employee_id']) ? (int) $filters['employee_id'] : null;

        if ($branchFilter !== null) {
            abort_unless(BranchAccess::allows($user, $branchFilter), 403);
        }

        $periodsPaginator = $this->periodsQuery($branchFilter, $employeeFilter, $year)
            ->paginate(15)
            ->withQueryString();

        $balances = $this->balances->forPeriods(collect($periodsPaginator->items()));

        $periodsPaginator->through(
            fn (VacationPeriod $period): array => $this->periodRow($period, $balances[$period->id] ?? null),
        );

        $requestsPaginator = $this->requestsQuery($branchFilter, $employeeFilter, $year)
            ->paginate(10, ['*'], 'requests_page')
            ->withQueryString()
            ->through(fn (VacationRequest $vacationRequest): array => $this->requestRow($vacationRequest));

        return Inertia::render('vacations/Index', [
            'filters' => [
                'year' => $year,
                'branch_id' => $branchFilter,
                'employee_id' => $employeeFilter,
            ],
            'periods' => PaginatedPayload::from($periodsPaginator),
            'requests' => PaginatedPayload::from($requestsPaginator),
            'options' => [
                'branches' => BranchOptions::forUser($user),
                'years' => $this->yearOptions(),
                'employees' => $this->employeeOptions(),
                'request_statuses' => array_map(
                    fn (VacationRequestStatus $status): array => [
                        'value' => $status->value,
                        'label' => $status->label(),
                    ],
                    VacationRequestStatus::cases(),
                ),
            ],
            'rules' => [
                'legal_basis' => VacationAccrualRules::LEGAL_BASIS,
                'source_url' => VacationAccrualRules::sourceUrl(),
                'source_checked_at' => VacationAccrualRules::SOURCE_CHECKED_AT,
                'table' => VacationAccrualRules::table(),
                'legal_notice' => VacationAccrualRules::validationNotice(),
            ],
            'can' => [
                'manage' => $user->hasPermission('vacations.manage'),
                'approve' => $user->hasPermission('vacations.approve'),
            ],
        ]);
    }

    /**
     * Genera los periodos por aniversario del año indicado (idempotente).
     */
    public function generatePeriods(GenerateVacationPeriodsRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $year = (int) $request->validated('year');

        $result = $this->generator->forYear($year, $user->allowedBranchIds());

        $this->audit->record('vacation.periods.generated', null, $user, null, [
            'year' => $year,
            'employees' => $result['employees'],
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);

        $message = $result['created'] === 0
            ? "No había periodos nuevos por generar en {$year}: {$result['skipped']} ya existían."
            : "Periodos {$year}: {$result['created']} nuevos y {$result['skipped']} ya existentes.";

        return to_route('vacations.index', ['year' => $year])->with('success', $message);
    }

    /**
     * @return Builder<VacationPeriod>
     */
    private function periodsQuery(?int $branchFilter, ?int $employeeFilter, int $year): Builder
    {
        return VacationPeriod::query()
            ->with([
                'employee:id,employee_number,first_name,last_name,second_last_name,hire_date,branch_id',
                'employee.branch:id,name',
            ])
            ->whereIn('employee_id', $this->visibleEmployeesQuery($branchFilter))
            ->when($employeeFilter !== null, fn (Builder $query) => $query->where('employee_id', $employeeFilter))
            ->whereYear('period_start', $year)
            ->orderByDesc('period_start')
            ->orderBy('employee_id');
    }

    /**
     * @return Builder<VacationRequest>
     */
    private function requestsQuery(?int $branchFilter, ?int $employeeFilter, int $year): Builder
    {
        return VacationRequest::query()
            ->with([
                'employee:id,employee_number,first_name,last_name,second_last_name,branch_id',
                'requester:id,name',
                'decider:id,name',
            ])
            ->whereIn('employee_id', $this->visibleEmployeesQuery($branchFilter))
            ->when($employeeFilter !== null, fn (Builder $query) => $query->where('employee_id', $employeeFilter))
            ->whereYear('start_date', $year)
            ->orderByDesc('start_date')
            ->orderByDesc('id');
    }

    /**
     * Subconsulta de colaboradores visibles por aislamiento de sucursal (D-02).
     *
     * @return Builder<Employee>
     */
    private function visibleEmployeesQuery(?int $branchFilter): Builder
    {
        $query = BranchAccess::scope(Employee::query())->select('employees.id');

        if ($branchFilter !== null) {
            $query->where('employees.branch_id', $branchFilter);
        }

        return $query;
    }

    /**
     * @param  array{
     *     period_id: int,
     *     employee_id: int,
     *     days_granted: string,
     *     days_adjustment: string,
     *     days_taken: string,
     *     days_pending: string,
     *     days_available: string,
     *     overdrawn: bool
     * }|null  $balance
     * @return array<string, mixed>
     */
    private function periodRow(VacationPeriod $period, ?array $balance): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        return [
            'id' => $period->id,
            'employee' => [
                'id' => $period->employee->id,
                'full_name' => $period->employee->fullName(),
                'employee_number' => $period->employee->employee_number,
                'hire_date' => $period->employee->hire_date->toDateString(),
            ],
            'branch' => [
                'id' => $period->employee->branch->id,
                'name' => $period->employee->branch->name,
            ],
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
            'anniversary_years' => $period->anniversary_years,
            'days_granted' => $period->days_granted,
            'days_adjustment' => $period->days_adjustment,
            'days_taken' => $balance['days_taken'] ?? '0.00',
            'days_pending' => $balance['days_pending'] ?? '0.00',
            'days_available' => $balance['days_available'] ?? (string) $period->days_granted,
            'legal_reference' => $period->legal_reference,
            'status' => $this->periodStatus($period, $today),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestRow(VacationRequest $vacationRequest): array
    {
        return [
            'id' => $vacationRequest->id,
            'employee' => [
                'id' => $vacationRequest->employee->id,
                'full_name' => $vacationRequest->employee->fullName(),
                'employee_number' => $vacationRequest->employee->employee_number,
            ],
            'start_date' => $vacationRequest->start_date->toDateString(),
            'end_date' => $vacationRequest->end_date->toDateString(),
            'days' => $vacationRequest->days,
            'status' => $vacationRequest->status->value,
            'status_label' => $vacationRequest->status->label(),
            'requested_by' => $vacationRequest->requester?->name,
            'decided_by' => $vacationRequest->decider?->name,
            'decided_at' => $vacationRequest->decided_at?->toIso8601String(),
            'notes' => $vacationRequest->notes,
        ];
    }

    /**
     * `current` si hoy cae dentro del periodo, `closed` si ya terminó y
     * `pending` si todavía no inicia (etiquetas de `lib/labels.ts`).
     */
    private function periodStatus(VacationPeriod $period, CarbonImmutable $today): string
    {
        if ($today->greaterThan($period->period_end)) {
            return 'closed';
        }

        return $today->lessThan($period->period_start) ? 'pending' : 'current';
    }

    /**
     * @return array<int, int>
     */
    private function yearOptions(): array
    {
        $current = (int) CarbonImmutable::now()->year;

        return array_reverse(range($current - 3, $current + 1));
    }

    /**
     * Catálogo de colaboradores visibles para el formulario de solicitud.
     *
     * @return array<int, array{id: int, name: string, employee_number: string}>
     */
    private function employeeOptions(): array
    {
        return BranchAccess::scope(Employee::query())
            ->where('status', EmployeeStatus::Active->value)
            ->orderBy('first_name')
            ->limit(500)
            ->get(['id', 'employee_number', 'first_name', 'last_name', 'second_last_name'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
            ])
            ->values()
            ->all();
    }
}
