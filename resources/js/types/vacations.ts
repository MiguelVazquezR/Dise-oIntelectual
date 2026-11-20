import type { RawSelectOption } from './options';
import type { Paginated } from './pagination';
import type { ActorRef } from './ui';

/** Vacaciones — contrato ARCHITECTURE §7.3. */

export type VacationRequestStatus =
    | 'pending'
    | 'approved'
    | 'rejected'
    | 'cancelled';

export type VacationDecision = 'approved' | 'rejected';

export type VacationEmployeeRef = {
    id: number;
    full_name: string;
    employee_number: string;
    hire_date?: string | null;
    branch?: VacationBranchRef | null;
};

export type VacationBranchRef = {
    id: number;
    name: string;
};

export type VacationPeriodRow = {
    id: number;
    employee: VacationEmployeeRef;
    branch: VacationBranchRef | null;
    period_start: string;
    period_end: string;
    anniversary_years: number | string;
    days_granted: string | number | null;
    days_taken: string | number | null;
    days_adjustment: string | number | null;
    /** Días solicitados aún pendientes de aprobación. */
    days_pending: string | number | null;
    /** Saldo disponible para el periodo (otorgados + ajustes − tomados). */
    days_available?: string | number | null;
    status: string;
};

export type VacationRequestRow = {
    id: number;
    employee: VacationEmployeeRef;
    start_date: string;
    end_date: string;
    days: string | number | null;
    status: VacationRequestStatus | string;
    requested_by: ActorRef;
    decided_by: ActorRef;
    decided_at: string | null;
    notes: string | null;
};

export type VacationFilters = {
    year: string | number | null;
    branch_id: string | number | null;
    employee_id: string | number | null;
};

export type VacationOptions = {
    branches: RawSelectOption[];
    years: RawSelectOption[];
    /**
     * Catálogo para buscar colaboradores en el alta de solicitudes.
     * §7.3 no lo incluye en `options`; si el backend no lo envía, la página
     * ofrece capturar el id de colaborador.
     */
    employees?: RawSelectOption[];
    request_statuses?: RawSelectOption[];
};

export type VacationPermissions = {
    manage: boolean;
    approve: boolean;
};

export type VacationIndexProps = {
    filters: VacationFilters;
    periods: Paginated<VacationPeriodRow>;
    requests: Paginated<VacationRequestRow>;
    options: VacationOptions;
    can: VacationPermissions;
    /** Regla legal con fuente y fecha de revisión (informativa). */
    rules?: {
        legal_basis?: string | null;
        source_url?: string | null;
        source_checked_at?: string | null;
        legal_notice?: string | null;
    } | null;
};

export type VacationRequestFormData = {
    employee_id: string;
    start_date: string;
    end_date: string;
    notes: string;
};

export type VacationDecisionFormData = {
    decision: VacationDecision;
    notes: string;
};

export type VacationTotals = {
    days_granted: string | number | null;
    days_taken: string | number | null;
    /** Días solicitados pendientes de aprobación. */
    days_pending: string | number | null;
    days_adjustment?: string | number | null;
    /** Saldo disponible (otorgados + ajustes − tomados). */
    days_available?: string | number | null;
};

/**
 * Fila del periodo en «Mis vacaciones»: el backend envía solo los datos del
 * periodo (sin el colaborador, que ya viene en `employee`).
 */
export type MyVacationPeriodRow = {
    id: number;
    period_start: string;
    period_end: string;
    anniversary_years: number | string;
    days_granted: string | number | null;
    days_taken: string | number | null;
    days_adjustment: string | number | null;
    days_pending: string | number | null;
    days_available?: string | number | null;
    status: string;
};

export type MyVacationRequestRow = {
    id: number;
    start_date: string;
    end_date: string;
    days: string | number | null;
    status: VacationRequestStatus | string;
    decided_at: string | null;
    notes: string | null;
    status_label?: string | null;
};

/** Vista de solo lectura «Mis vacaciones» (§7.3 y props del backend). */
export type MyVacationsProps = {
    employee: VacationEmployeeRef | null;
    periods: MyVacationPeriodRow[];
    totals: VacationTotals | null;
    /** Paginado quando el colaborador está vinculado; `[]` si no hay vínculo. */
    requests?: Paginated<MyVacationRequestRow> | MyVacationRequestRow[];
    period?: { from: string; to: string } | null;
    linked?: boolean;
    notice?: string | null;
};
