<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\IndexAttendanceRequest;
use App\Http\Requests\Attendance\ShowAttendanceRequest;
use App\Models\AttendanceEvent;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendancePeriodSummary;
use App\Services\Attendance\AttendanceRowPresenter;
use App\Support\BranchAccess;
use App\Support\Branches\BranchOptions;
use App\Support\Pagination\PaginatedPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Listado y ficha de asistencias (§7.3).
 *
 * Las filas son días por colaborador; el motor de jornada calcula retardos,
 * descansos, faltas y horas extra propuestas. El aislamiento por sucursal se
 * aplica en la consulta de colaboradores (`BranchAccess`, D-02).
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendancePeriodSummary $summary) {}

    public function index(IndexAttendanceRequest $request): Response
    {
        $this->authorize('viewAny', AttendanceEvent::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();

        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $employeeId = isset($filters['employee_id']) ? (int) $filters['employee_id'] : null;
        $search = $filters['search'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 25);

        [$from, $to] = $this->period($branchId, $filters['from'] ?? null, $filters['to'] ?? null);

        $employees = $this->summary->employeesQuery($branchId, $employeeId, $search)->get();
        $results = $this->summary->daysFor($employees, $from, $to);
        $rows = $this->summary->rowsFrom($employees, $results);

        return Inertia::render('attendance/Index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'branch_id' => $branchId,
                'employee_id' => $employeeId,
                'search' => $search,
                'per_page' => $perPage,
            ],
            'rows' => PaginatedPayload::from($this->paginateRows($request, $rows, $perPage)),
            'summary' => $this->summary->summarize($results),
            'options' => [
                'branches' => BranchOptions::forUser($user),
            ],
            'can' => [
                'manage' => $user->hasPermission('attendance.manage'),
                'incidents' => $user->hasPermission('incidents.view'),
            ],
        ]);
    }

    public function show(ShowAttendanceRequest $request, Employee $employee): Response
    {
        $this->authorize('viewAny', AttendanceEvent::class);

        /** @var User $user */
        $user = $request->user();
        abort_unless(BranchAccess::allows($user, $employee->branch_id), 403);

        $employee->loadMissing('branch:id,name,timezone');

        $filters = $request->validated();
        [$from, $to] = $this->period(
            $employee->branch_id,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $employee->branch->timezone,
        );

        $results = $this->summary->daysFor(collect([$employee]), $from, $to);

        return Inertia::render('attendance/Employee', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
                'branch' => [
                    'id' => $employee->branch->id,
                    'name' => $employee->branch->name,
                    'timezone' => $employee->branch->timezone,
                ],
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'days' => array_map(
                fn ($result): array => AttendanceRowPresenter::fromResult($result, $employee),
                $results,
            ),
            'totals' => $this->summary->totals($results),
            'can' => [
                'manage' => $user->hasPermission('attendance.manage'),
                'incidents' => $user->hasPermission('incidents.view'),
            ],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateRows(Request $request, array $rows, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) $request->integer('page', 1));

        $paginator = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );

        return $paginator->appends($request->query());
    }

    /**
     * Rango efectivo del listado, expresado en la zona horaria de la sucursal.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(?int $branchId, ?string $from, ?string $to, ?string $timezone = null): array
    {
        $timezone ??= $branchId !== null
            ? (string) Branch::query()->whereKey($branchId)->value('timezone')
            : AttendancePeriodSummary::DEFAULT_TIMEZONE;

        if ($timezone === '') {
            $timezone = AttendancePeriodSummary::DEFAULT_TIMEZONE;
        }

        $today = CarbonImmutable::now($timezone)->startOfDay();

        $fromDate = $from !== null
            ? CarbonImmutable::parse($from, $timezone)->startOfDay()
            : $today->subDays(AttendancePeriodSummary::DEFAULT_DAYS - 1);

        $toDate = $to !== null
            ? CarbonImmutable::parse($to, $timezone)->startOfDay()
            : $today;

        if ($toDate->lessThan($fromDate)) {
            $toDate = $fromDate;
        }

        if ($fromDate->diffInDays($toDate) > AttendancePeriodSummary::MAX_DAYS - 1) {
            $fromDate = $toDate->subDays(AttendancePeriodSummary::MAX_DAYS - 1);
        }

        return [$fromDate, $toDate];
    }
}
