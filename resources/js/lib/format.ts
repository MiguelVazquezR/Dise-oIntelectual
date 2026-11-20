/**
 * Utilidades de presentación (solo formato, nunca cálculo de negocio).
 * Convención del proyecto: es-MX, dinero como string con 2 decimales
 * (docs/AI/ARCHITECTURE.md §7).
 */
const LOCALE = 'es-MX';

const EMPTY_VALUE = '—';

const DATE_ONLY_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

type DateInput = string | Date | null | undefined;

function toFiniteNumber(
    value: string | number | null | undefined,
): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed =
        typeof value === 'number'
            ? value
            : Number.parseFloat(value.replace(/,/g, ''));

    return Number.isFinite(parsed) ? parsed : null;
}

function toDate(value: DateInput, timeZone?: string): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    // Fecha sin hora (columna `date`): evita el corrimiento de día por zona horaria.
    if (DATE_ONLY_PATTERN.test(value)) {
        if (timeZone) {
            const utc = new Date(`${value}T12:00:00Z`);

            return Number.isNaN(utc.getTime()) ? null : utc;
        }

        const [year, month, day] = value.split('-').map(Number);
        const local = new Date(year, month - 1, day);

        return Number.isNaN(local.getTime()) ? null : local;
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function formatDateWithOptions(
    value: DateInput,
    options: Intl.DateTimeFormatOptions,
    timeZone?: string,
): string {
    const date = toDate(value, timeZone);

    if (!date) {
        return EMPTY_VALUE;
    }

    const baseOptions: Intl.DateTimeFormatOptions = timeZone
        ? { ...options, timeZone }
        : options;

    try {
        return new Intl.DateTimeFormat(LOCALE, baseOptions).format(date);
    } catch {
        // Zona horaria inválida: se presenta con la zona del navegador.
        return new Intl.DateTimeFormat(LOCALE, options).format(date);
    }
}

export function formatMoney(
    value: string | number | null,
    currency = 'MXN',
): string {
    const amount = toFiniteNumber(value);

    if (amount === null) {
        return EMPTY_VALUE;
    }

    return new Intl.NumberFormat(LOCALE, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

export function formatDate(iso: DateInput, timeZone?: string): string {
    return formatDateWithOptions(
        iso,
        { day: '2-digit', month: 'short', year: 'numeric' },
        timeZone,
    );
}

export function formatDateTime(iso: DateInput, timeZone?: string): string {
    return formatDateWithOptions(
        iso,
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        },
        timeZone,
    );
}

/** Hora del día (HH:MM) para marcajes y horarios programados. */
export function formatTime(iso: DateInput, timeZone?: string): string {
    return formatDateWithOptions(
        iso,
        { hour: '2-digit', minute: '2-digit' },
        timeZone,
    );
}

/** Mes y año para agrupar listados (festivos, periodos). */
export function formatMonthYear(iso: DateInput, timeZone?: string): string {
    return formatDateWithOptions(
        iso,
        { month: 'long', year: 'numeric' },
        timeZone,
    );
}

export function formatMinutesAsHours(minutes: string | number | null): string {
    const total = toFiniteNumber(minutes);

    if (total === null) {
        return EMPTY_VALUE;
    }

    const rounded = Math.round(total);
    const sign = rounded < 0 ? '-' : '';
    const absolute = Math.abs(rounded);
    const hours = Math.floor(absolute / 60);
    const remainingMinutes = absolute % 60;

    if (hours === 0) {
        return `${sign}${remainingMinutes} min`;
    }

    if (remainingMinutes === 0) {
        return `${sign}${hours} h`;
    }

    return `${sign}${hours} h ${remainingMinutes} min`;
}

/**
 * Valor para `<input type="date">` a partir de ISO 8601 o `YYYY-MM-DD`.
 * Las columnas `date` no llevan hora, así que se toma el día tal como llega.
 */
export function toDateInputValue(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return value.slice(0, 10);
}

/**
 * Días (vacaciones, festivos): el backend envía decimales como string.
 * Se presentan como cantidad con hasta dos decimales, no como duración.
 */
export function formatDays(value: string | number | null): string {
    const days = toFiniteNumber(value);

    if (days === null) {
        return EMPTY_VALUE;
    }

    return new Intl.NumberFormat(LOCALE, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(days);
}
