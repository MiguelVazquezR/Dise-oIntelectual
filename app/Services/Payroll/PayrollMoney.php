<?php

namespace App\Services\Payroll;

/**
 * Aritmética monetaria de la pre-nómina en centavos enteros.
 *
 * Nunca se acumulan `float`: los importes viajan como enteros de centavos y
 * solo se convierten a cadena decimal (2 posiciones) al persistir o presentar
 * (S-05: redondeo a dos decimales al final del cálculo).
 */
final class PayrollMoney
{
    /**
     * Convierte un importe decimal de la base de datos ("1234.56") a centavos.
     *
     * Acepta enteros, cadenas (con o sin signo) y, por compatibilidad con
     * columnas `decimal` casteadas, flotantes ya materializados por el motor
     * de base de datos. El flotante se formatea de inmediato y no se usa para
     * operar.
     */
    public static function toCents(string|int|float|null $amount): int
    {
        if ($amount === null) {
            return 0;
        }

        if (is_int($amount)) {
            return $amount * 100;
        }

        $value = is_float($amount)
            ? number_format($amount, 2, '.', '')
            : trim($amount);

        if ($value === '') {
            return 0;
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        $parts = explode('.', $value, 2);
        $whole = (int) preg_replace('/\D/', '', $parts[0]);
        $fraction = substr(str_pad((string) preg_replace('/\D/', '', $parts[1] ?? ''), 2, '0'), 0, 2);
        $cents = ($whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    /**
     * Devuelve el importe con dos decimales, listo para una columna decimal(12,2).
     */
    public static function toDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }

    /**
     * División entera con redondeo "medio hacia arriba" y signo simétrico.
     *
     * Sustituye a la división flotante en todos los prorrateos: el resultado es
     * el único punto de redondeo de cada importe.
     */
    public static function roundDivide(int $numerator, int $denominator): int
    {
        if ($denominator === 0) {
            return 0;
        }

        $sign = ($numerator < 0) !== ($denominator < 0) ? -1 : 1;
        $absoluteNumerator = abs($numerator);
        $absoluteDenominator = abs($denominator);

        $rounded = intdiv(($absoluteNumerator * 2) + $absoluteDenominator, $absoluteDenominator * 2);

        return $sign * $rounded;
    }

    /**
     * Aplica una proporción expresada en puntos base (1.0 = 10000).
     */
    public static function applyBasisPoints(int $cents, int $basisPoints): int
    {
        return self::roundDivide($cents * $basisPoints, 10000);
    }
}
