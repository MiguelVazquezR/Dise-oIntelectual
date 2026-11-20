<?php

namespace App\Services\Payroll;

use App\Enums\PayrollScheduleFrequency;
use App\Models\PayrollPeriod;
use App\Models\PayrollSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Genera periodos de pre-nómina a partir de un esquema de pago.
 *
 * Reglas (S-04 y §2):
 * - `weekly`: el periodo inicia el `start_weekday` del esquema y termina el
 *   `cutoff_weekday` (la semana siguiente si ambos coinciden).
 * - `biweekly`: día 1 al 15 y día 16 al fin de mes; `start_weekday` y
 *   `cutoff_weekday` quedan informativos.
 * - `monthly`: mes natural completo.
 * - `payment_date` = fin del periodo + `payment_offset_days`.
 *
 * Idempotencia: la clave natural es (`payroll_schedule_id`, `start_date`), la
 * misma restricción única de la tabla. Un periodo existente no se modifica.
 */
final class PayrollPeriodGenerator
{
    /** Tope de periodos generados por llamada para evitar rangos accidentales. */
    private const MAX_PERIODS = 130;

    /**
     * @return array{created: int, existing: int, periods: Collection<int, PayrollPeriod>}
     */
    public function generate(PayrollSchedule $schedule, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cursor = $from->startOfDay();
        $limit = $to->startOfDay();
        $created = 0;
        $existing = 0;

        /** @var Collection<int, PayrollPeriod> $periods */
        $periods = collect();

        if ($limit->lessThan($cursor)) {
            return ['created' => 0, 'existing' => 0, 'periods' => $periods];
        }

        $iterations = 0;

        while ($cursor->lessThanOrEqualTo($limit) && $iterations < self::MAX_PERIODS) {
            $iterations++;
            [$start, $end] = $this->windowFor($schedule, $cursor);
            $paymentDate = $end->addDays(max(0, $schedule->payment_offset_days));

            $period = DB::transaction(fn (): array => $this->firstOrCreatePeriod($schedule, $start, $end, $paymentDate));

            if ($period['created']) {
                $created++;
            } else {
                $existing++;
            }

            $periods->push($period['period']);
            $cursor = $end->addDay();
        }

        return ['created' => $created, 'existing' => $existing, 'periods' => $periods];
    }

    /**
     * Genera los periodos necesarios para cubrir un mes completo.
     *
     * @return array{created: int, existing: int, periods: Collection<int, PayrollPeriod>}
     */
    public function generateForMonth(PayrollSchedule $schedule, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'America/Mexico_City');

        return $this->generate($schedule, $start, $start->endOfMonth()->startOfDay());
    }

    /**
     * Ventana [inicio, fin] del periodo que contiene o sigue a `cursor`.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function windowFor(PayrollSchedule $schedule, CarbonImmutable $cursor): array
    {
        return match ($schedule->frequency) {
            PayrollScheduleFrequency::Weekly => $this->weeklyWindow($schedule, $cursor),
            PayrollScheduleFrequency::Biweekly => $this->biweeklyWindow($cursor),
            PayrollScheduleFrequency::Monthly => [
                $cursor->startOfMonth(),
                $cursor->endOfMonth()->startOfDay(),
            ],
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function weeklyWindow(PayrollSchedule $schedule, CarbonImmutable $cursor): array
    {
        $startWeekday = $schedule->start_weekday->value;
        $cutoffWeekday = $schedule->cutoff_weekday->value;

        $offset = ($startWeekday - $cursor->dayOfWeekIso + 7) % 7;
        $start = $cursor->addDays($offset)->startOfDay();

        $length = (($cutoffWeekday - $startWeekday + 7) % 7) ?: 7;
        $end = $start->addDays($length)->startOfDay();

        return [$start, $end];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function biweeklyWindow(CarbonImmutable $cursor): array
    {
        if ($cursor->day <= 15) {
            return [
                $cursor->startOfMonth(),
                $cursor->startOfMonth()->addDays(14),
            ];
        }

        return [
            $cursor->startOfMonth()->addDays(15),
            $cursor->endOfMonth()->startOfDay(),
        ];
    }

    /**
     * @return array{created: bool, period: PayrollPeriod}
     */
    private function firstOrCreatePeriod(
        PayrollSchedule $schedule,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $paymentDate,
    ): array {
        $existing = PayrollPeriod::query()
            ->where('payroll_schedule_id', $schedule->id)
            ->whereDate('start_date', $start->toDateString())
            ->first();

        if ($existing instanceof PayrollPeriod) {
            return ['created' => false, 'period' => $existing];
        }

        $period = PayrollPeriod::query()->create([
            'payroll_schedule_id' => $schedule->id,
            'start_date' => $start,
            'end_date' => $end,
            'payment_date' => $paymentDate,
        ]);

        return ['created' => true, 'period' => $period];
    }
}
