<?php

namespace App\Services\Holidays;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Generación idempotente del calendario de festivos obligatorios por año
 * (D-10).
 *
 * Reglas de idempotencia:
 * - Se buscan los festivos obligatorios globales (`branch_id` nulo) por fecha,
 *   de modo que ejecutar dos veces no duplica filas.
 * - Nunca se sobrescriben datos capturados manualmente: solo se completan
 *   `legal_basis`/`source_url` cuando están vacíos y se respeta el nombre que
 *   el coordinador haya corregido.
 * - Los festivos `company` (globales o por sucursal) no se tocan.
 * - Un año cerrado no se modifica salvo autorización explícita.
 */
final class HolidayGenerator
{
    public function __construct(private readonly HolidayLockGuard $lockGuard) {}

    /**
     * @return array{
     *     year: int,
     *     locked: bool,
     *     created: int,
     *     refreshed: int,
     *     skipped: int,
     *     total: int,
     *     holidays: array<int, array<string, mixed>>
     * }
     */
    public function forYear(int $year, bool $allowClosedYear = false, ?int $createdBy = null): array
    {
        if (! $allowClosedYear && $this->lockGuard->isLocked($year)) {
            return [
                'year' => $year,
                'locked' => true,
                'created' => 0,
                'refreshed' => 0,
                'skipped' => 0,
                'total' => 0,
                'holidays' => [],
            ];
        }

        $rules = LegalHolidayRules::forYear($year);

        return DB::transaction(function () use ($year, $rules, $createdBy): array {
            $created = 0;
            $refreshed = 0;
            $skipped = 0;
            $holidays = [];

            foreach ($rules as $rule) {
                /** @var CarbonImmutable $date */
                $date = $rule['date'];

                $existing = Holiday::query()
                    ->whereDate('date', $date->toDateString())
                    ->whereNull('branch_id')
                    ->where('type', HolidayType::Mandatory->value)
                    ->first();

                if ($existing instanceof Holiday) {
                    $missing = [];

                    if ($existing->legal_basis === null || $existing->legal_basis === '') {
                        $missing['legal_basis'] = $rule['legal_basis'];
                    }

                    if ($existing->source_url === null || $existing->source_url === '') {
                        $missing['source_url'] = $rule['source_url'];
                    }

                    if ($missing !== []) {
                        $existing->update($missing);
                        $refreshed++;
                    } else {
                        $skipped++;
                    }

                    $existing->refresh();
                    $holidays[] = $this->row($existing);

                    continue;
                }

                $holiday = Holiday::query()->create([
                    'name' => $rule['name'],
                    'date' => $date->toDateString(),
                    'type' => HolidayType::Mandatory->value,
                    'branch_id' => null,
                    'legal_basis' => $rule['legal_basis'],
                    'source_url' => $rule['source_url'],
                    'source_checked_at' => null,
                    'created_by' => $createdBy,
                ]);

                $created++;
                $holidays[] = $this->row($holiday);
            }

            return [
                'year' => $year,
                'locked' => false,
                'created' => $created,
                'refreshed' => $refreshed,
                'skipped' => $skipped,
                'total' => count($rules),
                'holidays' => $holidays,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Holiday $holiday): array
    {
        return [
            'id' => $holiday->id,
            'name' => $holiday->name,
            'date' => $holiday->date->toDateString(),
            'type' => $holiday->type->value,
            'branch_id' => $holiday->branch_id,
            'legal_basis' => $holiday->legal_basis,
            'source_url' => $holiday->source_url,
            'source_checked_at' => $holiday->source_checked_at?->toIso8601String(),
        ];
    }
}
