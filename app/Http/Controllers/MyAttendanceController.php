<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\MyAttendanceRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendancePeriodSummary;
use App\Services\Attendance\AttendanceRowPresenter;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mi asistencia»: solo lectura del colaborador vinculado a `employee.user_id`.
 *
 * No exige permisos de asistencia; el alcance se limita al propio colaborador.
 * Sin vínculo se devuelve `employee: null` y la vista muestra el estado vacío.
 */
class MyAttendanceController extends Controller
{
    public function __construct(private readonly AttendancePeriodSummary $summary) {}

    public function index(MyAttendanceRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $employee = Employee::query()
            ->with('branch:id,name,timezone')
            ->where('user_id', $user->id)
            ->first();

        $timezone = $employee?->branch->timezone ?? AttendancePeriodSummary::DEFAULT_TIMEZONE;
        $filters = $request->validated();
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $from = isset($filters['from'])
            ? CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()
            : $today->startOfMonth();

        $to = isset($filters['to'])
            ? CarbonImmutable::parse($filters['to'], $timezone)->startOfDay()
            : $today;

        if ($to->lessThan($from)) {
            $to = $from;
        }

        if ($from->diffInDays($to) > AttendancePeriodSummary::MAX_DAYS - 1) {
            $from = $to->subDays(AttendancePeriodSummary::MAX_DAYS - 1);
        }

        $results = $employee instanceof Employee
            ? $this->summary->daysFor(collect([$employee]), $from, $to)
            : [];

        return Inertia::render('my/Attendance', [
            'employee' => $employee instanceof Employee
                ? [
                    'id' => $employee->id,
                    'full_name' => $employee->fullName(),
                    'employee_number' => $employee->employee_number,
                    'branch' => [
                        'id' => $employee->branch->id,
                        'name' => $employee->branch->name,
                        'timezone' => $employee->branch->timezone,
                    ],
                ]
                : null,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'days' => $employee instanceof Employee
                ? array_map(
                    fn ($result): array => AttendanceRowPresenter::fromResult($result, $employee),
                    $results,
                )
                : [],
            'totals' => $this->summary->totals($results),
        ]);
    }
}
