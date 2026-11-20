import type {
    ScheduleDay,
    ScheduleDayForm,
    ScheduleRecord,
    ScheduleSegment,
    ScheduleSegmentForm,
    WeekdayOption,
} from '@/types';

const DEFAULT_START = '09:00';
const DEFAULT_END = '18:00';
const DEFAULT_TOLERANCE = '10';

const WEEKDAY_LABELS = [
    'Lunes',
    'Martes',
    'Miércoles',
    'Jueves',
    'Viernes',
    'Sábado',
    'Domingo',
];

export type NormalizedWeekday = {
    value: number;
    label: string;
};

/** Siempre 7 días (1 = lunes … 7 = domingo); usa el catálogo del backend si viene completo. */
export function normalizeWeekdays(
    weekdays: WeekdayOption[] | null | undefined,
): NormalizedWeekday[] {
    const labels = new Map<number, string>();

    for (const weekday of weekdays ?? []) {
        const value = Number(weekday.value);

        if (Number.isInteger(value) && value >= 1 && value <= 7) {
            labels.set(value, weekday.label);
        }
    }

    return WEEKDAY_LABELS.map((label, index) => ({
        value: index + 1,
        label: labels.get(index + 1) ?? label,
    }));
}

function toSegmentForm(segment: ScheduleSegment): ScheduleSegmentForm {
    return {
        start_time: segment.start_time,
        end_time: segment.end_time,
        kind: segment.kind,
    };
}

/**
 * Construye el estado del editor semanal.
 *
 * Sin registro (`null`) parte de un horario editable de lunes a viernes
 * 09:00–18:00 con sábado y domingo como descanso: solo es un punto de partida
 * de la interfaz, la vigencia y la validación las define el backend.
 */
export function buildScheduleDays(
    record: ScheduleRecord | null,
    weekdayValues: number[],
): ScheduleDayForm[] {
    if (!record) {
        return weekdayValues.map((weekday) => ({
            weekday,
            is_rest_day: weekday > 5,
            late_tolerance_minutes: DEFAULT_TOLERANCE,
            segments:
                weekday > 5
                    ? []
                    : [
                          {
                              start_time: DEFAULT_START,
                              end_time: DEFAULT_END,
                              kind: 'work',
                          },
                      ],
        }));
    }

    const byWeekday = new Map<number, ScheduleDay>();

    for (const day of record.days ?? []) {
        byWeekday.set(Number(day.weekday), day);
    }

    return weekdayValues.map((weekday) => {
        const day = byWeekday.get(weekday);

        if (!day) {
            return {
                weekday,
                is_rest_day: true,
                late_tolerance_minutes: DEFAULT_TOLERANCE,
                segments: [],
            };
        }

        return {
            weekday,
            is_rest_day: Boolean(day.is_rest_day),
            late_tolerance_minutes: String(
                day.late_tolerance_minutes ?? DEFAULT_TOLERANCE,
            ),
            segments: (day.segments ?? []).map(toSegmentForm),
        };
    });
}
