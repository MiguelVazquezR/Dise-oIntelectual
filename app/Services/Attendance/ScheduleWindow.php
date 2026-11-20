<?php

namespace App\Services\Attendance;

use Carbon\CarbonImmutable;

/**
 * Ventana de trabajo resuelta para una fecha laborable (`work_date`).
 *
 * Los segmentos se conservan como instantes absolutos en la zona horaria de la
 * sucursal. Un segmento con `end_time <= start_time` se proyecta al día
 * siguiente (D-09), por lo que un turno nocturno 22:00–06:00 queda como
 * `[work_date 22:00, work_date + 1 día 06:00]`.
 */
final class ScheduleWindow
{
    /**
     * @param  array<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $workSegments
     * @param  array<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $breakSegments
     */
    public function __construct(
        public readonly string $timezone,
        public readonly CarbonImmutable $workDate,
        public readonly ?int $weekday,
        public readonly bool $isRestDay,
        public readonly int $lateToleranceMinutes,
        public readonly array $workSegments,
        public readonly array $breakSegments,
        public readonly ?int $scheduleId = null,
        public readonly ?string $scheduleName = null,
    ) {}

    public function hasWork(): bool
    {
        return $this->workSegments !== [];
    }

    public function isWorkingDay(): bool
    {
        return ! $this->isRestDay && $this->hasWork();
    }

    public function scheduledStart(): ?CarbonImmutable
    {
        if ($this->workSegments === []) {
            return null;
        }

        $segments = $this->sortedWorkSegments();

        return $segments[0]['start'];
    }

    public function scheduledEnd(): ?CarbonImmutable
    {
        if ($this->workSegments === []) {
            return null;
        }

        $end = null;

        foreach ($this->workSegments as $segment) {
            if ($end === null || $segment['end']->greaterThan($end)) {
                $end = $segment['end'];
            }
        }

        return $end;
    }

    /**
     * Minutos programados: suma de la duración de los segmentos `work`.
     */
    public function scheduledMinutes(): int
    {
        $minutes = 0;

        foreach ($this->workSegments as $segment) {
            $minutes += self::minutesBetween($segment['start'], $segment['end']);
        }

        return $minutes;
    }

    /**
     * Descanso programado: suma de la duración de los segmentos `break`.
     */
    public function scheduledBreakMinutes(): int
    {
        $minutes = 0;

        foreach ($this->breakSegments as $segment) {
            $minutes += self::minutesBetween($segment['start'], $segment['end']);
        }

        return $minutes;
    }

    /**
     * Indica si el turno inicia un día y termina al siguiente.
     */
    public function crossesMidnight(): bool
    {
        $start = $this->scheduledStart();
        $end = $this->scheduledEnd();

        return $start !== null
            && $end !== null
            && $end->toDateString() !== $start->toDateString();
    }

    /**
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function sortedWorkSegments(): array
    {
        $segments = $this->workSegments;

        usort($segments, fn (array $left, array $right): int => $left['start'] <=> $right['start']);

        return $segments;
    }

    private static function minutesBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return max(0, (int) round($start->diffInMinutes($end)));
    }
}
