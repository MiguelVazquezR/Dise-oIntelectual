<?php

namespace Database\Seeders;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollScheduleFrequency;
use App\Models\PayrollPeriod;
use App\Models\PayrollSchedule;
use App\Models\User;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PayrollCloser;
use App\Services\Payroll\PayrollPeriodGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Periodos de pre-nómina de demostración del mes en curso (D-12).
 *
 * Genera los periodos del mes para el esquema semanal y el quincenal y calcula
 * los que caen dentro de la quincena que ya tiene marcajes ficticios
 * (`AttendanceEventSeeder::currentPeriod()`):
 *
 * - Quincenal del mes en curso → estado `calculated` (pre-nómina por revisar).
 * - Semanal con cierre dentro de esa quincena → estado `reviewed` (listo para
 *   demostrar el cierre).
 * - El resto de periodos queda `open` para demostrar el cálculo.
 *
 * Idempotente: los periodos se identifican por (`payroll_schedule_id`,
 * `start_date`); solo se calcula cuando el periodo sigue `open` y solo se
 * revisa cuando acaba de calcularse.
 */
class PayrollPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now('America/Mexico_City')->startOfDay();
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth()->startOfDay();

        $weekly = self::scheduleFor(PayrollScheduleFrequency::Weekly);
        $biweekly = self::scheduleFor(PayrollScheduleFrequency::Biweekly);

        if (! $weekly instanceof PayrollSchedule || ! $biweekly instanceof PayrollSchedule) {
            $this->command?->warn('No hay esquemas de pago; ejecuta primero PayrollScheduleSeeder.');

            return;
        }

        /** @var PayrollPeriodGenerator $generator */
        $generator = app(PayrollPeriodGenerator::class);
        $generator->generate($weekly, $monthStart, $monthEnd);
        $generator->generate($biweekly, $monthStart, $monthEnd);

        [$attendanceFrom, $attendanceTo] = AttendanceEventSeeder::currentPeriod();

        $biweeklyPeriod = $this->periodContaining($biweekly, $attendanceFrom);
        $weeklyPeriod = $this->lastWeeklyEndingWithin($weekly, $attendanceFrom, $attendanceTo);

        $actor = self::demoActor();

        if (! $actor instanceof User) {
            $this->command?->warn('No hay usuarios; los periodos se generaron sin calcular.');

            return;
        }

        /** @var PayrollCalculator $calculator */
        $calculator = app(PayrollCalculator::class);
        /** @var PayrollCloser $closer */
        $closer = app(PayrollCloser::class);

        foreach ([$biweeklyPeriod, $weeklyPeriod] as $period) {
            if (! $period instanceof PayrollPeriod || $period->status !== PayrollPeriodStatus::Open) {
                continue;
            }

            try {
                $outcome = $calculator->calculate(
                    period: $period,
                    actor: $actor,
                    note: 'Cálculo de demostración generado por el seeder.',
                );

                $this->command?->info(sprintf(
                    'Periodo %s a %s calculado: %d corrida(s), %d colaborador(es), neto %s.',
                    $period->start_date->toDateString(),
                    $period->end_date->toDateString(),
                    $outcome->runs,
                    $outcome->items,
                    $outcome->totals()['net'],
                ));
            } catch (Throwable $exception) {
                $this->command?->warn('No se pudo calcular el periodo '.$period->id.': '.$exception->getMessage());
            }
        }

        if ($weeklyPeriod instanceof PayrollPeriod && $weeklyPeriod->status === PayrollPeriodStatus::Calculated) {
            try {
                $closer->review($weeklyPeriod, $actor);
                $this->command?->info('Periodo semanal '.$weeklyPeriod->start_date->toDateString().' marcado como revisado (demostración).');
            } catch (Throwable $exception) {
                $this->command?->warn('No se pudo revisar el periodo semanal: '.$exception->getMessage());
            }
        }
    }

    /**
     * Periodo quincenal del mes en curso que ya tiene marcajes ficticios.
     * Lo reutiliza `AdjustmentSeeder` para colgar los ajustes de demostración.
     */
    public static function currentBiweeklyPeriod(): ?PayrollPeriod
    {
        $schedule = self::scheduleFor(PayrollScheduleFrequency::Biweekly);

        if (! $schedule instanceof PayrollSchedule) {
            return null;
        }

        [$attendanceFrom] = AttendanceEventSeeder::currentPeriod();

        return PayrollPeriod::query()
            ->where('payroll_schedule_id', $schedule->id)
            ->whereDate('start_date', '<=', $attendanceFrom->toDateString())
            ->whereDate('end_date', '>=', $attendanceFrom->toDateString())
            ->first();
    }

    public static function scheduleFor(PayrollScheduleFrequency $frequency): ?PayrollSchedule
    {
        return PayrollSchedule::query()
            ->where('frequency', $frequency)
            ->orderBy('id')
            ->first();
    }

    public static function demoActor(): ?User
    {
        $admin = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('slug', User::ADMIN_ROLE))
            ->orderBy('id')
            ->first();

        if ($admin instanceof User) {
            return $admin;
        }

        return User::query()->orderBy('id')->first();
    }

    private function periodContaining(PayrollSchedule $schedule, CarbonImmutable $date): ?PayrollPeriod
    {
        return PayrollPeriod::query()
            ->where('payroll_schedule_id', $schedule->id)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();
    }

    private function lastWeeklyEndingWithin(PayrollSchedule $schedule, CarbonImmutable $from, CarbonImmutable $to): ?PayrollPeriod
    {
        return PayrollPeriod::query()
            ->where('payroll_schedule_id', $schedule->id)
            ->whereDate('start_date', '>=', $from->toDateString())
            ->whereDate('end_date', '<=', $to->toDateString())
            ->orderByDesc('end_date')
            ->first();
    }
}
