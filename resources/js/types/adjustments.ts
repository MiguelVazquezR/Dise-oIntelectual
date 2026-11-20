import type { RawSelectOption } from './options';
import type { Paginated } from './pagination';
import type { ActorRef } from './ui';

/**
 * Ajustes de nómina (bonos y penalizaciones) — contrato ARCHITECTURE §7.4.
 * Los descuentos marcados `is_sensitive` requieren revisión adicional.
 */

export type AdjustmentStatus = 'pending' | 'approved' | 'rejected';

export type AdjustmentDecision = 'approved' | 'rejected';

export type AdjustmentEmployeeRef = {
    id: number;
    full_name: string;
    employee_number?: string | null;
};

export type AdjustmentBranchRef = {
    id: number;
    name: string;
};

export type AdjustmentConceptRef = {
    id: number;
    code: string;
    name: string;
    type: string;
};

export type AdjustmentRow = {
    id: number;
    employee: AdjustmentEmployeeRef;
    branch: AdjustmentBranchRef | null;
    concept: AdjustmentConceptRef;
    amount: string | number | null;
    quantity: string | number | null;
    reason: string;
    status: AdjustmentStatus;
    created_by: ActorRef;
    approved_by: ActorRef;
    approved_at: string | null;
    is_sensitive: boolean;
};

export type AdjustmentFilters = {
    period_id: string | number | null;
    branch_id: string | number | null;
    employee_id: string | number | null;
    status: string | null;
    type: string | null;
};

export type AdjustmentOptions = {
    branches: RawSelectOption[];
    concepts: RawSelectOption[];
    periods: RawSelectOption[];
    /**
     * §7.4 no lista `employees` en `options`, pero el alta exige
     * `employee_id` con búsqueda (`EmployeeSearchSelect`). Pendiente de
     * confirmar con el coordinador.
     */
    employees?: RawSelectOption[];
};

export type AdjustmentPermissions = {
    manage: boolean;
    approve: boolean;
};

export type AdjustmentIndexProps = {
    filters: AdjustmentFilters;
    adjustments: Paginated<AdjustmentRow>;
    options: AdjustmentOptions;
    can: AdjustmentPermissions;
    /** Aviso del backend sobre descuentos sensibles. */
    warning?: string | null;
    notice?: string | null;
};

export type AdjustmentFormData = {
    employee_id: string;
    payroll_concept_id: string;
    payroll_period_id: string;
    amount: string;
    quantity: string;
    reason: string;
};

export type AdjustmentDecisionFormData = {
    decision: AdjustmentDecision;
    notes: string;
};
