/** Tipos del módulo de horarios (contrato ARCHITECTURE §7.1). */
export type ScheduleSegmentKind = 'work' | 'break';

export type ScheduleSegment = {
    start_time: string;
    end_time: string;
    kind: ScheduleSegmentKind;
    sort_order?: number;
};

export type ScheduleDay = {
    weekday: number;
    is_rest_day: boolean;
    late_tolerance_minutes: number;
    segments: ScheduleSegment[];
    notes?: string | null;
};

export type ScheduleBranch = {
    id: number;
    name: string;
};

export type ScheduleRow = {
    id: number;
    name: string;
    branch: ScheduleBranch | null;
    effective_from: string;
    effective_to: string | null;
    is_active: boolean;
    days_count: number;
};

export type ScheduleRecord = {
    id: number;
    name: string;
    branch_id: number;
    effective_from: string;
    effective_to: string | null;
    is_active: boolean;
    days: ScheduleDay[];
};

export type ScheduleFilters = {
    branch_id: string | null;
    search: string | null;
};

export type WeekdayOption = {
    value: number | string;
    label: string;
};

/** Estado local del editor semanal (los minutos viajan como texto en el input). */
export type ScheduleSegmentForm = {
    start_time: string;
    end_time: string;
    kind: ScheduleSegmentKind;
};

export type ScheduleDayForm = {
    weekday: number;
    is_rest_day: boolean;
    late_tolerance_minutes: string;
    segments: ScheduleSegmentForm[];
};

export type SchedulePermissions = {
    manage: boolean;
};
