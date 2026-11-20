import type { Paginated } from './pagination';

/**
 * Conceptos de pre-nómina — contrato ARCHITECTURE §7.4.
 * Catálogo administrable por `concepts.manage`; el `code` es único.
 */

export type PayrollConceptTypeValue = 'earning' | 'deduction';

export type PayrollConceptCalculationValue = 'fixed' | 'per_quantity';

export type PayrollConceptRow = {
    id: number;
    code: string;
    name: string;
    type: PayrollConceptTypeValue;
    calculation: PayrollConceptCalculationValue;
    default_amount: string | number | null;
    is_sensitive: boolean;
    is_active: boolean;
    notes: string | null;
};

export type PayrollConceptPermissions = {
    manage: boolean;
};

export type PayrollConceptIndexProps = {
    /**
     * §7.4 declara `concepts` como arreglo simple; se tolera paginado si el
     * backend lo envía para crecer el catálogo.
     */
    concepts: PayrollConceptRow[] | Paginated<PayrollConceptRow>;
    can: PayrollConceptPermissions;
    notice?: string | null;
};

export type PayrollConceptFormData = {
    code: string;
    name: string;
    type: string;
    calculation: string;
    default_amount: string;
    is_sensitive: boolean;
    is_active: boolean;
    notes: string;
};
