import type { RawSelectOption, SelectOption } from '@/types';

/** Clave preferida al normalizar una opción del backend. */
export type PreferredOptionKey = 'value' | 'slug' | 'id';

const KEY_ORDER: Record<PreferredOptionKey, PreferredOptionKey[]> = {
    value: ['value', 'slug', 'id'],
    slug: ['slug', 'value', 'id'],
    id: ['id', 'value', 'slug'],
};

function humanize(value: string): string {
    if (value === '') {
        return '';
    }

    const spaced = value.replace(/[_-]+/g, ' ').trim();

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

function isObjectOption(
    option: RawSelectOption,
): option is Exclude<RawSelectOption, string | number> {
    return typeof option === 'object' && option !== null;
}

export function optionValue(
    option: RawSelectOption,
    preferred: PreferredOptionKey = 'value',
): string {
    if (typeof option === 'string' || typeof option === 'number') {
        return String(option);
    }

    if (!isObjectOption(option)) {
        return '';
    }

    for (const key of KEY_ORDER[preferred]) {
        const candidate = option[key];

        if (candidate !== undefined && candidate !== null && candidate !== '') {
            return String(candidate);
        }
    }

    return '';
}

export function optionLabel(
    option: RawSelectOption,
    preferred: PreferredOptionKey = 'value',
): string {
    if (typeof option === 'string') {
        return humanize(option);
    }

    if (typeof option === 'number') {
        return String(option);
    }

    if (isObjectOption(option)) {
        const label = option.label ?? option.full_name ?? option.name;

        if (label !== undefined && label !== null && label !== '') {
            return String(label);
        }
    }

    return humanize(optionValue(option, preferred));
}

/**
 * Normaliza los catálogos de `options` a `{ value, label }`.
 * Acepta escalares, `{value,label}`, `{id,name}` y `{slug,name}`.
 */
export function toSelectOptions(
    options: RawSelectOption[] | null | undefined,
    preferred: PreferredOptionKey = 'value',
): SelectOption[] {
    if (!Array.isArray(options)) {
        return [];
    }

    return options
        .map((option) => ({
            value: optionValue(option, preferred),
            label: optionLabel(option, preferred),
        }))
        .filter((option) => option.value !== '');
}

/**
 * Catálogo con identidad numérica (roles, sucursales) para checkboxes
 * que envían `role_ids[]` / `branch_ids[]`.
 */
export function toIdOptions(
    options: RawSelectOption[] | null | undefined,
    preferred: PreferredOptionKey = 'id',
): { id: number; label: string }[] {
    return toSelectOptions(options, preferred)
        .map((option) => ({ id: Number(option.value), label: option.label }))
        .filter((option) => Number.isInteger(option.id) && option.id > 0);
}

/**
 * Catálogo de colaboradores para búsqueda por nombre o número.
 * El backend envía `{id, name|full_name, employee_number}`.
 */
export function toEmployeeOptions(
    options: RawSelectOption[] | null | undefined,
): SelectOption[] {
    if (!Array.isArray(options)) {
        return [];
    }

    return options
        .map((option) => {
            const value = optionValue(option, 'id');
            const base = optionLabel(option, 'id');
            const number =
                typeof option === 'object' &&
                option !== null &&
                option.employee_number
                    ? String(option.employee_number)
                    : '';

            return {
                value,
                label: number === '' ? base : `${base} · ${number}`,
            };
        })
        .filter((option) => option.value !== '');
}

/** Normaliza un filtro del backend (id, año o texto) al string de la query. */
export function filterValue(value: string | number | null | undefined): string {
    return value === null || value === undefined ? '' : String(value);
}
