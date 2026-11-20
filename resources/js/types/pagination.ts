/**
 * Enlaces del paginador de Laravel (`$paginator->links()`).
 * Se comparten hacia Inertia tal cual y se consumen en `Pagination.vue`.
 */
export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

/**
 * Colección paginada de Laravel compartida por Inertia
 * (`data`, `links`, `meta`) — ARCHITECTURE §7.1.
 */
export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    meta?: PaginationMeta | null;
};
