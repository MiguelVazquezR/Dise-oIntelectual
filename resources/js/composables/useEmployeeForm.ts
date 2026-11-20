import { useForm } from '@inertiajs/vue3';
import type { InertiaForm } from '@inertiajs/vue3';
import { toDateInputValue } from '@/lib/format';
import type { EmployeeFormData, EmployeeRecord } from '@/types';

type EmployeeFormDefaults = {
    status: string;
    payFrequency: string;
};

/** Estado inicial del formulario de colaborador (crear y editar). */
export function useEmployeeForm(
    record: EmployeeRecord | null,
    defaults: EmployeeFormDefaults,
): InertiaForm<EmployeeFormData> {
    const compensation = record?.compensation ?? null;

    return useForm<EmployeeFormData>({
        employee_number: record?.employee_number ?? '',
        first_name: record?.first_name ?? '',
        last_name: record?.last_name ?? '',
        second_last_name: record?.second_last_name ?? '',
        branch_id: record ? String(record.branch_id) : '',
        position: record?.position ?? '',
        department: record?.department ?? '',
        email: record?.email ?? '',
        phone: record?.phone ?? '',
        hire_date: toDateInputValue(record?.hire_date),
        status: record?.status ?? defaults.status,
        compensation: {
            base_salary: compensation?.base_salary ?? '',
            pay_frequency: compensation?.pay_frequency ?? defaults.payFrequency,
            effective_from: toDateInputValue(compensation?.effective_from),
            notes: compensation?.notes ?? '',
        },
    });
}

/** Normaliza el payload: opcionales vacíos como `null` y vigencia por defecto. */
export function transformEmployeeForm(
    data: EmployeeFormData,
): Record<string, unknown> {
    return {
        ...data,
        second_last_name:
            data.second_last_name === '' ? null : data.second_last_name,
        department: data.department === '' ? null : data.department,
        email: data.email === '' ? null : data.email,
        phone: data.phone === '' ? null : data.phone,
        compensation: {
            base_salary: data.compensation.base_salary,
            pay_frequency: data.compensation.pay_frequency,
            effective_from:
                data.compensation.effective_from === ''
                    ? data.hire_date
                    : data.compensation.effective_from,
            notes:
                data.compensation.notes === '' ? null : data.compensation.notes,
        },
    };
}
