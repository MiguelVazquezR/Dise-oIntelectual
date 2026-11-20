import type { RawSelectOption } from './options';

/** Festivos — contrato ARCHITECTURE §7.3. */

export type HolidayType = 'mandatory' | 'company';

export type HolidayBranchRef = {
    id: number;
    name: string;
};

export type HolidayRow = {
    id: number;
    name: string;
    date: string;
    type: HolidayType | string;
    branch_id: number | null;
    branch: HolidayBranchRef | null;
    legal_basis: string | null;
    source_url: string | null;
    source_checked_at: string | null;
    /** Año cerrado o con nómina cerrada: no admite cambios. */
    is_locked: boolean;
};

/**
 * El backend puede enviar la colección plana, agrupada por mes en un arreglo
 * (`{month, label, days}` o `{month, label, holidays}`) o como diccionario
 * `{ "01": [...] }`. La página lo normaliza para pintar.
 */
export type HolidayMonthGroup = {
    month?: number | string | null;
    label?: string | null;
    month_label?: string | null;
    days?: HolidayRow[];
    holidays?: HolidayRow[];
};

export type HolidaysProp =
    | HolidayRow[]
    | HolidayMonthGroup[]
    | Record<string, HolidayRow[]>;

export type HolidaySummary = {
    mandatory: number;
    company: number;
    total: number;
};

export type HolidayFilters = {
    year: string | number | null;
    branch_id: string | number | null;
    type: string | null;
};

export type HolidayOptions = {
    years: RawSelectOption[];
    branches: RawSelectOption[];
    types?: RawSelectOption[];
};

export type HolidayPermissions = {
    manage: boolean;
};

export type HolidayIndexProps = {
    filters: HolidayFilters;
    holidays: HolidaysProp;
    summary: HolidaySummary;
    options: HolidayOptions;
    can: HolidayPermissions;
    /** Aviso legal con fuente y fecha de revisión; puede ser `null`. */
    legal_notice: string | null;
    /** Año protegido por cierre de nómina (todas las filas bloqueadas). */
    year_locked?: boolean;
};

export type HolidayFormData = {
    name: string;
    date: string;
    type: HolidayType;
    branch_id: string;
    legal_basis: string;
};
