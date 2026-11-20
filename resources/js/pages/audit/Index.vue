<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ScrollText,
    Search,
    RotateCcw,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
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
import { formatDateTime } from '@/lib/format';
import { actorLabel } from '@/lib/labels';
import { filterValue, toSelectOptions } from '@/lib/options';
import type { AuditIndexProps, AuditLogRow } from '@/types/audit';
import type { SelectOption } from '@/types/options';
import type { StatusVariant } from '@/types/ui';

const props = defineProps<AuditIndexProps>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Auditoría', href: '/audit' }],
    },
});

const { filters, isFiltering, apply, reset } = useListFilters('/audit', {
    action: props.filters?.action ?? '',
    user_id: filterValue(props.filters?.user_id),
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

const logs = computed(() => props.logs ?? null);

const actionOptions = computed<SelectOption[]>(() =>
    toSelectOptions(props.options?.actions),
);

const userOptions = computed<SelectOption[]>(() =>
    toSelectOptions(props.options?.users, 'id'),
);

const actionOptionLabels = computed(
    () => new Map(actionOptions.value.map((option) => [option.value, option.label])),
);

const actionModel = computed({
    get: () => (filters.action === '' ? 'all' : filters.action),
    set: (value: string) => {
        filters.action = value === 'all' ? '' : value;
        apply();
    },
});

const userModel = computed({
    get: () => (filters.user_id === '' ? 'all' : filters.user_id),
    set: (value: string) => {
        filters.user_id = value === 'all' ? '' : value;
        apply();
    },
});

const hasFilters = computed(
    () =>
        filters.action !== '' ||
        filters.user_id !== '' ||
        filters.from !== '' ||
        filters.to !== '',
);

/** Acciones conocidas; el catálogo del backend tiene prioridad. */
const ACTION_LABELS: Record<string, string> = {
    created: 'Creó',
    updated: 'Actualizó',
    deleted: 'Eliminó',
    approved: 'Aprobó',
    rejected: 'Rechazó',
    calculated: 'Calculó',
    recalculated: 'Recalculó',
    reviewed: 'Revisó',
    closed: 'Cerró',
    generated: 'Generó',
    decided: 'Decidió',
    exported: 'Exportó',
    tested: 'Probó configuración',
    login: 'Inició sesión',
    logout: 'Cerró sesión',
};

const ACTION_VARIANTS: Record<string, StatusVariant> = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
    approved: 'success',
    rejected: 'danger',
    calculated: 'info',
    recalculated: 'info',
    reviewed: 'warning',
    closed: 'neutral',
};

function humanize(value: string): string {
    const spaced = value.replace(/[_-]+/g, ' ').trim();

    return spaced === ''
        ? '—'
        : spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

function actionKey(action: string): string {
    const parts = action.toLowerCase().split('.');

    return parts[parts.length - 1] ?? action.toLowerCase();
}

function actionLabel(action: string): string {
    return (
        actionOptionLabels.value.get(action) ??
        ACTION_LABELS[action.toLowerCase()] ??
        ACTION_LABELS[actionKey(action)] ??
        humanize(actionKey(action))
    );
}

function actionVariant(action: string): StatusVariant {
    return (
        ACTION_VARIANTS[action.toLowerCase()] ??
        ACTION_VARIANTS[actionKey(action)] ??
        'neutral'
    );
}

/** Tipos auditables del backend traducidos a lenguaje de negocio. */
const ENTITY_LABELS: Record<string, string> = {
    Employee: 'Colaborador',
    EmployeeCompensation: 'Compensación',
    User: 'Usuario',
    Role: 'Rol',
    Branch: 'Sucursal',
    BranchSchedule: 'Horario',
    AttendanceEvent: 'Marcaje',
    AttendanceIncident: 'Incidencia',
    VacationRequest: 'Solicitud de vacaciones',
    VacationPeriod: 'Periodo de vacaciones',
    Holiday: 'Festivo',
    PayrollPeriod: 'Periodo de nómina',
    PayrollRun: 'Corrida de nómina',
    PayrollItem: 'Recibo de nómina',
    PayrollConcept: 'Concepto de nómina',
    EmployeePayrollAdjustment: 'Ajuste de nómina',
    AwsUsageRecord: 'Consumo AWS',
    BiometricEnrollment: 'Registro biométrico',
};

function entityLabel(record: AuditLogRow): string {
    if (!record.auditable_type) {
        return '—';
    }

    const segments = record.auditable_type.split('\\');
    const className = segments[segments.length - 1] ?? record.auditable_type;
    const label = ENTITY_LABELS[className] ?? humanize(className);

    return record.auditable_id === null || record.auditable_id === undefined
        ? label
        : `${label} #${record.auditable_id}`;
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Auditoría" />

        <PageHeader
            title="Auditoría"
            description="Registro de acciones administrativas: quién, qué, sobre qué entidad y desde qué IP. No se muestran payloads con datos sensibles (PIN, biometría ni secretos)."
        >
            <template #actions>
                <StatusBadge variant="neutral">
                    <ShieldCheck class="size-3.5" aria-hidden="true" />
                    Solo metadatos
                </StatusBadge>
            </template>
        </PageHeader>

        <FlashAlert />

        <DataTable
            :empty="(logs?.data.length ?? 0) === 0"
            :loading="isFiltering"
            caption="Bitácora de auditoría"
        >
            <template #header>
                <form
                    class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between"
                    aria-label="Filtros de auditoría"
                    @submit.prevent="apply"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
                    >
                        <div class="grid gap-1.5">
                            <Label
                                for="audit-action"
                                class="text-xs text-muted-foreground"
                                >Acción</Label
                            >
                            <Select v-model="actionModel">
                                <SelectTrigger
                                    id="audit-action"
                                    class="w-full sm:w-52"
                                >
                                    <SelectValue placeholder="Todas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas</SelectItem>
                                    <SelectItem
                                        v-for="option in actionOptions"
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
                                for="audit-user"
                                class="text-xs text-muted-foreground"
                                >Usuario</Label
                            >
                            <Select v-model="userModel">
                                <SelectTrigger
                                    id="audit-user"
                                    class="w-full sm:w-52"
                                >
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem
                                        v-for="option in userOptions"
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
                                for="audit-from"
                                class="text-xs text-muted-foreground"
                                >Desde</Label
                            >
                            <Input
                                id="audit-from"
                                v-model="filters.from"
                                type="date"
                                class="sm:w-40"
                                @change="apply"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="audit-to"
                                class="text-xs text-muted-foreground"
                                >Hasta</Label
                            >
                            <Input
                                id="audit-to"
                                v-model="filters.to"
                                type="date"
                                class="sm:w-40"
                                @change="apply"
                            />
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
            </template>

            <template #body>
                <thead
                    class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th scope="col" class="px-3 py-2.5">Fecha</th>
                        <th scope="col" class="px-3 py-2.5">Usuario</th>
                        <th scope="col" class="px-3 py-2.5">Acción</th>
                        <th scope="col" class="px-3 py-2.5">Entidad afectada</th>
                        <th scope="col" class="px-3 py-2.5">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="record in logs?.data ?? []"
                        :key="record.id"
                        class="border-t border-border align-middle hover:bg-muted/30"
                    >
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ formatDateTime(record.created_at) }}
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="block max-w-56 truncate">
                                {{ actorLabel(record.user) }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge :variant="actionVariant(record.action)">
                                {{ actionLabel(record.action) }}
                            </StatusBadge>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="text-foreground">{{ entityLabel(record) }}</span>
                        </td>
                        <td class="px-3 py-2.5 font-mono text-xs whitespace-nowrap text-muted-foreground">
                            {{ record.ip_address ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </template>

            <template #empty>
                <EmptyState
                    :icon="ScrollText"
                    title="Sin registros de auditoría"
                    :description="
                        hasFilters
                            ? 'No hay acciones con los filtros aplicados. Ajusta la acción, el usuario o el rango de fechas.'
                            : 'Todavía no se han registrado acciones administrativas.'
                    "
                />
            </template>

            <Pagination
                v-if="logs"
                :links="logs.links"
                :meta="logs.meta ?? null"
            />
        </DataTable>
    </div>
</template>
