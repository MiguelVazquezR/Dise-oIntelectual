import type { Branch } from './branches';
import type { RawSelectOption } from './options';

/** Tipos del módulo de colaboradores (contrato ARCHITECTURE §7.1). */
export type EmployeeBranch = Pick<Branch, 'id' | 'name'>;

export type EmployeeRow = {
    id: number;
    employee_number: string;
    full_name: string;
    branch: EmployeeBranch | null;
    position: string;
    hire_date: string;
    status: string;
    has_pin: boolean;
};

export type EmployeeCompensation = {
    id?: number;
    base_salary: string;
    pay_frequency: string;
    effective_from: string;
    effective_to?: string | null;
    notes?: string | null;
};

export type EmployeeCompensationInput = {
    base_salary: string;
    pay_frequency: string;
    effective_from: string;
    notes: string;
};

/** Estado local del formulario de colaborador (los ids y fechas viajan como texto). */
export type EmployeeFormData = {
    employee_number: string;
    first_name: string;
    last_name: string;
    second_last_name: string;
    branch_id: string;
    position: string;
    department: string;
    email: string;
    phone: string;
    hire_date: string;
    status: string;
    compensation: EmployeeCompensationInput;
};

export type EmployeeRecord = {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    second_last_name: string | null;
    branch_id: number;
    position: string;
    department: string | null;
    email: string | null;
    phone: string | null;
    hire_date: string;
    status: string;
    compensation: EmployeeCompensation | null;
};

/** Ficha (`/employees/{id}`): incluye estado del PIN y resúmenes. */
export type EmployeeDetail = EmployeeRecord & {
    pin_updated_at: string | null;
    has_pin: boolean;
};

export type AttendanceEventSummary = {
    id: number;
    occurred_at: string;
    type: string;
    method: string;
    validation_status?: string | null;
};

export type VacationSummary = {
    days_granted: string | number | null;
    days_taken: string | number | null;
    days_pending: string | number | null;
};

export type EmployeeFilters = {
    search: string | null;
    branch_id: string | null;
    status: string | null;
    per_page: string | null;
};

export type EmployeeOptions = {
    branches: RawSelectOption[];
    statuses: RawSelectOption[];
    pay_frequencies: RawSelectOption[];
};

export type EmployeePermissions = {
    manage: boolean;
};
