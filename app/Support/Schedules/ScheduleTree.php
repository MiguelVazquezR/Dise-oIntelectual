<?php

namespace App\Support\Schedules;

use App\Models\BranchSchedule;
use App\Models\BranchScheduleDay;
use App\Models\BranchScheduleSegment;
use Illuminate\Support\Facades\DB;

/**
 * Persiste el árbol de días y segmentos de un horario.
 *
 * La vista de edición envía siempre los 7 días; se reemplaza el árbol completo
 * dentro de una transacción para evitar duplicados y estados intermedios.
 */
final class ScheduleTree
{
    /**
     * @param  array<int, array{
     *     weekday: int,
     *     is_rest_day?: bool,
     *     late_tolerance_minutes?: int,
     *     notes?: string|null,
     *     segments?: array<int, array{start_time: string, end_time: string, kind: string, sort_order?: int}>
     * }>  $days
     */
    public static function replace(BranchSchedule $schedule, array $days): void
    {
        DB::transaction(function () use ($schedule, $days): void {
            self::purge($schedule);

            foreach ($days as $day) {
                $record = $schedule->days()->create([
                    'weekday' => (int) $day['weekday'],
                    'is_rest_day' => (bool) ($day['is_rest_day'] ?? false),
                    'late_tolerance_minutes' => (int) ($day['late_tolerance_minutes'] ?? 10),
                    'notes' => $day['notes'] ?? null,
                ]);

                self::createSegments($record, $day['segments'] ?? []);
            }
        });
    }

    /**
     * Elimina los días y segmentos del horario (sin borrar el horario).
     */
    public static function purge(BranchSchedule $schedule): void
    {
        BranchScheduleSegment::query()
            ->whereIn('branch_schedule_day_id', $schedule->days()->select('id'))
            ->delete();

        $schedule->days()->delete();
    }

    /**
     * @param  array<int, array{start_time: string, end_time: string, kind: string, sort_order?: int}>  $segments
     */
    private static function createSegments(BranchScheduleDay $day, array $segments): void
    {
        foreach (array_values($segments) as $index => $segment) {
            $day->segments()->create([
                'start_time' => $segment['start_time'],
                'end_time' => $segment['end_time'],
                'kind' => $segment['kind'],
                'sort_order' => (int) ($segment['sort_order'] ?? $index),
            ]);
        }
    }
}
