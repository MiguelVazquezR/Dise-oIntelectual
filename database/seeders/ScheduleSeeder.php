<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchSchedule;
use App\Support\Schedules\ScheduleTree;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Horarios vigentes de demostración, uno por sucursal.
 *
 * - Matriz Centro: turno partido con descanso intermedio.
 * - Sucursal Norte: turno nocturno 22:00–06:00 (cruza medianoche, D-09).
 * - Sucursal Sur: turno continuo.
 *
 * Idempotente: la clave es (branch_id, name, effective_from); el árbol de días
 * y segmentos se reemplaza en cada corrida para reflejar la definición demo.
 *
 * @phpstan-type DayDefinition array{
 *     weekday: int,
 *     is_rest_day: bool,
 *     late_tolerance_minutes: int,
 *     notes: string|null,
 *     segments: array<int, array{start_time: string, end_time: string, kind: string, sort_order: int}>
 * }
 */
class ScheduleSeeder extends Seeder
{
    private const EFFECTIVE_FROM = '2026-01-01';

    public function run(): void
    {
        $branches = Branch::query()
            ->whereIn('code', ['MATRIZ', 'NORTE', 'SUR'])
            ->get()
            ->keyBy('code');

        foreach ($this->definitions() as $code => $definition) {
            $branch = $branches->get($code);

            if ($branch === null) {
                continue;
            }

            DB::transaction(function () use ($branch, $definition): void {
                $schedule = BranchSchedule::query()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'name' => $definition['name'],
                        'effective_from' => CarbonImmutable::parse(self::EFFECTIVE_FROM)->startOfDay(),
                    ],
                    [
                        'effective_to' => null,
                        'is_active' => true,
                    ],
                );

                ScheduleTree::replace($schedule, $definition['days']);
            });
        }

        $this->command?->info('Horarios de demostración listos (incluye turno nocturno y turno partido).');
    }

    /**
     * @return array<string, array{name: string, days: array<int, DayDefinition>}>
     */
    private function definitions(): array
    {
        return [
            'MATRIZ' => [
                'name' => 'Horario general',
                'days' => [
                    $this->day(1, [['09:00', '14:00', 'work'], ['14:00', '15:00', 'break'], ['15:00', '19:00', 'work']], 10),
                    $this->day(2, [['09:00', '14:00', 'work'], ['14:00', '15:00', 'break'], ['15:00', '19:00', 'work']], 10),
                    $this->day(3, [['09:00', '14:00', 'work'], ['14:00', '15:00', 'break'], ['15:00', '19:00', 'work']], 10),
                    $this->day(4, [['09:00', '14:00', 'work'], ['14:00', '15:00', 'break'], ['15:00', '19:00', 'work']], 10),
                    $this->day(5, [['09:00', '14:00', 'work'], ['14:00', '15:00', 'break'], ['15:00', '19:00', 'work']], 10),
                    $this->day(6, [['09:00', '13:00', 'work']], 0, 'Sábado medio día'),
                    $this->day(7, []),
                ],
            ],
            'NORTE' => [
                'name' => 'Horario nocturno',
                'days' => [
                    $this->day(1, [['22:00', '06:00', 'work']], 15, 'Turno nocturno: cruza medianoche'),
                    $this->day(2, [['22:00', '06:00', 'work']], 15, 'Turno nocturno: cruza medianoche'),
                    $this->day(3, [['22:00', '06:00', 'work']], 15, 'Turno nocturno: cruza medianoche'),
                    $this->day(4, [['22:00', '06:00', 'work']], 15, 'Turno nocturno: cruza medianoche'),
                    $this->day(5, [['22:00', '06:00', 'work']], 15, 'Turno nocturno: cruza medianoche'),
                    $this->day(6, [['22:00', '06:00', 'work']], 15, 'Turno nocturno: cruza medianoche'),
                    $this->day(7, []),
                ],
            ],
            'SUR' => [
                'name' => 'Horario general',
                'days' => [
                    $this->day(1, [['08:00', '16:00', 'work']], 5),
                    $this->day(2, [['08:00', '16:00', 'work']], 5),
                    $this->day(3, [['08:00', '16:00', 'work']], 5),
                    $this->day(4, [['08:00', '16:00', 'work']], 5),
                    $this->day(5, [['08:00', '16:00', 'work']], 5),
                    $this->day(6, [['08:00', '12:00', 'work']], 0, 'Sábado medio día'),
                    $this->day(7, []),
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $segments
     * @return DayDefinition
     */
    private function day(int $weekday, array $segments, int $tolerance = 10, ?string $notes = null): array
    {
        $isRestDay = $segments === [];

        return [
            'weekday' => $weekday,
            'is_rest_day' => $isRestDay,
            'late_tolerance_minutes' => $isRestDay ? 0 : $tolerance,
            'notes' => $notes,
            'segments' => array_map(
                fn (array $segment, int $index): array => [
                    'start_time' => $segment[0],
                    'end_time' => $segment[1],
                    'kind' => $segment[2],
                    'sort_order' => $index,
                ],
                $segments,
                array_keys($segments),
            ),
        ];
    }
}
