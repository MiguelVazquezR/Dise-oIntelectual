<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\AdjustmentStatus;
use App\Enums\EmployeeStatus;
use App\Enums\PayrollConceptType;
use App\Enums\PayrollPeriodStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\DecideAdjustmentRequest;
use App\Http\Requests\Payroll\IndexAdjustmentRequest;
use App\Http\Requests\Payroll\StoreAdjustmentRequest;
use App\Models\Employee;
use App\Models\EmployeePayrollAdjustment;
use App\Models\PayrollConcept;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\PayrollAuditRecorder;
use App\Support\BranchAccess;
use App\Support\Branches\BranchOptions;
use App\Support\Pagination\PaginatedPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ajustes de pre-nómina: bonos y penalizaciones (§7.4).
 *
 * - La sucursal del ajuste se deriva del colaborador; nunca viene del cliente.
 * - Los conceptos `is_sensitive` (penalizaciones administrativas) solo los
 *   registran y aprueban usuarios con `adjustments.approve`.
 * - Un ajuste `approved` se aplica en el siguiente cálculo de su periodo; el
 *   recálculo lo reemplaza, no lo duplica (el vínculo vive en
 *   `applied_payroll_item_id`).
 */
class AdjustmentController extends Controller
{
    public function __construct(private readonly PayrollAuditRecorder $auditor) {}

    public function index(IndexAdjustmentRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);

        $adjustments = EmployeePayrollAdjustment::query()
            ->with([
                'employee:id,employee_number,first_name,last_name,second_last_name,branch_id',
                'employee.branch:id,name',
                'concept:id,code,name,type,is_sensitive',
                'creator:id,name',
                'approver:id,name',
            ])
            ->whereIn('employee_id', BranchAccess::scope(Employee::query())->select('employees.id'))
            ->when($filters['period_id'] ?? null, fn (Builder $query, int|string $periodId) => $query->where('payroll_period_id', (int) $periodId))
            ->when($filters['employee_id'] ?? null, fn (Builder $query, int|string $employeeId) => $query->where('employee_id', (int) $employeeId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branchId) => $query->whereHas(
                'employee',
                fn (Builder $inner) => $inner->where('branch_id', (int) $branchId),
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->whereHas(
                'concept',
                fn (Builder $inner) => $inner->where('type', $type),
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (EmployeePayrollAdjustment $adjustment): array => $this->row($adjustment));

        return Inertia::render('adjustments/Index', [
            'filters' => [
                'period_id' => isset($filters['period_id']) ? (int) $filters['period_id'] : null,
                'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
                'employee_id' => isset($filters['employee_id']) ? (int) $filters['employee_id'] : null,
                'status' => $filters['status'] ?? null,
                'type' => $filters['type'] ?? null,
                'per_page' => $perPage,
            ],
            'adjustments' => PaginatedPayload::from($adjustments),
            'options' => [
                'branches' => BranchOptions::forUser($user),
                'concepts' => PayrollConcept::query()
                    ->active()
                    ->orderBy('code')
                    ->get(['id', 'code', 'name', 'type', 'is_sensitive'])
                    ->map(fn (PayrollConcept $concept): array => [
                        'id' => $concept->id,
                        'name' => $concept->code.' · '.$concept->name,
                        'code' => $concept->code,
                        'type' => $concept->type->value,
                        'is_sensitive' => $concept->is_sensitive,
                    ])
                    ->values()
                    ->all(),
                'periods' => $this->periodOptions($user),
                'employees' => $this->employeeOptions($user),
            ],
            'can' => [
                'manage' => $user->hasPermission('adjustments.manage'),
                'approve' => $user->hasPermission('adjustments.approve'),
            ],
            'warning' => 'Los ajustes marcados como sensibles (penalizaciones administrativas) requieren el permiso de aprobación de ajustes.',
        ]);
    }

    public function store(StoreAdjustmentRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        abort_unless(BranchAccess::allows($user, $employee->branch_id), 403);

        $concept = PayrollConcept::query()->findOrFail((int) $data['payroll_concept_id']);

        if ($concept->is_sensitive && ! $user->hasPermission('adjustments.approve')) {
            abort(403, 'Los ajustes sensibles requieren el permiso de aprobación de ajustes.');
        }

        $period = null;

        if (($data['payroll_period_id'] ?? null) !== null) {
            $period = PayrollPeriod::query()->findOrFail((int) $data['payroll_period_id']);

            if ($period->status === PayrollPeriodStatus::Closed) {
                return back()->with('error', 'No se pueden registrar ajustes en un periodo cerrado.');
            }

            $branchIds = $user->allowedBranchIds();

            if ($branchIds !== null
                && $period->runs()->exists()
                && ! $period->runs()->whereIn('branch_id', $branchIds)->exists()) {
                abort(403, 'El periodo pertenece a sucursales que no puedes consultar.');
            }
        }

        $adjustment = DB::transaction(function () use ($request, $data, $employee, $concept, $period, $user): EmployeePayrollAdjustment {
            $adjustment = EmployeePayrollAdjustment::query()->create([
                'employee_id' => $employee->id,
                'payroll_period_id' => $period?->id,
                'payroll_concept_id' => $concept->id,
                'amount' => (string) $data['amount'],
                'quantity' => (string) $data['quantity'],
                'reason' => (string) $data['reason'],
                'status' => AdjustmentStatus::Pending,
                'created_by' => $user->id,
                'is_sensitive' => $concept->is_sensitive,
            ]);

            $this->auditor->adjustmentCreated($adjustment, $user, $request->ip(), $request->userAgent());

            return $adjustment;
        });

        $kind = $concept->type === PayrollConceptType::Deduction ? 'penalización' : 'bono';

        return back()->with('success', ucfirst($kind).' registrada para '.$employee->fullName().'; queda pendiente de aprobación.');
    }

    public function decide(DecideAdjustmentRequest $request, EmployeePayrollAdjustment $adjustment): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $adjustment->loadMissing(['employee', 'concept', 'period']);

        abort_unless(BranchAccess::allows($user, $adjustment->employee->branch_id), 403);

        if ($adjustment->status !== AdjustmentStatus::Pending) {
            return back()->with('error', 'El ajuste ya fue decidido.');
        }

        if ($adjustment->period instanceof PayrollPeriod && $adjustment->period->status === PayrollPeriodStatus::Closed) {
            return back()->with('error', 'El periodo del ajuste está cerrado; no se puede aprobar ni rechazar.');
        }

        if ($adjustment->is_sensitive && ! $user->hasPermission('adjustments.approve')) {
            abort(403, 'Solo quien puede aprobar ajustes puede decidir una penalización sensible.');
        }

        $decision = AdjustmentStatus::from((string) $request->validated()['decision']);
        $before = $adjustment->only(['status', 'approved_by', 'approved_at']);

        DB::transaction(function () use ($request, $adjustment, $decision, $user, $before): void {
            $adjustment->update([
                'status' => $decision,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $this->auditor->adjustmentDecided($adjustment->refresh(), $user, $before, $request->ip(), $request->userAgent());
        });

        $label = $decision === AdjustmentStatus::Approved ? 'aprobado' : 'rechazado';

        $message = 'Ajuste '.$label.' correctamente.';

        if ($decision === AdjustmentStatus::Approved) {
            $message .= ' Recalcula la pre-nómina del periodo para aplicarlo.';
        }

        return back()->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(EmployeePayrollAdjustment $adjustment): array
    {
        $employee = $adjustment->employee;

        return [
            'id' => $adjustment->id,
            'employee' => [
                'id' => $adjustment->employee_id,
                'full_name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
            ],
            'branch' => [
                'id' => $employee->branch_id,
                'name' => $employee->branch->name,
            ],
            'concept' => [
                'id' => $adjustment->concept->id,
                'code' => $adjustment->concept->code,
                'name' => $adjustment->concept->name,
                'type' => $adjustment->concept->type->value,
            ],
            'amount' => (string) $adjustment->amount,
            'quantity' => (string) $adjustment->quantity,
            'reason' => $adjustment->reason,
            'status' => $adjustment->status->value,
            'created_by' => $adjustment->creator?->name,
            'approved_by' => $adjustment->approver?->name,
            'approved_at' => $adjustment->approved_at?->toIso8601String(),
            'is_sensitive' => $adjustment->is_sensitive,
        ];
    }

    /**
     * Colaboradores visibles para el alta de ajustes (búsqueda en el formulario).
     *
     * @return array<int, array{id: int, full_name: string, employee_number: string}>
     */
    private function employeeOptions(User $user): array
    {
        $query = Employee::query()->where('status', EmployeeStatus::Active);
        $branchIds = $user->allowedBranchIds();

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }

        return $query
            ->orderBy('first_name')
            ->limit(200)
            ->get(['id', 'first_name', 'last_name', 'second_last_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'full_name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
            ])
            ->values()
            ->all();
    }

    /**
     * Periodos recientes visibles para el actor (catálogo del formulario).
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function periodOptions(User $user): array
    {
        $branchIds = $user->allowedBranchIds();

        return PayrollPeriod::query()
            ->with('schedule:id,name')
            ->where(function (Builder $scope) use ($branchIds): void {
                $scope->doesntHave('runs');

                if ($branchIds === null) {
                    $scope->orWhereHas('runs');
                } elseif ($branchIds !== []) {
                    $scope->orWhereHas('runs', fn (Builder $runs) => $runs->whereIn('branch_id', $branchIds));
                }
            })
            ->orderByDesc('start_date')
            ->limit(24)
            ->get(['id', 'payroll_schedule_id', 'start_date', 'end_date', 'payment_date', 'status'])
            ->map(fn (PayrollPeriod $period): array => [
                'id' => $period->id,
                'name' => sprintf(
                    '%s · %s a %s (%s)',
                    $period->schedule->name,
                    $period->start_date->format('d/m/Y'),
                    $period->end_date->format('d/m/Y'),
                    $period->status->label(),
                ),
            ])
            ->values()
            ->all();
    }
}
