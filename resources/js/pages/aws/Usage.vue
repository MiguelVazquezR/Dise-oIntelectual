<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CloudCog,
    Info,
    Percent,
    RotateCcw,
    ScanFace,
    Search,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { formatDateTime, formatMoney } from '@/lib/format';
import { filterValue, toSelectOptions } from '@/lib/options';
import type {
    AwsBranchRef,
    AwsEmployeeRef,
    AwsUsageProps,
    AwsUsageRecord,
} from '@/types/aws';
import type { SelectOption } from '@/types/options';
import type { StatusVariant } from '@/types/ui';

const props = defineProps<AwsUsageProps>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Consumo AWS', href: '/aws/usage' }],
    },
});

/** Etiquetas de presentación; el valor y sus reglas viven en el backend. */
const OPERATION_LABELS: Record<string, string> = {
    index_faces: 'Registro de rostro',
    search_faces_by_image: 'Búsqueda por imagen',
    detect_faces: 'Detección de rostro',
    delete_faces: 'Baja de rostro',
};

const SOURCE_LABELS: Record<string, string> = {
    demo: 'Simulación',
    real: 'AWS real',
};

function humanize(value: string): string {
    const spaced = value.replace(/[_-]+/g, ' ').trim();

    return spaced === ''
        ? '—'
        : spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

function operationLabel(operation: string): string {
    return OPERATION_LABELS[operation] ?? humanize(operation);
}

function sourceLabel(source: string): string {
    return SOURCE_LABELS[source] ?? humanize(source);
}

const { filters, isFiltering, apply, reset } = useListFilters('/aws/usage', {
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
    branch_id: filterValue(props.filters?.branch_id),
    operation: props.filters?.operation ?? '',
    source: props.filters?.source ?? '',
});

const branchOptions = computed<SelectOption[]>(() =>
    toSelectOptions(props.options?.branches),
);

const operationOptions = computed<SelectOption[]>(() => {
    const options = toSelectOptions(props.options?.operations);

    if (options.length > 0) {
        return options;
    }

    return Object.entries(OPERATION_LABELS).map(([value, label]) => ({
        value,
        label,
    }));
});

const sourceOptions = computed<SelectOption[]>(() => {
    const options = toSelectOptions(props.options?.sources);

    if (options.length > 0) {
        return options;
    }

    return Object.entries(SOURCE_LABELS).map(([value, label]) => ({
        value,
        label,
    }));
});

const branchModel = computed({
    get: () => (filters.branch_id === '' ? 'all' : filters.branch_id),
    set: (value: string) => {
        filters.branch_id = value === 'all' ? '' : value;
        apply();
    },
});

const operationModel = computed({
    get: () => (filters.operation === '' ? 'all' : filters.operation),
    set: (value: string) => {
        filters.operation = value === 'all' ? '' : value;
        apply();
    },
});

const sourceModel = computed({
    get: () => (filters.source === '' ? 'all' : filters.source),
    set: (value: string) => {
        filters.source = value === 'all' ? '' : value;
        apply();
    },
});

const hasFilters = computed(
    () =>
        filters.from !== '' ||
        filters.to !== '' ||
        filters.branch_id !== '' ||
        filters.operation !== '' ||
        filters.source !== '',
);

/* Totales */
const totals = computed(() => props.totals ?? null);

const records = computed(() => props.records ?? null);

const pricing = computed(() => props.pricing ?? null);

const pricingCurrency = computed(() => pricing.value?.currency ?? 'MXN');

const pricingNote = computed(
    () =>
        pricing.value?.note ??
        'Cargo interno de demostración; no es la tarifa oficial de AWS.',
);

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

function formatCount(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return new Intl.NumberFormat('es-MX').format(toNumber(value));
}

/** Costo promedio por rostro a partir de los totales del backend. */
const costPerFace = computed<number | null>(() => {
    const faces = toNumber(totals.value?.faces);

    if (faces <= 0) {
        return null;
    }

    return toNumber(totals.value?.amount_mxn) / faces;
});

const operationTotals = computed(() => totals.value?.by_operation ?? []);

const branchTotals = computed(() => totals.value?.by_branch ?? []);

function branchLabel(branch: AwsBranchRef | null | undefined): string {
    return branch?.name?.trim() || (branch?.id ? `Sucursal #${branch.id}` : '—');
}

function employeeLabel(employee: AwsEmployeeRef | null | undefined): string {
    const name = employee?.full_name?.trim() || employee?.name?.trim();

    if (name) {
        return name;
    }

    return employee?.id ? `Colaborador #${employee.id}` : '—';
}

function statusLabel(status: string): string {
    if (status === 'succeeded') {
        return 'Exitoso';
    }

    return status === 'failed' ? 'Fallido' : humanize(status);
}

function statusVariant(status: string): StatusVariant {
    if (status === 'succeeded') {
        return 'success';
    }

    return status === 'failed' ? 'danger' : 'neutral';
}

function isDemo(record: AwsUsageRecord): boolean {
    return record.source === 'demo';
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Consumo AWS" />

        <PageHeader
            title="Consumo AWS"
            description="Rostros analizados por el checador biométrico e importe interno acumulado. En modo demostración el consumo está etiquetado como simulado; el backend calcula los importes."
        >
            <template #actions>
                <StatusBadge variant="info">
                    <CloudCog class="size-3.5" aria-hidden="true" />
                    AWS Rekognition
                </StatusBadge>
            </template>
        </PageHeader>

        <FlashAlert />

        <p
            role="note"
            class="flex items-start gap-2 rounded-lg border border-sky-700/30 bg-sky-50 px-3 py-2 text-sm text-sky-900 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-100"
        >
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span class="text-pretty">
                <strong class="font-semibold">Tarifa interna:</strong>
                {{ formatMoney(pricing?.price_per_1000 ?? null, pricingCurrency) }}
                {{ pricingCurrency }} por cada 1,000 rostros.
                {{ pricingNote }}
            </span>
        </p>

        <form
            class="flex flex-col gap-3 rounded-lg border border-border bg-card p-3 xl:flex-row xl:items-end xl:justify-between"
            aria-label="Filtros del consumo AWS"
            @submit.prevent="apply"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="grid gap-1.5">
                    <Label for="aws-usage-from" class="text-xs text-muted-foreground"
                        >Desde</Label
                    >
                    <Input
                        id="aws-usage-from"
                        v-model="filters.from"
                        type="date"
                        class="sm:w-40"
                        @change="apply"
                    />
                </div>

                <div class="grid gap-1.5">
                    <Label for="aws-usage-to" class="text-xs text-muted-foreground"
                        >Hasta</Label
                    >
                    <Input
                        id="aws-usage-to"
                        v-model="filters.to"
                        type="date"
                        class="sm:w-40"
                        @change="apply"
                    />
                </div>

                <div class="grid gap-1.5">
                    <Label
                        for="aws-usage-branch"
                        class="text-xs text-muted-foreground"
                        >Sucursal</Label
                    >
                    <Select v-model="branchModel">
                        <SelectTrigger
                            id="aws-usage-branch"
                            class="w-full sm:w-48"
                        >
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

                <div class="grid gap-1.5">
                    <Label
                        for="aws-usage-operation"
                        class="text-xs text-muted-foreground"
                        >Operación</Label
                    >
                    <Select v-model="operationModel">
                        <SelectTrigger
                            id="aws-usage-operation"
                            class="w-full sm:w-52"
                        >
                            <SelectValue placeholder="Todas" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todas</SelectItem>
                            <SelectItem
                                v-for="option in operationOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-1.5">
                    <Label
                        for="aws-usage-source"
                        class="text-xs text-muted-foreground"
                        >Origen</Label
                    >
                    <Select v-model="sourceModel">
                        <SelectTrigger
                            id="aws-usage-source"
                            class="w-full sm:w-44"
                        >
                            <SelectValue placeholder="Todos" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            <SelectItem
                                v-for="option in sourceOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="flex items-center gap-2">
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
                    <Search aria-hidden="true" />
                    Filtrar
                </Button>
            </div>
        </form>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <StatCard
                label="Rostros analizados"
                :value="formatCount(totals?.faces)"
                :icon="ScanFace"
                hint="Operaciones de reconocimiento del rango"
            />
            <StatCard
                label="Importe interno"
                :value="formatMoney(totals?.amount_mxn ?? null)"
                :icon="Wallet"
                hint="Cargo interno; no es la tarifa oficial de AWS"
            />
            <StatCard
                label="Costo por operación"
                :value="formatMoney(costPerFace)"
                :icon="Percent"
                hint="Promedio por rostro analizado"
            />
        </div>

        <div
            v-if="operationTotals.length > 0 || branchTotals.length > 0"
            class="grid gap-6 lg:grid-cols-2"
        >
            <section
                v-if="operationTotals.length > 0"
                class="rounded-lg border border-border bg-card p-4"
                aria-labelledby="aws-usage-by-operation"
            >
                <h2
                    id="aws-usage-by-operation"
                    class="text-base font-semibold"
                >
                    Consumo por operación
                </h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">
                            Rostros e importe por operación
                        </caption>
                        <thead
                            class="text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            <tr class="border-b border-border">
                                <th scope="col" class="px-2 py-2">Operación</th>
                                <th scope="col" class="px-2 py-2 text-right">
                                    Rostros
                                </th>
                                <th scope="col" class="px-2 py-2 text-right">
                                    Importe
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in operationTotals"
                                :key="row.operation"
                                class="border-b border-border/60 last:border-0"
                            >
                                <th
                                    scope="row"
                                    class="px-2 py-2 text-left font-medium text-foreground"
                                >
                                    {{ operationLabel(row.operation) }}
                                </th>
                                <td
                                    class="px-2 py-2 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ formatCount(row.faces) }}
                                </td>
                                <td
                                    class="px-2 py-2 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ formatMoney(row.amount_mxn) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-if="branchTotals.length > 0"
                class="rounded-lg border border-border bg-card p-4"
                aria-labelledby="aws-usage-by-branch"
            >
                <h2 id="aws-usage-by-branch" class="text-base font-semibold">
                    Consumo por sucursal
                </h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">
                            Rostros e importe por sucursal
                        </caption>
                        <thead
                            class="text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            <tr class="border-b border-border">
                                <th scope="col" class="px-2 py-2">Sucursal</th>
                                <th scope="col" class="px-2 py-2 text-right">
                                    Rostros
                                </th>
                                <th scope="col" class="px-2 py-2 text-right">
                                    Importe
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in branchTotals"
                                :key="row.branch?.id ?? `${branchLabel(row.branch)}-${index}`"
                                class="border-b border-border/60 last:border-0"
                            >
                                <th
                                    scope="row"
                                    class="px-2 py-2 text-left font-medium text-foreground"
                                >
                                    {{ branchLabel(row.branch) }}
                                </th>
                                <td
                                    class="px-2 py-2 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ formatCount(row.faces) }}
                                </td>
                                <td
                                    class="px-2 py-2 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ formatMoney(row.amount_mxn) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <DataTable
            :empty="(records?.data.length ?? 0) === 0"
            :loading="isFiltering"
            caption="Registros de consumo de AWS Rekognition"
        >
            <template #body>
                <thead
                    class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th scope="col" class="px-3 py-2.5">Fecha</th>
                        <th scope="col" class="px-3 py-2.5">Sucursal</th>
                        <th scope="col" class="px-3 py-2.5">Colaborador</th>
                        <th scope="col" class="px-3 py-2.5">Operación</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Cantidad
                        </th>
                        <th scope="col" class="px-3 py-2.5">Estado</th>
                        <th scope="col" class="px-3 py-2.5">Origen</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Importe
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="record in records?.data ?? []"
                        :key="record.id"
                        class="border-t border-border align-middle hover:bg-muted/30"
                    >
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ formatDateTime(record.occurred_at) }}
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ branchLabel(record.branch) }}
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="block max-w-56 truncate">
                                {{ employeeLabel(record.employee) }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ operationLabel(record.operation) }}
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ formatCount(record.quantity) }}
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge :variant="statusVariant(record.status)">
                                {{ statusLabel(record.status) }}
                            </StatusBadge>
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                :variant="isDemo(record) ? 'warning' : 'neutral'"
                                :title="
                                    isDemo(record)
                                        ? 'Consumo simulado: no se llamó a AWS'
                                        : 'Llamada real a AWS Rekognition'
                                "
                            >
                                {{ isDemo(record) ? 'DEMO' : sourceLabel(record.source) }}
                            </StatusBadge>
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ formatMoney(record.amount_mxn) }}
                        </td>
                    </tr>
                </tbody>
            </template>

            <template #empty>
                <EmptyState
                    :icon="ScanFace"
                    title="Sin consumo registrado"
                    :description="
                        hasFilters
                            ? 'No hay registros con los filtros aplicados. Ajusta el rango de fechas, la sucursal o el origen.'
                            : 'Todavía no se han registrado operaciones de reconocimiento facial. Mientras AWS no esté configurado, el checador opera con PIN.'
                    "
                >
                    <template #action>
                        <Button variant="outline" as-child>
                            <Link href="/settings/aws">
                                <CloudCog aria-hidden="true" />
                                Configuración de AWS
                            </Link>
                        </Button>
                    </template>
                </EmptyState>
            </template>

            <Pagination
                v-if="records"
                :links="records.links"
                :meta="records.meta ?? null"
            />
        </DataTable>
    </div>
</template>
