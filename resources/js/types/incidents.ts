import type { RawSelectOption } from './options';
import type { Paginated } from './pagination';
import type { ActorRef } from './ui';

/** Incidencias — contrato ARCHITECTURE §7.3. */

export type IncidentStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';

export type IncidentDecision = 'approved' | 'rejected';

export type IncidentEmployeeRef = {
    id: number;
    full_name: string;
    employee_number?: string | null;
};

export type IncidentBranchRef = {
    id: number;
    name: string;
};

export type IncidentRow = {
    id: number;
    employee: IncidentEmployeeRef;
    branch: IncidentBranchRef | null;
    type: string;
    start_date: string;
    end_date: string;
    quantity: string | number;
    status: IncidentStatus | string;
    reason: string | null;
    created_by: ActorRef;
    decided_by: ActorRef;
    decided_at: string | null;
    notes?: string | null;
    /** `true` cuando la incidencia tiene comprobante cargado. */
    has_evidence?: boolean;
    /** Enlace al comprobante, si el backend lo expone. */
    evidence_url?: string | null;
};

export type IncidentFilters = {
    from: string | null;
    to: string | null;
    branch_id: string | number | null;
    employee_id: string | number | null;
    type: string | null;
    status: string | null;
    per_page?: string | number | null;
};

export type IncidentOptions = {
    branches: RawSelectOption[];
    employees: RawSelectOption[];
    types: RawSelectOption[];
    statuses: RawSelectOption[];
};

export type IncidentPermissions = {
    manage: boolean;
    approve: boolean;
};

export type IncidentIndexProps = {
    filters: IncidentFilters;
    incidents: Paginated<IncidentRow>;
    options: IncidentOptions;
    can: IncidentPermissions;
};

export type IncidentFormData = {
    employee_id: string;
    type: string;
    start_date: string;
    end_date: string;
    quantity: string;
    reason: string;
    evidence: File | null;
};

export type IncidentDecisionFormData = {
    decision: IncidentDecision;
    notes: string;
};
