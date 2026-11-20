<?php

namespace App\Services\Holidays;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Reglas versionadas de los días de descanso obligatorio (LFT art. 74).
 *
 * Decisión D-10: la tabla se declara en código para que cada año se pueda
 * regenerar de forma determinista y auditable. Los campos `source_url` y
 * `source_checked_at` viajan con cada regla; `source_checked_at` se mantiene en
 * `null` porque la captura se hizo sin acceso a la fuente oficial, por lo que
 * toda la tabla queda **pendiente de validación legal** (ver
 * {@see self::validationNotice()}).
 *
 * Reglas capturadas:
 * - 1 de enero (fracción I).
 * - Primer lunes de febrero, en conmemoración del 5 de febrero (fracción II).
 * - Tercer lunes de marzo, en conmemoración del 21 de marzo (fracción III).
 * - 1 de mayo (fracción IV).
 * - 16 de septiembre (fracción V).
 * - Tercer lunes de noviembre, en conmemoración del 20 de noviembre (fracción VI).
 * - 1 de octubre cada seis años a partir de 2024, transmisión del Poder
 *   Ejecutivo Federal (fracción VII tras la reforma de 2024; antes 1 de diciembre).
 * - Jornada electoral federal en años de elección federal ordinaria
 *   (fracción IX); la fecha es configurable en `ELECTION_DAY_OVERRIDES` y, si
 *   el año no está configurado, se calcula como el primer domingo de junio.
 * - 25 de diciembre (fracción VIII).
 */
final class LegalHolidayRules
{
    /**
     * Fuente oficial de la Ley Federal del Trabajo.
     */
    public const SOURCE_URL = 'https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf';

    /**
     * Aviso que la UI muestra junto al calendario de festivos.
     */
    public const VALIDATION_NOTICE = 'Días de descanso obligatorio capturados con base en el artículo 74 de la Ley Federal del Trabajo (texto con la reforma de 2024, que traslada la transmisión del Poder Ejecutivo Federal al 1 de octubre). Esta tabla se capturó sin acceso a la fuente oficial y queda PENDIENTE DE VALIDACIÓN en diputados.gob.mx antes de usarse en producción; la fecha de la jornada electoral federal debe confirmarse con el calendario del INE.';

    /**
     * Fecha de revisión en la fuente oficial; `null` = pendiente de validación.
     */
    public const SOURCE_CHECKED_AT = null;

    /**
     * Primer año en que aplica la transmisión del Poder Ejecutivo el 1 de octubre.
     */
    private const POWER_TRANSFER_START_YEAR = 2024;

    /**
     * Periodicidad sexenal de la transmisión del Poder Ejecutivo Federal.
     */
    private const POWER_TRANSFER_CYCLE_YEARS = 6;

    /**
     * Primer año de elección federal ordinaria con jornada electoral como
     * descanso obligatorio dentro del horizonte de este sistema.
     */
    private const ELECTION_CYCLE_START_YEAR = 2027;

    /**
     * Periodicidad de las elecciones federales ordinarias.
     */
    private const ELECTION_CYCLE_YEARS = 3;

    /**
     * Mes en que se celebra la jornada electoral federal.
     */
    private const ELECTION_MONTH = 6;

    /**
     * Fechas de jornada electoral confirmadas por calendario electoral.
     * Cualquier otro año se calcula como el primer domingo de junio.
     *
     * @var array<int, string>
     */
    private const ELECTION_DAY_OVERRIDES = [
        2027 => '2027-06-06',
        2030 => '2030-06-02',
    ];

    /**
     * Festivos de fecha fija.
     *
     * @var array<int, array{key: string, name: string, month: int, day: int, legal_basis: string}>
     */
    private const FIXED_DATE_RULES = [
        ['key' => 'new_year', 'name' => 'Año Nuevo', 'month' => 1, 'day' => 1, 'legal_basis' => 'LFT art. 74, fracción I'],
        ['key' => 'labor_day', 'name' => 'Día del Trabajo', 'month' => 5, 'day' => 1, 'legal_basis' => 'LFT art. 74, fracción IV'],
        ['key' => 'independence_day', 'name' => 'Día de la Independencia', 'month' => 9, 'day' => 16, 'legal_basis' => 'LFT art. 74, fracción V'],
        ['key' => 'christmas', 'name' => 'Navidad', 'month' => 12, 'day' => 25, 'legal_basis' => 'LFT art. 74, fracción VIII'],
    ];

    /**
     * Festivos que se trasladan al lunes indicado del mes.
     *
     * @var array<int, array{key: string, name: string, month: int, weekday: int, occurrence: int, legal_basis: string}>
     */
    private const MOVABLE_RULES = [
        ['key' => 'constitution_day', 'name' => 'Día de la Constitución', 'month' => 2, 'weekday' => CarbonInterface::MONDAY, 'occurrence' => 1, 'legal_basis' => 'LFT art. 74, fracción II'],
        ['key' => 'benito_juarez_birthday', 'name' => 'Natalicio de Benito Juárez', 'month' => 3, 'weekday' => CarbonInterface::MONDAY, 'occurrence' => 3, 'legal_basis' => 'LFT art. 74, fracción III'],
        ['key' => 'revolution_day', 'name' => 'Revolución Mexicana', 'month' => 11, 'weekday' => CarbonInterface::MONDAY, 'occurrence' => 3, 'legal_basis' => 'LFT art. 74, fracción VI'],
    ];

    public static function sourceUrl(): string
    {
        return self::SOURCE_URL;
    }

    public static function validationNotice(): string
    {
        return self::VALIDATION_NOTICE;
    }

    /**
     * Festivos obligatorios de un año, ordenados por fecha y sin duplicados.
     *
     * @return array<int, array{
     *     key: string,
     *     name: string,
     *     date: CarbonImmutable,
     *     legal_basis: string,
     *     source_url: string,
     *     source_checked_at: CarbonImmutable|null
     * }>
     */
    public static function forYear(int $year): array
    {
        $holidays = [];

        foreach (self::FIXED_DATE_RULES as $rule) {
            $holidays[] = self::rule(
                $rule['key'],
                $rule['name'],
                CarbonImmutable::create($year, $rule['month'], $rule['day']),
                $rule['legal_basis'],
            );
        }

        foreach (self::MOVABLE_RULES as $rule) {
            $holidays[] = self::rule(
                $rule['key'],
                $rule['name'],
                CarbonImmutable::create($year, $rule['month'], 1)->nthOfMonth($rule['occurrence'], $rule['weekday']),
                $rule['legal_basis'],
            );
        }

        $powerTransfer = self::powerTransferDay($year);

        if ($powerTransfer instanceof CarbonImmutable) {
            $holidays[] = self::rule(
                'power_transfer',
                'Transmisión del Poder Ejecutivo Federal',
                $powerTransfer,
                'LFT art. 74, fracción VII',
            );
        }

        $electionDay = self::electionDay($year);

        if ($electionDay instanceof CarbonImmutable) {
            $holidays[] = self::rule(
                'election_day',
                'Jornada electoral federal',
                $electionDay,
                'LFT art. 74, fracción IX',
            );
        }

        usort(
            $holidays,
            fn (array $left, array $right): int => $left['date']->getTimestamp() <=> $right['date']->getTimestamp(),
        );

        return $holidays;
    }

    /**
     * ¿El año es de elección federal ordinaria con jornada electoral?
     */
    public static function isElectionYear(int $year): bool
    {
        return $year >= self::ELECTION_CYCLE_START_YEAR
            && ($year - self::ELECTION_CYCLE_START_YEAR) % self::ELECTION_CYCLE_YEARS === 0;
    }

    /**
     * ¿El año corresponde a la transmisión del Poder Ejecutivo Federal?
     */
    public static function isPowerTransferYear(int $year): bool
    {
        return $year >= self::POWER_TRANSFER_START_YEAR
            && ($year - self::POWER_TRANSFER_START_YEAR) % self::POWER_TRANSFER_CYCLE_YEARS === 0;
    }

    /**
     * Fecha de la jornada electoral federal, o `null` si no aplica.
     */
    public static function electionDay(int $year): ?CarbonImmutable
    {
        if (! self::isElectionYear($year)) {
            return null;
        }

        if (isset(self::ELECTION_DAY_OVERRIDES[$year])) {
            return CarbonImmutable::parse(self::ELECTION_DAY_OVERRIDES[$year])->startOfDay();
        }

        $firstDayOfMonth = CarbonImmutable::create($year, self::ELECTION_MONTH, 1);

        return $firstDayOfMonth->isSunday()
            ? $firstDayOfMonth
            : $firstDayOfMonth->next(CarbonInterface::SUNDAY);
    }

    /**
     * 1 de octubre de los años de transmisión del Poder Ejecutivo Federal.
     */
    public static function powerTransferDay(int $year): ?CarbonImmutable
    {
        if (! self::isPowerTransferYear($year)) {
            return null;
        }

        return CarbonImmutable::create($year, 10, 1);
    }

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     date: CarbonImmutable,
     *     legal_basis: string,
     *     source_url: string,
     *     source_checked_at: CarbonImmutable|null
     * }
     */
    private static function rule(string $key, string $name, CarbonImmutable $date, string $legalBasis): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'date' => $date->startOfDay(),
            'legal_basis' => $legalBasis,
            'source_url' => self::SOURCE_URL,
            'source_checked_at' => self::SOURCE_CHECKED_AT,
        ];
    }
}
