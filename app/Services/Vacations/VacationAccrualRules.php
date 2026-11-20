<?php

namespace App\Services\Vacations;

use Carbon\CarbonImmutable;

/**
 * Tabla versionada de días de vacaciones por antigüedad (LFT art. 76, D-11).
 *
 * Artículo 76: después del primer año de servicio el colaborador tiene derecho
 * a 12 días de vacaciones; a partir del sexto año el periodo aumenta dos días
 * por cada cinco años de servicio (fracción III). La tabla reproduce ambos
 * tramos, por lo que los años por encima de 35 se calculan con la misma regla.
 *
 * La captura se hizo sin acceso a la fuente oficial: la tabla queda
 * **pendiente de validación legal** (`source_checked_at` = null).
 */
final class VacationAccrualRules
{
    /**
     * Fuente oficial de la Ley Federal del Trabajo.
     */
    public const SOURCE_URL = 'https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf';

    /**
     * Base legal de la tabla.
     */
    public const LEGAL_BASIS = 'LFT art. 76';

    /**
     * Aviso de validación pendiente que se muestra en la UI.
     */
    public const VALIDATION_NOTICE = 'Días de vacaciones por antigüedad capturados con base en el artículo 76 de la Ley Federal del Trabajo (reforma de 2023 que otorga 12 días el primer año). Esta tabla se capturó sin acceso a la fuente oficial y queda PENDIENTE DE VALIDACIÓN en diputados.gob.mx antes de usarse en producción; el ajuste autorizado de días se registra aparte en cada periodo.';

    /**
     * Fecha de revisión en la fuente oficial; `null` = pendiente de validación.
     */
    public const SOURCE_CHECKED_AT = null;

    /**
     * Tramos legales: [años de servicio mínimo, años máximo, días].
     *
     * @var array<int, array{0: int, 1: int, 2: int}>
     */
    private const BANDS = [
        [1, 1, 12],
        [2, 2, 14],
        [3, 3, 16],
        [4, 4, 18],
        [5, 5, 20],
        [6, 10, 22],
        [11, 15, 24],
        [16, 20, 26],
        [21, 25, 28],
        [26, 30, 30],
        [31, 35, 32],
    ];

    /**
     * Días otorgados al cumplir los años de servicio indicados.
     *
     * Sin año cumplido (`0`) no hay derecho devengado todavía.
     */
    public static function daysForYears(int $completedYears): int
    {
        if ($completedYears < 1) {
            return 0;
        }

        foreach (self::BANDS as [$from, $to, $days]) {
            if ($completedYears >= $from && $completedYears <= $to) {
                return $days;
            }
        }

        return self::daysBeyondThirtyFive($completedYears);
    }

    /**
     * Días del tramo de antigüedad de un colaborador en una fecha de corte.
     */
    public static function daysForHireDate(CarbonImmutable $hireDate, CarbonImmutable $onDate): int
    {
        return self::daysForYears(self::completedYears($hireDate, $onDate));
    }

    /**
     * Años de servicio cumplidos (aniversario ya alcanzado).
     */
    public static function completedYears(CarbonImmutable $hireDate, CarbonImmutable $onDate): int
    {
        $hireDate = $hireDate->startOfDay();
        $onDate = $onDate->startOfDay();

        if ($onDate->lessThan($hireDate)) {
            return 0;
        }

        $years = (int) $hireDate->diffInYears($onDate);

        return $years;
    }

    /**
     * Tabla completa para mostrar en la UI o en la documentación.
     *
     * @return array<int, array{years_from: int, years_to: int, years_label: string, days: int, legal_basis: string}>
     */
    public static function table(): array
    {
        return array_map(
            fn (array $band): array => [
                'years_from' => $band[0],
                'years_to' => $band[1],
                'years_label' => self::label($band[0], $band[1]),
                'days' => $band[2],
                'legal_basis' => self::LEGAL_BASIS,
            ],
            self::BANDS,
        );
    }

    public static function sourceUrl(): string
    {
        return self::SOURCE_URL;
    }

    public static function validationNotice(): string
    {
        return self::VALIDATION_NOTICE;
    }

    /**
     * A partir del tramo de 31–35 años la ley suma dos días por cada cinco
     * años adicionales (fracción III del artículo 76).
     */
    private static function daysBeyondThirtyFive(int $completedYears): int
    {
        return 32 + 2 * intdiv($completedYears - 31, 5);
    }

    private static function label(int $from, int $to): string
    {
        if ($from === $to) {
            return $from === 1 ? '1 año' : "{$from} años";
        }

        return "{$from} a {$to} años";
    }
}
