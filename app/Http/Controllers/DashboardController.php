<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Http\Requests\Aws\IndexDashboardRequest;
use App\Models\AttendanceIncident;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Attendance\AttendanceDayResult;
use App\Services\Attendance\AttendancePeriodSummary;
use App\Services\Payroll\PayrollMoney;
use App\Support\BranchAccess;
use App\Support\Branches\BranchOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard administrativo (§7.4).
 *
 * Todas las cifras se calculan desde datos reales con el motor de jornada y
 * las incidencias registradas; no hay valores estáticos. El aislamiento por
 * sucursal se aplica en cada consulta (`BranchAccess`, D-02) y, si el módulo
 * de pre-nómina aún no tiene periodos, el bloque `payroll` responde vacío.
 */
class DashboardController extends Controller
{
    /** Días de la tendencia solicitada por el contrato. */
    private const TREND_DAYS = 14;

    /** Periodos recientes ofrecidos en el filtro. */
    private const PERIOD_OPTION_LIMIT = 24;

    public function __construct(private readonly AttendancePeriodSummary $attendance) {}

    public function index(IndexDashboardRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();
        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;

        abort_if($branchId !== null && ! BranchAccess::allows($user, $branchId), 403);

        $timezone = $this->timezone($branchId);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $payrollPeriod = $this->selectedPeriod(isset($filters['period_id']) ? (int) $filters['period_id'] : null);

        [$from, $to] = $payrollPeriod instanceof PayrollPeriod
            ? [
                CarbonImmutable::parse($payrollPeriod->start_date->toDateString(), $timezone)->startOfDay(),
                CarbonImmutable::parse($payrollPeriod->end_date->toDateString(), $timezone)->endOfDay(),
            ]
            : [$today->subDays(self::TREND_DAYS - 1), $today->endOfDay()];

        /** @var Collection<int, Employee> $employees */
        $employees = $this->attendance->employeesQuery($branchId)->get();
        $results = $this->attendance->daysFor($employees, $from, $to);
        $summary = $this->attendance->summarize($results);

        $incidentsBase = $this->incidentsQuery($branchId, $from, $to);
        $approvedIncidents = (clone $incidentsBase)->where('status', IncidentStatus::Approved);

        $justified = $this->quantityCents(
            (clone $approvedIncidents)->whereIn('type', [
                IncidentType::JustifiedAbsence,
                IncidentType::SickLeave,
                IncidentType::PaidLeave,
            ])->pluck('quantity'),
        );

        $unjustified = $this->quantityCents(
            (clone $approvedIncidents)->where('type', IncidentType::UnjustifiedAbsence)->pluck('quantity'),
        );

        return Inertia::render('Dashboard', [
            'filters' => [
                'period_id' => $payrollPeriod?->id,
                'branch_id' => $branchId,
            ],
            'metrics' => [
                'active_employees' => $employees->count(),
                'late_count' => (int) $summary['late_count'],
                'justified_absences' => PayrollMoney::toDecimal($justified),
                'unjustified_absences' => PayrollMoney::toDecimal($unjustified),
                'overtime_minutes' => (int) $summary['overtime_minutes'],
                'incidents_count' => (clone $incidentsBase)->count(),
            ],
            'by_branch' => $this->byBranch($employees, $results),
            'trend' => $this->trend($employees, $today),
            'recent_incidents' => $this->recentIncidents($incidentsBase),
            'payroll' => $this->payroll($payrollPeriod, $user, $branchId),
            'can' => [
                'payroll' => $user->hasPermission('payroll.view'),
                'incidents' => $user->hasPermission('incidents.view'),
                'attendance' => $user->hasPermission('attendance.view'),
                'aws_usage' => $user->hasPermission('aws.usage.view'),
                'audit' => $user->hasPermission('audit.view'),
            ],
            'options' => [
                'branches' => BranchOptions::forUser($user),
                'periods' => $this->periodOptions($user),
            ],
        ]);
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  array<int, AttendanceDayResult>  $results
     * @return array<int, array{branch: array{id: int, name: string}, employees: int, late_count: int, absences: int, overtime_minutes: int}>
     */
    private function byBranch(Collection $employees, array $results): array
    {
        $branches = $employees
            ->map(fn (Employee $employee): array => [
                'id' => $employee->branch_id,
                'name' => $employee->branch->name,
            ])
            ->unique('id')
            ->sortBy('name')
            ->values();

        $rows = [];

        foreach ($branches as $branch) {
            $branchResults = array_values(array_filter(
                $results,
                fn (AttendanceDayResult $result): bool => $result->branchId === $branch['id'],
            ));

            $lateCount = 0;
            $absences = 0;
            $overtime = 0;

            foreach ($branchResults as $result) {
                if ($result->lateMinutes > 0) {
                    $lateCount++;
                }

                if ($result->status === AttendanceDayResult::STATUS_ABSENCE) {
                    $absences++;
                }

                $overtime += $result->overtimeMinutes;
            }

            $rows[] = [
                'branch' => ['id' => $branch['id'], 'name' => $branch['name']],
                'employees' => $employees->where('branch_id', $branch['id'])->count(),
                'late_count' => $lateCount,
                'absences' => $absences,
                'overtime_minutes' => $overtime,
            ];
        }

        return $rows;
    }

    /**
     * Tendencia de los últimos 14 días (independiente del periodo filtrado).
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array{date: string, late_count: int, absences: int}>
     */
    private function trend(Collection $employees, CarbonImmutable $today): array
    {
        $from = $today->subDays(self::TREND_DAYS - 1);
        $to = $today->endOfDay();
        $results = $this->attendance->daysFor($employees, $from, $to);

        $lateByDate = [];
        $absencesByDate = [];

        foreach ($results as $result) {
            if ($result->lateMinutes > 0) {
                $lateByDate[$result->workDate] = ($lateByDate[$result->workDate] ?? 0) + 1;
            }

            if ($result->status === AttendanceDayResult::STATUS_ABSENCE) {
                $absencesByDate[$result->workDate] = ($absencesByDate[$result->workDate] ?? 0) + 1;
            }
        }

        $trend = [];

        for ($cursor = $from; $cursor->lessThanOrEqualTo($today); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();
            $trend[] = [
                'date' => $date,
                'late_count' => $lateByDate[$date] ?? 0,
                'absences' => $absencesByDate[$date] ?? 0,
            ];
        }

        return $trend;
    }

    /**
     * @param  Builder<AttendanceIncident>  $incidentsBase
     * @return array<int, array<string, mixed>>
     */
    private function recentIncidents(Builder $incidentsBase): array
    {
        return (clone $incidentsBase)
            ->with(['employee:id,first_name,last_name,second_last_name', 'branch:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (AttendanceIncident $incident): array => [
                'id' => $incident->id,
                'employee' => [
                    'id' => $incident->employee->id,
                    'full_name' => $incident->employee->fullName(),
                ],
                'branch' => [
                    'id' => $incident->branch->id,
                    'name' => $incident->branch->name,
                ],
                'type' => $incident->type->value,
                'start_date' => $incident->start_date->toDateString(),
                'end_date' => $incident->end_date->toDateString(),
                'status' => $incident->status->value,
            ])
            ->values()
            ->all();
    }

    /**
     * Bloque de pre-nómina: sin periodos responde `period=null` y totales en
     * cero, sin romper el dashboard.
     *
     * @return array{period: array<string, mixed>|null, status: string|null, totals: array{earnings: string, deductions: string, net: string}}
     */
    private function payroll(?PayrollPeriod $period, User $user, ?int $branchId): array
    {
        $totals = ['earnings' => '0.00', 'deductions' => '0.00', 'net' => '0.00'];

        if (! $period instanceof PayrollPeriod) {
            return ['period' => null, 'status' => null, 'totals' => $totals];
        }

        $allowedBranchIds = $user->allowedBranchIds();

        /** @var Collection<int, PayrollRun> $runs */
        $runs = $period->runs()
            ->when($branchId !== null, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->when(
                $allowedBranchIds !== null,
                fn (Builder $query): Builder => $query->whereIn('branch_id', $allowedBranchIds),
            )
            ->get(['branch_id', 'total_earnings', 'total_deductions', 'total_net']);

        $totals = [
            'earnings' => PayrollMoney::toDecimal($this->amountCents($runs, 'total_earnings')),
            'deductions' => PayrollMoney::toDecimal($this->amountCents($runs, 'total_deductions')),
            'net' => PayrollMoney::toDecimal($this->amountCents($runs, 'total_net')),
        ];

        return [
            'period' => [
                'id' => $period->id,
                'label' => $this->periodLabel($period),
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $period->status->value,
            ],
            // El frontend pinta la insignia de estado con `payroll.status`
            // (types/dashboard.ts); se expone junto a `period` para no
            // depender de `period.status` y mantener ambos bloques alineados.
            'status' => $period->status->value,
            'totals' => $totals,
        ];
    }

    /**
     * @param  Collection<int, PayrollRun>  $runs
     */
    private function amountCents(Collection $runs, string $column): int
    {
        return $runs->sum(fn (PayrollRun $run): int => PayrollMoney::toCents($run->getAttribute($column)));
    }

    private function selectedPeriod(?int $periodId): ?PayrollPeriod
    {
        return PayrollPeriod::query()
            ->when($periodId !== null, fn (Builder $query): Builder => $query->whereKey($periodId))
            ->when($periodId === null, fn (Builder $query): Builder => $query->orderByDesc('end_date'))
            ->first();
    }

    /**
     * Periodos ofrecidos en el filtro: los que no tienen corridas o los que
     * tienen al menos una corrida de una sucursal visible (D-02). Un actor con
     * sucursales acotadas no ve periodos exclusivos de otras sucursales.
     *
     * @return array<int, array{id: int, label: string}>
     */
    private function periodOptions(User $user): array
    {
        $branchIds = $user->allowedBranchIds();

        return PayrollPeriod::query()
            ->when(
                $branchIds !== null,
                fn (Builder $query): Builder => $query->where(function (Builder $scope) use ($branchIds): void {
                    $scope->doesntHave('runs');

                    if ($branchIds !== []) {
                        $scope->orWhereHas('runs', fn (Builder $runs) => $runs->whereIn('branch_id', $branchIds));
                    }
                }),
            )
            ->orderByDesc('end_date')
            ->limit(self::PERIOD_OPTION_LIMIT)
            ->get(['id', 'start_date', 'end_date', 'payment_date', 'status'])
            ->map(fn (PayrollPeriod $period): array => [
                'id' => $period->id,
                'label' => $this->periodLabel($period),
            ])
            ->values()
            ->all();
    }

    private function periodLabel(PayrollPeriod $period): string
    {
        return 'Del '.$period->start_date->format('d/m/Y').' al '.$period->end_date->format('d/m/Y');
    }

    /**
     * @return Builder<AttendanceIncident>
     */
    private function incidentsQuery(?int $branchId, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return BranchAccess::scope(AttendanceIncident::query())
            ->overlapping($from->toDateString(), $to->toDateString())
            ->when($branchId !== null, fn (Builder $query): Builder => $query->where('branch_id', $branchId));
    }

    /**
     * @param  Collection<int, mixed>  $quantities
     */
    private function quantityCents(Collection $quantities): int
    {
        return $quantities->sum(fn (mixed $quantity): int => PayrollMoney::toCents(is_string($quantity) || is_numeric($quantity) ? (string) $quantity : null));
    }

    private function timezone(?int $branchId): string
    {
        if ($branchId === null) {
            return AttendancePeriodSummary::DEFAULT_TIMEZONE;
        }

        $timezone = Branch::query()->whereKey($branchId)->value('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : AttendancePeriodSummary::DEFAULT_TIMEZONE;
    }
}
