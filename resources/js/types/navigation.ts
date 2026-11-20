import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    /**
     * Ruta como string literal mientras no exista la función generada por
     * Wayfinder (ver docs/AI/ARCHITECTURE.md §6).
     */
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /** Permiso requerido para mostrar el ítem (ARCHITECTURE §4). */
    permission?: string;
};

export type NavSection = {
    label: string;
    items: NavItem[];
};
