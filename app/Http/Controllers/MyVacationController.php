<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Models\VacationPeriod;
use App\Models\VacationRequest;
use App\Services\Vacations\VacationBalanceService;
use App\Support\Pagination\PaginatedPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta de sólo lectura de las vacaciones del colaborador vinculado al
 * usuario autenticado (`employees.user_id`, §7.3).
 */
class MyVacationController extends Controller
{
    public function __construct(private readonly VacationBalanceService $balances) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $employee = Employee::query()
            ->with('branch:id,name,timezone')
            ->where('user_id', $user->id)
            ->first();

        if (! $employee instanceof Employee) {
            return Inertia::render('my/Vacations', [
                'linked' => false,
                'employee' => null,
                'periods' => [],
                'requests' => [],
                'totals' => null,
                'notice' => 'Tu cuenta no está vinculada a un colaborador. Pide a Recursos Humanos que la vincule para consultar tus vacaciones.',
            ]);
        }

        $periods = $employee->vacationPeriods()
            ->orderByDesc('period_start')
            ->get();

        $balances = $this->balances->forPeriods($periods);
        $today = CarbonImmutable::now()->startOfDay();

        $rows = $periods->map(fn (VacationPeriod $period): array => [
            'id' => $period->id,
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
            'anniversary_years' => $period->anniversary_years,
            'days_granted' => $period->days_granted,
            'days_adjustment' => $period->days_adjustment,
            'days_taken' => $balances[$period->id]['days_taken'] ?? '0.00',
            'days_pending' => $balances[$period->id]['days_pending'] ?? '0.00',
            'days_available' => $balances[$period->id]['days_available'] ?? $period->days_granted,
            'legal_reference' => $period->legal_reference,
            'status' => $today->greaterThan($period->period_end)
                ? 'closed'
                : ($today->lessThan($period->period_start) ? 'pending' : 'current'),
        ])->values()->all();

        $requests = $employee->vacationRequests()
            ->orderByDesc('start_date')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (VacationRequest $vacationRequest): array => [
                'id' => $vacationRequest->id,
                'start_date' => $vacationRequest->start_date->toDateString(),
                'end_date' => $vacationRequest->end_date->toDateString(),
                'days' => $vacationRequest->days,
                'status' => $vacationRequest->status->value,
                'status_label' => $vacationRequest->status->label(),
                'decided_at' => $vacationRequest->decided_at?->toIso8601String(),
                'notes' => $vacationRequest->notes,
            ]);

        return Inertia::render('my/Vacations', [
            'linked' => true,
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
                'hire_date' => $employee->hire_date->toDateString(),
                'branch' => [
                    'id' => $employee->branch->id,
                    'name' => $employee->branch->name,
                    'timezone' => $employee->branch->timezone,
                ],
            ],
            'period' => $this->consultedPeriod($periods),
            'periods' => $rows,
            'requests' => PaginatedPayload::from($requests),
            'totals' => $this->balances->forEmployee($employee),
            'notice' => null,
        ]);
    }

    /**
     * Rango consultado: del primer periodo otorgado al último.
     *
     * @param  Collection<int, VacationPeriod>  $periods
     * @return array{from: string, to: string}|null
     */
    private function consultedPeriod(Collection $periods): ?array
    {
        if ($periods->isEmpty()) {
            return null;
        }

        $first = $periods->last();
        $last = $periods->first();

        return [
            'from' => $first->period_start->toDateString(),
            'to' => $last->period_end->toDateString(),
        ];
    }
}
