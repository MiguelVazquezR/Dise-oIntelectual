import type { RawSelectOption } from './options';
import type { Paginated } from './pagination';

/**
 * Contrato de la ola 3 para asistencias y checador
 * (docs/AI/ARCHITECTURE.md §7.3). El backend es la fuente de verdad de
 * cálculos y estados; aquí solo se describen las props.
 */

export type AttendanceDayStatus =
    | 'complete'
    | 'incomplete'
    | 'absence'
    | 'rest_day'
    | 'holiday'
    | 'vacation'
    | 'incident';

export type AttendanceEmployeeRef = {
    id: number;
    full_name: string;
    employee_number: string;
};

export type AttendanceBranchRef = {
    id: number;
    name: string;
    timezone?: string | null;
};

export type AttendanceScheduledShift = {
    start: string | null;
    end: string | null;
    tolerance_minutes: number | string | null;
    is_rest_day: boolean;
};

export type AttendanceRowIncident = {
    id: number;
    type: string;
    status: string;
};

/** Una fila = un día de un colaborador. */
export type AttendanceRow = {
    employee: AttendanceEmployeeRef;
    branch: AttendanceBranchRef | null;
    work_date: string;
    scheduled: AttendanceScheduledShift | null;
    clock_in: string | null;
    break_start: string | null;
    break_end: string | null;
    clock_out: string | null;
    worked_minutes: number | string | null;
    break_minutes: number | string | null;
    late_minutes: number | string | null;
    overtime_minutes: number | string | null;
    status: AttendanceDayStatus | string;
    incidents: AttendanceRowIncident[];
};

export type AttendanceFilters = {
    from: string | null;
    to: string | null;
    branch_id: string | number | null;
    employee_id: string | number | null;
    search: string | null;
    per_page?: string | number | null;
};

export type AttendanceSummary = {
    employees: number | string | null;
    late_count: number | string | null;
    absence_count: number | string | null;
    overtime_minutes: number | string | null;
    worked_minutes: number | string | null;
};

export type AttendanceOptions = {
    branches: RawSelectOption[];
};

export type AttendancePermissions = {
    manage: boolean;
    incidents: boolean;
};

export type AttendanceIndexProps = {
    filters: AttendanceFilters;
    rows: Paginated<AttendanceRow>;
    summary: AttendanceSummary;
    options: AttendanceOptions;
    can: AttendancePermissions;
};

export type AttendancePeriod = {
    from: string;
    to: string;
};

export type AttendanceEmployeeDetail = AttendanceEmployeeRef & {
    branch: AttendanceBranchRef | null;
};

export type AttendanceTotals = {
    worked_minutes: number | string | null;
    late_minutes: number | string | null;
    overtime_minutes: number | string | null;
    absences: number | string | null;
    vacations: number | string | null;
};

export type AttendanceEmployeeProps = {
    employee: AttendanceEmployeeDetail;
    period: AttendancePeriod;
    days: AttendanceRow[];
    totals: AttendanceTotals;
    can: AttendancePermissions;
};

/** Checador táctil — `/kiosk`. */
export type KioskEventType =
    | 'clock_in'
    | 'break_start'
    | 'break_end'
    | 'clock_out';

export type KioskBranch = {
    id: number;
    name: string;
    timezone: string | null;
};

export type KioskRecentEvent = {
    employee_name: string;
    type: string;
    occurred_at: string;
    method: string;
};

/** Estado de AWS sin secretos (colección enmascarada y mensaje del backend). */
export type KioskFaceStatus = {
    configured: boolean;
    enabled: boolean;
    collection_masked: string | null;
    message: string | null;
};

export type KioskPermissions = {
    face: boolean;
};

export type KioskProps = {
    branch: KioskBranch | null;
    /** Estado de AWS; si el backend aún no lo envía se asume no configurado. */
    face?: KioskFaceStatus;
    recent_events?: KioskRecentEvent[];
    can: KioskPermissions;
    /** Solo se envía cuando el operador tiene más de una sucursal. */
    options?: {
        branches: RawSelectOption[];
    };
};

export type GpsPayload = {
    latitude: number;
    longitude: number;
    accuracy: number;
};

export type KioskPinFormData = {
    employee_number: string;
    pin: string;
    branch_id: string;
    event_type: KioskEventType | '';
    gps: GpsPayload | null;
};

export type KioskFaceFormData = {
    branch_id: string;
    event_type: KioskEventType;
    image: string;
    gps: GpsPayload | null;
};

/** Vistas de solo lectura «Mi asistencia». */
export type MyAttendanceProps = {
    employee: AttendanceEmployeeDetail | null;
    period: AttendancePeriod | null;
    days: AttendanceRow[];
    totals: AttendanceTotals | null;
};
