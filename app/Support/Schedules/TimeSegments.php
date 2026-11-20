<?php

namespace App\Support\Schedules;

/**
 * Utilidades de los segmentos de horario (`work`/`break`).
 *
 * Un segmento con `end_time <= start_time` cruza la medianoche (D-09); para
 * comparar solapamientos se proyecta sobre una línea de minutos de 48 h.
 */
final class TimeSegments
{
    /**
     * Valida un conjunto de segmentos de un mismo día.
     *
     * @param  array<int|string, mixed>  $segments
     * @return string|null Mensaje de error en español o `null` si son válidos.
     */
    public static function validationError(array $segments): ?string
    {
        $intervals = [];

        foreach (array_values($segments) as $index => $segment) {
            if (! is_array($segment)) {
                continue;
            }

            $start = self::toMinutes($segment['start_time'] ?? null);
            $end = self::toMinutes($segment['end_time'] ?? null);

            if ($start === null || $end === null) {
                continue;
            }

            if ($start === $end) {
                return "El segmento {$index} tiene la misma hora de inicio y de fin.";
            }

            if ($end < $start) {
                $end += 1440;
            }

            $intervals[] = [
                'index' => $index,
                'start' => $start,
                'end' => $end,
            ];
        }

        usort($intervals, fn (array $left, array $right): int => $left['start'] <=> $right['start']);

        $previous = null;

        foreach ($intervals as $interval) {
            if ($previous !== null && $interval['start'] < $previous['end']) {
                return sprintf(
                    'Los segmentos %d y %d se solapan en el mismo día.',
                    $previous['index'] + 1,
                    $interval['index'] + 1,
                );
            }

            $previous = $interval;
        }

        return null;
    }

    /**
     * Convierte `H:i` o `H:i:s` a minutos desde la medianoche.
     */
    public static function toMinutes(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $value, $matches) !== 1) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($hours > 23 || $minutes > 59) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }

    /**
     * Normaliza una hora de BD (`09:00:00`) al formato del contrato (`09:00`).
     */
    public static function toHhMm(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr($value, 0, 5);
    }
}
