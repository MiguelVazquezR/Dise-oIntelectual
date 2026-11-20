import type { RawSelectOption } from './options';
import type { Paginated } from './pagination';

/**
 * Pre-nómina y recibos — contrato ARCHITECTURE §7.4 y decisión D-14
 * (sin fiscalidad: no hay ISR, IMSS ni INFONAVIT).
 *
 * Los importes viajan como string decimal con 2 decimales y se presentan
 * con `formatMoney`; el frontend no recalcula ni redondea (S-05).
 */

export type PayrollPeriodStatus = 'open' | 'calculated' | 'reviewed' | 'closed';

export type PayrollRunStatus = 'draft' | 'calculated' | 'reviewed' | 'closed';

export type PayrollTotals = {
    earnings: string | number | null;
    deductions: string | number | null;
    net: string | number | null;
};

export type PayrollScheduleRef = {
    id: number;
    name: string;
    frequency?: string | null;
};

export type PayrollBranchRef = {
    id: number;
    name: string;
};

export type PayrollPeriodRow = {
    id: number;
    schedule: PayrollScheduleRef | null;
    start_date: string;
    end_date: string;
    payment_date: string;
    status: PayrollPeriodStatus;
    runs_count: number | string | null;
    totals: PayrollTotals | null;
};

/** `Show` añade los datos de cierre del periodo. */
export type PayrollPeriodDetail = PayrollPeriodRow & {
    items_count?: number | string | null;
    closed_at?: string | null;
    closed_by?: number | null;
    created_at?: string | null;
    updated_at?: string | null;
    notice?: string | null;
};

export type PayrollRunRow = {
    id: number;
    branch: PayrollBranchRef | null;
    status: PayrollRunStatus;
    items_count: number | string | null;
    totals: PayrollTotals | null;
    calculated_at: string | null;
    reviewed_at: string | null;
    closed_at: string | null;
};

export type PayrollFilters = {
    schedule_id: string | number | null;
    status: string | null;
    from: string | null;
    to: string | null;
};

export type PayrollOptions = {
    schedules: RawSelectOption[];
};

export type PayrollPermissions = {
    calculate: boolean;
    review: boolean;
    close: boolean;
};

export type PayrollIndexProps = {
    filters: PayrollFilters;
    periods: Paginated<PayrollPeriodRow>;
    options: PayrollOptions;
    can: PayrollPermissions;
    /** Aviso de pre-nómina preliminar (§7.4). */
    notice?: string | null;
};

export type PayrollPeriodFormData = {
    payroll_schedule_id: string;
    start_date: string;
    end_date: string;
    payment_date: string;
};

export type PayrollItemEmployeeRef = {
    /** `id` no forma parte de §7.4; se tolera si el backend lo envía. */
    id?: number;
    full_name: string;
    employee_number?: string | null;
    position?: string | null;
};

/**
 * Fila por colaborador para enlazar su recibo.
 * §7.4 no incluye `items` en `Show`; ver nota en `PayrollShowProps`.
 */
export type PayrollItemRow = {
    id: number;
    employee: PayrollItemEmployeeRef;
    branch?: PayrollBranchRef | null;
    net_amount?: string | number | null;
    totals?: PayrollTotals | null;
};

export type PayrollShowProps = {
    period: PayrollPeriodDetail;
    runs: PayrollRunRow[];
    totals: PayrollTotals | null;
    can: PayrollPermissions;
    closed_snapshot_at: string | null;
    notice?: string | null;
    /**
     * Prop opcional: §7.4 sólo contrata `runs` por sucursal, pero la pantalla
     * debe enlazar el recibo de cada colaborador (`/payroll/items/{id}/receipt`).
     * Pendiente de confirmar con el coordinador; si no llega, la sección no
     * se muestra.
     */
    items?: PayrollItemRow[] | null;
};

export type PayrollReceiptConcept = {
    code: string;
    name: string;
    type: string;
    quantity: string | number | null;
    unit_amount: string | number | null;
    amount: string | number | null;
};

export type PayrollReceiptEmployee = {
    employee_number: string;
    full_name: string;
    position: string | null;
};

export type PayrollReceiptPeriod = {
    start_date: string;
    end_date: string;
    payment_date: string;
};

export type PayrollReceiptItem = {
    employee: PayrollReceiptEmployee;
    branch: PayrollBranchRef | null;
    period: PayrollReceiptPeriod;
    concepts: PayrollReceiptConcept[];
    totals: PayrollTotals;
};

export type PayrollReceiptCompany = {
    name: string;
    logo_url?: string | null;
    address?: string | null;
};

/** `signature_lines` no fija forma en §7.4; se aceptan textos u objetos. */
export type PayrollSignatureLine =
    | string
    | {
          label?: string | null;
          /** El backend actual envía `name`; `caption` se tolera como alias. */
          name?: string | null;
          caption?: string | null;
      };

export type PayrollReceiptProps = {
    item: PayrollReceiptItem;
    company: PayrollReceiptCompany | null;
    notice?: string | null;
    signature_lines?: PayrollSignatureLine[] | null;
};
