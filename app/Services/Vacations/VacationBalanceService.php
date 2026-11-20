<?php

namespace App\Services\Vacations;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\VacationRequestStatus;
use App\Models\AttendanceIncident;
use App\Models\Employee;
use App\Models\VacationPeriod;
use App\Models\VacationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Saldo de vacaciones por periodo laboral.
 *
 * Saldo = `days_granted` + `days_adjustment` − días disfrutados, donde los días
 * disfrutados son las solicitudes **aprobadas** más las incidencias de tipo
 * `vacation` **aprobadas** que se solapan con el periodo y que no provienen de
 * una solicitud aprobada (si provinieran de ella se contarían dos veces).
 *
 * El saldo solo puede quedar negativo si el ajuste autorizado del periodo lo
 * provoca; en ese caso se expone `overdrawn = true` y no se permiten consumos
 * adicionales.
 */
final class VacationBalanceService
{
    /**
     * Saldo de un periodo individual.
     *
     * @return array{
     *     period_id: int,
     *     employee_id: int,
     *     days_granted: string,
     *     days_adjustment: string,
     *     days_taken: string,
     *     days_pending: string,
     *     days_available: string,
     *     overdrawn: bool
     * }
     */
    public function forPeriod(VacationPeriod $period): array
    {
        return $this->forPeriods(new Collection([$period]))[$period->id];
    }

    /**
     * Saldo de varios periodos con dos consultas agregadas (sin N+1).
     *
     * @param  Collection<int, VacationPeriod>  $periods
     * @return array<int, array{
     *     period_id: int,
     *     employee_id: int,
     *     days_granted: string,
     *     days_adjustment: string,
     *     days_taken: string,
     *     days_pending: string,
     *     days_available: string,
     *     overdrawn: bool
     * }>
     */
    public function forPeriods(Collection $periods): array
    {
        if ($periods->isEmpty()) {
            return [];
        }

        $periods = $periods->values();
        $employeeIds = $periods->pluck('employee_id')->unique()->values()->all();
        $minStart = $periods->min(fn (VacationPeriod $period): string => $period->period_start->toDateString());
        $maxEnd = $periods->max(fn (VacationPeriod $period): string => $period->period_end->toDateString());

        $requests = $this->requestsQuery($employeeIds, (string) $minStart, (string) $maxEnd)
            ->get(['id', 'employee_id', 'start_date', 'end_date', 'days', 'status'])
            ->groupBy('employee_id');

        $incidents = $this->incidentsQuery($employeeIds, (string) $minStart, (string) $maxEnd)
            ->get(['id', 'employee_id', 'start_date', 'end_date', 'quantity'])
            ->groupBy('employee_id');

        $balances = [];

        foreach ($periods as $period) {
            $balances[$period->id] = $this->balance(
                $period,
                $requests->get($period->employee_id, new Collection),
                $incidents->get($period->employee_id, new Collection),
            );
        }

        return $balances;
    }

    /**
     * Totales del colaborador (todos los periodos o los de un año laboral).
     *
     * @return array{
     *     days_granted: string,
     *     days_adjustment: string,
     *     days_taken: string,
     *     days_pending: string,
     *     days_available: string,
     *     overdrawn: bool
     * }
     */
    public function forEmployee(Employee $employee, ?int $year = null): array
    {
        $periods = $employee->vacationPeriods()
            ->when($year !== null, fn (Builder $query) => $query->whereYear('period_start', $year))
            ->orderBy('period_start')
            ->get();

        $balances = $this->forPeriods($periods);

        $granted = 0.0;
        $adjustment = 0.0;
        $taken = 0.0;
        $pending = 0.0;

        foreach ($balances as $balance) {
            $granted += (float) $balance['days_granted'];
            $adjustment += (float) $balance['days_adjustment'];
            $taken += (float) $balance['days_taken'];
            $pending += (float) $balance['days_pending'];
        }

        $available = $granted + $adjustment - $taken;

        return [
            'days_granted' => self::format($granted),
            'days_adjustment' => self::format($adjustment),
            'days_taken' => self::format($taken),
            'days_pending' => self::format($pending),
            'days_available' => self::format($available),
            'overdrawn' => $available < 0,
        ];
    }

    /**
     * Valida que el periodo tenga saldo suficiente para consumir los días.
     *
     * @throws ValidationException
     */
    public function assertCanConsume(VacationPeriod $period, float $days): void
    {
        if ($days <= 0) {
            throw ValidationException::withMessages([
                'start_date' => 'El rango de fechas debe cubrir al menos un día.',
            ]);
        }

        $balance = $this->forPeriod($period);
        $available = (float) $balance['days_available'];

        if ($days > $available) {
            throw ValidationException::withMessages([
                'start_date' => "Saldo insuficiente: el periodo del {$period->period_start->format('d/m/Y')} al "
                    ."{$period->period_end->format('d/m/Y')} tiene {$balance['days_available']} días disponibles y se "
                    .'solicitaron '.self::format($days).' días.',
            ]);
        }
    }

    /**
     * Formato decimal homogéneo de días (2 decimales, como string).
     */
    public static function format(float|int|string $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    /**
     * @param  Collection<int, VacationRequest>  $requests
     * @param  Collection<int, AttendanceIncident>  $incidents
     * @return array{
     *     period_id: int,
     *     employee_id: int,
     *     days_granted: string,
     *     days_adjustment: string,
     *     days_taken: string,
     *     days_pending: string,
     *     days_available: string,
     *     overdrawn: bool
     * }
     */
    private function balance(VacationPeriod $period, Collection $requests, Collection $incidents): array
    {
        $periodRequests = $requests->filter(
            fn (VacationRequest $request): bool => $this->overlaps(
                $request->start_date->toDateString(),
                $request->end_date->toDateString(),
                $period,
            ),
        );

        $approvedRequests = $periodRequests->filter(
            fn (VacationRequest $request): bool => $request->status === VacationRequestStatus::Approved,
        );

        $pendingRequests = $periodRequests->filter(
            fn (VacationRequest $request): bool => $request->status === VacationRequestStatus::Pending,
        );

        $takenFromRequests = $approvedRequests->sum(fn (VacationRequest $request): float => (float) $request->days);

        $takenFromIncidents = $incidents
            ->filter(fn (AttendanceIncident $incident): bool => $this->overlaps(
                $incident->start_date->toDateString(),
                $incident->end_date->toDateString(),
                $period,
            ) && ! $this->hasApprovedRequest($approvedRequests, $incident))
            ->sum(fn (AttendanceIncident $incident): float => (float) $incident->quantity);

        $granted = (float) $period->days_granted;
        $adjustment = (float) $period->days_adjustment;
        $taken = $takenFromRequests + $takenFromIncidents;
        $pending = $pendingRequests->sum(fn (VacationRequest $request): float => (float) $request->days);
        $available = $granted + $adjustment - $taken;

        return [
            'period_id' => $period->id,
            'employee_id' => $period->employee_id,
            'days_granted' => self::format($granted),
            'days_adjustment' => self::format($adjustment),
            'days_taken' => self::format($taken),
            'days_pending' => self::format($pending),
            'days_available' => self::format($available),
            'overdrawn' => $available < 0,
        ];
    }

    /**
     * @param  Collection<int, VacationRequest>  $approvedRequests
     */
    private function hasApprovedRequest(Collection $approvedRequests, AttendanceIncident $incident): bool
    {
        return $approvedRequests->contains(
            fn (VacationRequest $request): bool => $request->start_date->toDateString() === $incident->start_date->toDateString()
                && $request->end_date->toDateString() === $incident->end_date->toDateString(),
        );
    }

    private function overlaps(string $start, string $end, VacationPeriod $period): bool
    {
        return $start <= $period->period_end->toDateString()
            && $end >= $period->period_start->toDateString();
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return Builder<VacationRequest>
     */
    private function requestsQuery(array $employeeIds, string $from, string $to): Builder
    {
        return VacationRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', [VacationRequestStatus::Approved->value, VacationRequestStatus::Pending->value])
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from);
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return Builder<AttendanceIncident>
     */
    private function incidentsQuery(array $employeeIds, string $from, string $to): Builder
    {
        return AttendanceIncident::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('type', IncidentType::Vacation->value)
            ->where('status', IncidentStatus::Approved->value)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from);
    }
}
