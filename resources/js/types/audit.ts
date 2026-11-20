import type { RawSelectOption } from './options';
import type { Paginated } from './pagination';
import type { ActorRef } from './ui';

/**
 * Contrato de auditoría (docs/AI/ARCHITECTURE.md §7.4).
 * Solo se presentan metadatos: nunca payloads con PIN, biometría o secretos.
 */

export type AuditFilters = {
    action: string | null;
    user_id: string | number | null;
    from: string | null;
    to: string | null;
    per_page?: string | number | null;
};

export type AuditLogRow = {
    id: number;
    created_at: string;
    user: ActorRef;
    action: string;
    auditable_type: string | null;
    auditable_id: number | string | null;
    ip_address: string | null;
};

export type AuditOptions = {
    actions: RawSelectOption[];
    users: RawSelectOption[];
};

export type AuditPermissions = {
    view?: boolean;
    export?: boolean;
};

export type AuditIndexProps = {
    filters?: AuditFilters;
    logs?: Paginated<AuditLogRow>;
    options?: Partial<AuditOptions>;
    can?: AuditPermissions;
};
