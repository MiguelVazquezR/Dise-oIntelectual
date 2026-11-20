export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type StatusVariant =
    | 'neutral'
    | 'success'
    | 'warning'
    | 'danger'
    | 'info';

/**
 * Usuario que registró o decidió un movimiento. El backend puede enviar el
 * nombre ya resuelto, el id o un objeto `{id, name}`; se presenta con
 * `actorLabel` de `lib/labels.ts`.
 */
export type ActorRef =
    | string
    | number
    | { id?: number | null; name?: string | null }
    | null;
