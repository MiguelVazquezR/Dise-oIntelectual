<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    CalendarCheck,
    CalendarRange,
    ClipboardClock,
    Clock,
    Info,
    RotateCcw,
    Timer,
    TriangleAlert,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useListFilters } from '@/composables/useListFilters';
import { usePermissions } from '@/composables/useNavigation';
import { formatDate, formatMinutesAsHours, formatMoney } from '@/lib/format';
import {
    incidentStatusLabel,
    incidentStatusVariant,
    incidentTypeLabel,
} from '@/lib/labels';
import { filterValue, toSelectOptions } from '@/lib/options';
import type {
    DashboardBranchRef,
    DashboardEmployeeRef,
    DashboardIncident,
    DashboardPayrollPeriod,
    DashboardPermissions,
    DashboardProps,
} from '@/types/dashboard';
import type { SelectOption } from '@/types/options';
import type { StatusVariant } from '@/types/ui';

const props = defineProps<DashboardProps>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
    },
});

const { filters, isFiltering, apply, reset } = useListFilters('/dashboard', {
    period_id: filterValue(props.filters?.period_id),
    branch_id: filterValue(props.filters?.branch_id),
});

const { hasPermission } = usePermissions();

/**
 * El backend oculta los enlaces; si `can` aún no llega se usa el permiso
 * compartido `auth.permissions` (y si tampoco existe, se muestra).
 */
function allowed(
    key: keyof DashboardPermissions,
    permission: string,
): boolean {
    const flag = props.can?.[key];

    return typeof flag === 'boolean' ? flag : hasPermission(permission);
}

const canSeePayroll = computed(() => allowed('payroll', 'payroll.view'));
const canSeeIncidents = computed(() =>
    allowed('incidents', 'incidents.view'),
);

const branchOptions = computed<SelectOption[]>(() =>
    toSelectOptions(props.options?.branches),
);

function periodLabel(period: DashboardPayrollPeriod | null | undefined): string {
    if (!period) {
        return 'Periodo actual';
    }

    if (period.label) {
        return period.label;
    }

    if (period.start_date && period.end_date) {
        return `${formatDate(period.start_date)} – ${formatDate(period.end_date)}`;
    }

    return period.start_date ? formatDate(period.start_date) : 'Periodo actual';
}

/**
 * §7.4 no incluye un catálogo de periodos en `options`; mientras el backend
 * lo agrega se ofrece el periodo de pre-nómina que viaja en `payroll.period`.
 */
const periodOptions = computed<SelectOption[]>(() => {
    const options = toSelectOptions(props.options?.periods);

    if (options.length > 0) {
        return options;
    }

    const period = props.payroll?.period;

    if (period && period.id !== null && period.id !== undefined) {
        return [{ value: String(period.id), label: periodLabel(period) }];
    }

    return [];
});

const periodModel = computed({
    get: () => (filters.period_id === '' ? 'all' : filters.period_id),
    set: (value: string) => {
        filters.period_id = value === 'all' ? '' : value;
        apply();
    },
});

const branchModel = computed({
    get: () => (filters.branch_id === '' ? 'all' : filters.branch_id),
    set: (value: string) => {
        filters.branch_id = value === 'all' ? '' : value;
        apply();
    },
});

const hasFilters = computed(
    () => filters.period_id !== '' || filters.branch_id !== '',
);

/* Indicadores */
const metrics = computed(() => props.metrics ?? null);

const metricsAvailable = computed(() => metrics.value !== null);

function countValue(value: number | string | null | undefined): string | number {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return value;
}

/* Comparativo por sucursal */
const branchRows = computed(() => props.by_branch ?? []);

function toNumber(value: number | string | null | undefined): number {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    const parsed =
        typeof value === 'number'
            ? value
            : Number.parseFloat(String(value).replace(/,/g, ''));

    return Number.isFinite(parsed) ? parsed : 0;
}

const branchMax = computed(() =>
    branchRows.value.reduce(
        (max, row) =>
            Math.max(
                max,
                toNumber(row.late_count),
                toNumber(row.absences),
                toNumber(row.employees),
            ),
        0,
    ),
);

function barWidth(
    value: number | string | null | undefined,
    max: number,
): string {
    const amount = toNumber(value);

    if (amount <= 0 || max <= 0) {
        return '0%';
    }

    return `${Math.max(2, Math.round((amount / max) * 100))}%`;
}

function branchLabel(branch: DashboardBranchRef | null | undefined): string {
    return branch?.name?.trim() || (branch?.id ? `Sucursal #${branch.id}` : '—');
}

function employeeLabel(employee: DashboardEmployeeRef | null | undefined): string {
    const name = employee?.full_name?.trim() || employee?.name?.trim();

    if (name) {
        return name;
    }

    return employee?.id ? `Colaborador #${employee.id}` : 'Colaborador sin nombre';
}

/* Tendencia de los últimos días */
const trendPoints = computed(() => props.trend ?? []);

const trendMax = computed(() =>
    trendPoints.value.reduce(
        (max, point) =>
            Math.max(max, toNumber(point.late_count), toNumber(point.absences)),
        0,
    ),
);

function barHeight(
    value: number | string | null | undefined,
    max: number,
): string {
    const amount = toNumber(value);

    if (amount <= 0 || max <= 0) {
        return '0%';
    }

    return `${Math.max(6, Math.round((amount / max) * 100))}%`;
}

function dayNumber(date: string): string {
    if (/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        return String(Number(date.slice(8, 10)));
    }

    return '';
}

function trendLabel(date: string, late: number, absences: number): string {
    const lateText = late === 1 ? '1 retardo' : `${late} retardos`;
    const absenceText = absences === 1 ? '1 falta' : `${absences} faltas`;

    return `${formatDate(date)}: ${lateText}, ${absenceText}`;
}

const trendFirst = computed(() => trendPoints.value.at(0) ?? null);
const trendLast = computed(() => trendPoints.value.at(-1) ?? null);

/* Pre-nómina */
const payroll = computed(() => props.payroll ?? null);

const payrollStatus = computed(
    () => payroll.value?.status ?? metrics.value?.payroll_status ?? null,
);

const PAYROLL_STATUSES: Record<string, { label: string; variant: StatusVariant }> =
    {
        open: { label: 'Abierto', variant: 'neutral' },
        calculated: { label: 'Calculado', variant: 'info' },
        reviewed: { label: 'Revisado', variant: 'warning' },
        closed: { label: 'Cerrado', variant: 'success' },
    };

function payrollStatusLabel(status: string | null): string {
    return (status ? PAYROLL_STATUSES[status]?.label : null) ?? 'Sin periodo';
}

function payrollStatusVariant(status: string | null): StatusVariant {
    return (status ? PAYROLL_STATUSES[status]?.variant : null) ?? 'neutral';
}

/* Incidencias recientes */
const recentIncidents = computed<DashboardIncident[]>(
    () => props.recent_incidents ?? [],
);

function incidentRange(incident: DashboardIncident): string {
    if (!incident.start_date) {
        return 'Sin fecha';
    }

    if (!incident.end_date || incident.end_date === incident.start_date) {
        return formatDate(incident.start_date);
    }

    return `${formatDate(incident.start_date)} – ${formatDate(incident.end_date)}`;
}
</script>

<template>
    <div
        class="flex flex-1 flex-col gap-6 p-4 md:p-6"
        :aria-busy="isFiltering"
    >
        <Head title="Dashboard" />

        <PageHeader
            title="Dashboard"
            description="Resumen de asistencia y pre-nómina del periodo y la sucursal seleccionados. Los cálculos los determina el backend."
        >
            <template #actions>
                <Button
                    v-if="canSeePayroll"
                    variant="outline"
                    as-child
                >
                    <Link href="/payroll/periods">
                        <CalendarRange aria-hidden="true" />
                        Periodos
                    </Link>
                </Button>
                <Button
                    v-if="canSeeIncidents"
                    variant="outline"
                    as-child
                >
                    <Link href="/incidents">
                        <TriangleAlert aria-hidden="true" />
                        Incidencias
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form
            class="flex flex-col gap-3 rounded-lg border border-border bg-card p-3 sm:flex-row sm:flex-wrap sm:items-end"
            aria-label="Filtros del dashboard"
            @submit.prevent="apply"
        >
            <div class="grid gap-1.5">
                <Label for="dashboard-period" class="text-xs text-muted-foreground"
                    >Periodo</Label
                >
                <Select v-model="periodModel">
                    <SelectTrigger id="dashboard-period" class="w-full sm:w-64">
                        <SelectValue placeholder="Periodo actual" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Periodo actual</SelectItem>
                        <SelectItem
                            v-for="option in periodOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <Label for="dashboard-branch" class="text-xs text-muted-foreground"
                    >Sucursal</Label
                >
                <Select v-model="branchModel">
                    <SelectTrigger id="dashboard-branch" class="w-full sm:w-56">
                        <SelectValue placeholder="Todas" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas</SelectItem>
                        <SelectItem
                            v-for="option in branchOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex items-center gap-2 sm:ml-auto">
                <Spinner v-if="isFiltering" class="text-muted-foreground" />
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="!hasFilters"
                    @click="reset"
                >
                    <RotateCcw aria-hidden="true" />
                    Limpiar
                </Button>
                <Button type="submit" size="sm">
                    Aplicar filtros
                </Button>
            </div>
        </form>

        <section class="space-y-3" aria-labelledby="dashboard-metrics">
            <h2
                id="dashboard-metrics"
                class="text-sm font-medium text-muted-foreground"
            >
                Indicadores del periodo
            </h2>

            <div
                v-if="metricsAvailable"
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6"
            >
                <StatCard
                    label="Colaboradores activos"
                    :value="countValue(metrics?.active_employees)"
                    :icon="Users"
                    hint="Con registro vigente"
                />
                <StatCard
                    label="Retardos"
                    :value="countValue(metrics?.late_count)"
                    :icon="Timer"
                    hint="Marcajes fuera de tolerancia"
                />
                <StatCard
                    label="Faltas justificadas"
                    :value="countValue(metrics?.justified_absences)"
                    :icon="CalendarCheck"
                    hint="Con incidencia aprobada"
                />
                <StatCard
                    label="Faltas injustificadas"
                    :value="countValue(metrics?.unjustified_absences)"
                    :icon="TriangleAlert"
                    hint="Sin justificación"
                />
                <StatCard
                    label="Horas extra"
                    :value="formatMinutesAsHours(metrics?.overtime_minutes ?? null)"
                    :icon="Clock"
                    hint="Suma del periodo"
                />
                <StatCard
                    label="Incidencias"
                    :value="countValue(metrics?.incidents_count)"
                    :icon="ClipboardClock"
                    hint="Registradas en el periodo"
                />
            </div>

            <EmptyState
                v-else
                :icon="Info"
                title="Sin indicadores del periodo"
                description="El backend todavía no devuelve métricas para los filtros aplicados. Ajusta el periodo o la sucursal."
            />
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <section
                class="rounded-lg border border-border bg-card p-4 lg:col-span-2"
                aria-labelledby="dashboard-branches"
            >
                <h2 id="dashboard-branches" class="text-base font-semibold">
                    Comparativo por sucursal
                </h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Barras proporcionales al máximo del comparativo; el valor
                    exacto aparece junto a cada barra.
                </p>

                <div
                    v-if="branchRows.length > 0"
                    class="mt-4 overflow-x-auto"
                >
                    <table class="w-full text-sm">
                        <caption class="sr-only">
                            Comparativo por sucursal del periodo seleccionado
                        </caption>
                        <thead
                            class="text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            <tr class="border-b border-border">
                                <th scope="col" class="px-2 py-2">Sucursal</th>
                                <th scope="col" class="px-2 py-2 text-right">
                                    Colaboradores
                                </th>
                                <th scope="col" class="px-2 py-2">
                                    Retardos
                                </th>
                                <th scope="col" class="px-2 py-2">
                                    Faltas
                                </th>
                                <th scope="col" class="px-2 py-2 text-right">
                                    Horas extra
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in branchRows"
                                :key="row.branch?.id ?? `${branchLabel(row.branch)}-${index}`"
                                class="border-b border-border/60 last:border-0"
                            >
                                <th
                                    scope="row"
                                    class="px-2 py-2.5 text-left font-medium text-foreground"
                                >
                                    {{ branchLabel(row.branch) }}
                                </th>
                                <td
                                    class="px-2 py-2.5 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ countValue(row.employees) }}
                                </td>
                                <td class="px-2 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="min-w-16 flex-1"
                                            role="img"
                                            :aria-label="`${branchLabel(row.branch)}: ${countValue(row.late_count)} retardos`"
                                            :title="`${branchLabel(row.branch)}: ${countValue(row.late_count)} retardos`"
                                        >
                                            <div
                                                class="h-2 overflow-hidden rounded-full bg-muted"
                                            >
                                                <div
                                                    class="h-full rounded-full bg-primary"
                                                    :style="{
                                                        width: barWidth(
                                                            row.late_count,
                                                            branchMax,
                                                        ),
                                                    }"
                                                />
                                            </div>
                                        </div>
                                        <span
                                            class="w-8 text-right text-xs tabular-nums"
                                            aria-hidden="true"
                                        >
                                            {{ countValue(row.late_count) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-2 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="min-w-16 flex-1"
                                            role="img"
                                            :aria-label="`${branchLabel(row.branch)}: ${countValue(row.absences)} faltas`"
                                            :title="`${branchLabel(row.branch)}: ${countValue(row.absences)} faltas`"
                                        >
                                            <div
                                                class="h-2 overflow-hidden rounded-full bg-muted"
                                            >
                                                <div
                                                    class="h-full rounded-full bg-destructive"
                                                    :style="{
                                                        width: barWidth(
                                                            row.absences,
                                                            branchMax,
                                                        ),
                                                    }"
                                                />
                                            </div>
                                        </div>
                                        <span
                                            class="w-8 text-right text-xs tabular-nums"
                                            aria-hidden="true"
                                        >
                                            {{ countValue(row.absences) }}
                                        </span>
                                    </div>
                                </td>
                                <td
                                    class="px-2 py-2.5 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ formatMinutesAsHours(row.overtime_minutes) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState
                    v-else
                    class="mt-4 border-0 bg-transparent"
                    :icon="Building2"
                    title="Sin datos por sucursal"
                    description="No hay sucursales con actividad en el periodo seleccionado."
                />
            </section>

            <section
                class="rounded-lg border border-border bg-card p-4"
                aria-labelledby="dashboard-payroll"
            >
                <div class="flex items-start justify-between gap-2">
                    <h2 id="dashboard-payroll" class="text-base font-semibold">
                        Pre-nómina
                    </h2>
                    <StatusBadge
                        v-if="payrollStatus"
                        :variant="payrollStatusVariant(payrollStatus)"
                    >
                        {{ payrollStatusLabel(payrollStatus) }}
                    </StatusBadge>
                </div>

                <template v-if="payroll">
                    <p class="mt-1 text-sm break-words text-muted-foreground">
                        {{ periodLabel(payroll.period) }}
                    </p>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-muted-foreground">Percepciones</dt>
                            <dd class="font-medium tabular-nums">
                                {{
                                    formatMoney(payroll.totals?.earnings ?? null)
                                }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-muted-foreground">Deducciones</dt>
                            <dd class="font-medium tabular-nums">
                                {{
                                    formatMoney(
                                        payroll.totals?.deductions ?? null,
                                    )
                                }}
                            </dd>
                        </div>
                        <div
                            class="flex items-baseline justify-between gap-2 border-t border-border pt-2"
                        >
                            <dt class="font-medium text-foreground">Neto</dt>
                            <dd class="text-base font-semibold tabular-nums">
                                {{ formatMoney(payroll.totals?.net ?? null) }}
                            </dd>
                        </div>
                    </dl>

                    <p class="mt-3 flex items-start gap-1.5 text-xs text-muted-foreground">
                        <Info class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                        Cifras preliminares de control interno; no constituyen
                        CFDI.
                    </p>

                    <Button
                        v-if="canSeePayroll"
                        variant="outline"
                        size="sm"
                        class="mt-4 w-full"
                        as-child
                    >
                        <Link href="/payroll/periods">
                            <Wallet aria-hidden="true" />
                            Ver periodos
                            <ArrowRight aria-hidden="true" />
                        </Link>
                    </Button>
                </template>

                <EmptyState
                    v-else
                    class="mt-4 border-0 bg-transparent px-2 py-6"
                    :icon="Wallet"
                    title="Sin periodo de pre-nómina"
                    description="Aún no hay un periodo generado para los filtros aplicados."
                >
                    <template v-if="canSeePayroll" #action>
                        <Button variant="outline" size="sm" as-child>
                            <Link href="/payroll/periods">
                                <CalendarRange aria-hidden="true" />
                                Ir a periodos
                            </Link>
                        </Button>
                    </template>
                </EmptyState>
            </section>
        </div>

        <section
            class="rounded-lg border border-border bg-card p-4"
            aria-labelledby="dashboard-trend"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 id="dashboard-trend" class="text-base font-semibold">
                        Tendencia de los últimos 14 días
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Retardos y faltas por día. Pasa el cursor o enfoca una
                        barra para ver el detalle.
                    </p>
                </div>
                <ul class="flex flex-wrap items-center gap-3 text-xs">
                    <li class="flex items-center gap-1.5">
                        <span
                            class="size-2.5 rounded-sm bg-primary"
                            aria-hidden="true"
                        />
                        <span class="text-muted-foreground">Retardos</span>
                    </li>
                    <li class="flex items-center gap-1.5">
                        <span
                            class="size-2.5 rounded-sm bg-destructive"
                            aria-hidden="true"
                        />
                        <span class="text-muted-foreground">Faltas</span>
                    </li>
                </ul>
            </div>

            <div v-if="trendPoints.length > 0" class="mt-4">
                <div class="flex items-end gap-1 sm:gap-1.5">
                    <div
                        v-for="point in trendPoints"
                        :key="point.date"
                        class="flex min-w-0 flex-1 flex-col items-center gap-1"
                    >
                        <div
                            class="flex h-24 w-full items-end justify-center gap-0.5 rounded-sm focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                            role="img"
                            :aria-label="
                                trendLabel(
                                    point.date,
                                    toNumber(point.late_count),
                                    toNumber(point.absences),
                                )
                            "
                            :title="
                                trendLabel(
                                    point.date,
                                    toNumber(point.late_count),
                                    toNumber(point.absences),
                                )
                            "
                            tabindex="0"
                        >
                            <div
                                class="w-1/2 max-w-2.5 rounded-t-sm bg-primary"
                                :style="{
                                    height: barHeight(
                                        point.late_count,
                                        trendMax,
                                    ),
                                }"
                            />
                            <div
                                class="w-1/2 max-w-2.5 rounded-t-sm bg-destructive"
                                :style="{
                                    height: barHeight(
                                        point.absences,
                                        trendMax,
                                    ),
                                }"
                            />
                        </div>
                        <span
                            class="text-[10px] text-muted-foreground tabular-nums"
                            aria-hidden="true"
                        >
                            {{ dayNumber(point.date) }}
                        </span>
                    </div>
                </div>

                <div
                    class="mt-1 flex justify-between text-[10px] text-muted-foreground"
                    aria-hidden="true"
                >
                    <span>{{ trendFirst ? formatDate(trendFirst.date) : '' }}</span>
                    <span>{{ trendLast ? formatDate(trendLast.date) : '' }}</span>
                </div>
            </div>

            <EmptyState
                v-else
                class="mt-4 border-0 bg-transparent"
                :icon="CalendarRange"
                title="Sin tendencia disponible"
                description="No hay marcajes ni faltas registrados en los últimos días del rango."
            />
        </section>

        <section
            class="rounded-lg border border-border bg-card"
            aria-labelledby="dashboard-incidents"
        >
            <div
                class="flex flex-wrap items-start justify-between gap-2 border-b border-border p-4"
            >
                <div>
                    <h2
                        id="dashboard-incidents"
                        class="text-base font-semibold"
                    >
                        Incidencias recientes
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Últimos movimientos registrados. La aprobación se
                        realiza en el módulo de incidencias.
                    </p>
                </div>
                <Button
                    v-if="canSeeIncidents"
                    variant="ghost"
                    size="sm"
                    as-child
                >
                    <Link href="/incidents">
                        Ver todas
                        <ArrowRight aria-hidden="true" />
                    </Link>
                </Button>
            </div>

            <ul
                v-if="recentIncidents.length > 0"
                class="divide-y divide-border"
            >
                <li
                    v-for="incident in recentIncidents"
                    :key="incident.id"
                    class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-foreground">
                            {{ employeeLabel(incident.employee) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ incidentTypeLabel(incident.type) }} ·
                            {{ incidentRange(incident) }}
                            <template v-if="incident.branch">
                                · {{ branchLabel(incident.branch) }}
                            </template>
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <StatusBadge
                            v-if="incident.status"
                            :variant="incidentStatusVariant(incident.status)"
                        >
                            {{ incidentStatusLabel(incident.status) }}
                        </StatusBadge>
                        <Button
                            v-if="canSeeIncidents"
                            variant="ghost"
                            size="sm"
                            as-child
                        >
                            <Link
                                href="/incidents"
                                :aria-label="`Ver incidencias de ${employeeLabel(incident.employee)}`"
                            >
                                Ver
                            </Link>
                        </Button>
                    </div>
                </li>
            </ul>

            <div v-else class="p-4">
                <EmptyState
                    class="border-0 bg-transparent"
                    :icon="TriangleAlert"
                    title="Sin incidencias recientes"
                    description="No hay incidencias registradas en el periodo seleccionado."
                />
            </div>
        </section>
    </div>
</template>
