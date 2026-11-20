/**
 * Catálogos que el backend envía en la prop `options` (ARCHITECTURE §7.1).
 * El contrato no fija la forma de cada elemento (`{value,label}`, `{id,name}`,
 * `{slug,name}` o un escalar), por lo que se normalizan en `lib/options.ts`.
 */
export type RawSelectOption =
    | string
    | number
    | {
          value?: string | number | null;
          label?: string | null;
          name?: string | null;
          full_name?: string | null;
          employee_number?: string | null;
          id?: number | null;
          slug?: string | null;
      };

/** Opción ya normalizada para `Select`/checkboxes. */
export type SelectOption = {
    value: string;
    label: string;
};
