import { usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BadgeDollarSign,
    Building2,
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    CalendarRange,
    ClipboardClock,
    CloudCog,
    LayoutDashboard,
    Plane,
    ScanFace,
    ScrollText,
    Settings,
    ShieldCheck,
    Tags,
    UserCog,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { NavItem, NavSection } from '@/types';

/**
 * Navegación agrupada del sistema.
 *
 * El backend es la fuente de verdad de los permisos: aquí solo se ocultan
 * los ítems que el usuario no puede ver. Si la prop `auth.permissions` aún no
 * existe (backend en construcción) no se oculta nada.
 *
 * TODO: migrar a Wayfinder cuando existan las rutas.
 */
export const navigationSections: NavSection[] = [
    {
        label: 'Principal',
        items: [
            {
                title: 'Dashboard',
                href: '/dashboard',
                icon: LayoutDashboard,
                permission: 'dashboard.view',
            },
            {
                title: 'Checador',
                href: '/kiosk',
                icon: ScanFace,
                permission: 'attendance.record',
            },
            {
                title: 'Mi asistencia',
                href: '/my/attendance',
                icon: CalendarCheck,
            },
            {
                title: 'Mis vacaciones',
                href: '/my/vacations',
                icon: Plane,
            },
        ],
    },
    {
        label: 'Operación',
        items: [
            {
                title: 'Colaboradores',
                href: '/employees',
                icon: Users,
                permission: 'employees.view',
            },
            {
                title: 'Asistencias',
                href: '/attendance',
                icon: ClipboardClock,
                permission: 'attendance.view',
            },
            {
                title: 'Incidencias',
                href: '/incidents',
                icon: AlertTriangle,
                permission: 'incidents.view',
            },
            {
                title: 'Vacaciones',
                href: '/vacations',
                icon: Plane,
                permission: 'vacations.view',
            },
            {
                title: 'Festivos',
                href: '/holidays',
                icon: CalendarDays,
                permission: 'holidays.view',
            },
        ],
    },
    {
        label: 'Nómina',
        items: [
            {
                title: 'Periodos',
                href: '/payroll/periods',
                icon: CalendarRange,
                permission: 'payroll.view',
            },
            {
                title: 'Bonos y descuentos',
                href: '/adjustments',
                icon: BadgeDollarSign,
                permission: 'adjustments.view',
            },
            {
                title: 'Conceptos',
                href: '/concepts',
                icon: Tags,
                permission: 'concepts.view',
            },
        ],
    },
    {
        label: 'Administración',
        items: [
            {
                title: 'Sucursales',
                href: '/branches',
                icon: Building2,
                permission: 'branches.view',
            },
            {
                title: 'Horarios',
                href: '/schedules',
                icon: CalendarClock,
                permission: 'schedules.view',
            },
            {
                title: 'Usuarios',
                href: '/users',
                icon: UserCog,
                permission: 'users.view',
            },
            {
                title: 'Roles',
                href: '/roles',
                icon: ShieldCheck,
                permission: 'roles.manage',
            },
            {
                title: 'Consumo AWS',
                href: '/aws/usage',
                icon: CloudCog,
                permission: 'aws.usage.view',
            },
            {
                title: 'Configuración AWS',
                href: '/settings/aws',
                icon: Settings,
                permission: 'aws.settings.view',
            },
            {
                title: 'Auditoría',
                href: '/audit',
                icon: ScrollText,
                permission: 'audit.view',
            },
        ],
    },
];

type Permissions = string[] | null;

function canSee(item: NavItem, permissions: Permissions): boolean {
    if (!item.permission) {
        return true;
    }

    if (permissions === null) {
        return true;
    }

    return permissions.includes(item.permission);
}

export function usePermissions(): {
    permissions: ComputedRef<Permissions>;
    hasPermission: (permission: string) => boolean;
} {
    const page = usePage();

    const permissions = computed<Permissions>(() => {
        const value = page.props.auth?.permissions;

        return Array.isArray(value) ? value : null;
    });

    function hasPermission(permission: string): boolean {
        return canSee({ title: '', href: '#', permission }, permissions.value);
    }

    return { permissions, hasPermission };
}

export function useNavigation(): {
    sections: ComputedRef<NavSection[]>;
} {
    const { permissions } = usePermissions();

    const sections = computed<NavSection[]>(() =>
        navigationSections
            .map((section) => ({
                label: section.label,
                items: section.items.filter((item) =>
                    canSee(item, permissions.value),
                ),
            }))
            .filter((section) => section.items.length > 0),
    );

    return { sections };
}
