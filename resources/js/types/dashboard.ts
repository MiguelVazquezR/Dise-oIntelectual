import type { RawSelectOption } from './options';

/**
 * Contrato del dashboard administrativo (docs/AI/ARCHITECTURE.md §7.4).
 *
 * Cada bloque llega opcional: el controlador de la ola 4 se implementa en
 * paralelo y, si un bloque falta, la página muestra `EmptyState` en lugar de
 * cifras inventadas. El backend es la fuente de verdad de todos los cálculos.
 */

export type DashboardFilters = {
    period_id: string | number | null;
    branch_id: string | number | null;
};

export type DashboardBranchRef = {
    id?: number | null;
    name?: string | null;
};

export type DashboardEmployeeRef = {
    id?: number | null;
    full_name?: string | null;
    name?: string | null;
    employee_number?: string | null;
};

export type DashboardMetrics = {
    active_employees: number | string | null;
    late_count: number | string | null;
    justified_absences: number | string | null;
    unjustified_absences: number | string | null;
    overtime_minutes: number | string | null;
    incidents_count: number | string | null;
    payroll_status: string | null;
};

export type DashboardBranchRow = {
    branch: DashboardBranchRef | null;
    employees: number | string | null;
    late_count: number | string | null;
    absences: number | string | null;
    overtime_minutes: number | string | null;
};

export type DashboardTrendPoint = {
    date: string;
    late_count: number | string | null;
    absences: number | string | null;
};

/** Periodo de pre-nómina resumido: se acepta la forma completa del recurso. */
export type DashboardPayrollPeriod = {
    id?: number | null;
    label?: string | null;
    start_date?: string | null;
    end_date?: string | null;
    payment_date?: string | null;
    status?: string | null;
};

export type DashboardPayrollTotals = {
    earnings?: string | number | null;
    deductions?: string | number | null;
    net?: string | number | null;
};

export type DashboardPayroll = {
    period: DashboardPayrollPeriod | null;
    status: string | null;
    totals: DashboardPayrollTotals | null;
};

export type DashboardIncident = {
    id: number;
    employee: DashboardEmployeeRef | null;
    branch?: DashboardBranchRef | null;
    type: string;
    start_date?: string | null;
    end_date?: string | null;
    quantity?: string | number | null;
    status?: string | null;
    created_at?: string | null;
};

/**
 * Banderas de `can` del dashboard (§7.4 no detalla las claves).
 * Si una bandera no llega se usa el permiso compartido `auth.permissions`.
 */
export type DashboardPermissions = {
    payroll?: boolean;
    incidents?: boolean;
    attendance?: boolean;
    vacations?: boolean;
    aws_usage?: boolean;
    audit?: boolean;
    manage?: boolean;
};

/** Catálogos de filtros; §7.4 no los incluye todavía (ver reporte). */
export type DashboardOptions = {
    branches: RawSelectOption[];
    periods: RawSelectOption[];
};

export type DashboardProps = {
    filters?: DashboardFilters;
    metrics?: DashboardMetrics;
    by_branch?: DashboardBranchRow[];
    trend?: DashboardTrendPoint[];
    recent_incidents?: DashboardIncident[];
    payroll?: DashboardPayroll | null;
    can?: DashboardPermissions;
    options?: Partial<DashboardOptions>;
};
