<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarDays,
    Check,
    Plane,
    Plus,
    RotateCcw,
    Search,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import DecisionDialog from '@/components/DecisionDialog.vue';
import EmployeeSearchSelect from '@/components/EmployeeSearchSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { useListFilters } from '@/composables/useListFilters';
import { formatDate, formatDateTime, formatDays } from '@/lib/format';
import {
    actorLabel,
    vacationPeriodStatusLabel,
    vacationPeriodStatusVariant,
    vacationRequestStatusLabel,
    vacationRequestStatusVariant,
} from '@/lib/labels';
import { toEmployeeOptions, toSelectOptions, filterValue } from '@/lib/options';
import type {
    VacationDecision,
    VacationDecisionFormData,
    VacationIndexProps,
    VacationRequestFormData,
    VacationRequestRow,
} from '@/types';

const props = defineProps<VacationIndexProps>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vacaciones', href: '/vacations' }],
    },
});

type Tab = 'periods' | 'requests';

const currentYear = new Date().getFullYear();

const activeTab = ref<Tab>('periods');

const { filters, isFiltering, apply } = useListFilters('/vacations', {
    year: filterValue(props.filters.year),
    branch_id: filterValue(props.filters.branch_id),
    employee_id: filterValue(props.filters.employee_id),
});

const branchOptions = computed(() => toSelectOptions(props.options?.branches));

const employeeOptions = computed(() =>
    toEmployeeOptions(props.options?.employees),
);

const yearOptions = computed(() => {
    const options = toSelectOptions(props.options?.years);

    return options.length > 0
        ? options
        : [{ value: String(currentYear), label: String(currentYear) }];
});

const yearModel = computed({
    get: () => filters.year,
    set: (value: string) => {
        filters.year = value;
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

const generateYear = computed(() =>
    filters.year === '' ? String(currentYear) : filters.year,
);

function setEmployeeFilter(value: string): void {
    filters.employee_id = value;
    apply();
}

/** El año siempre se consulta: solo se limpian sucursal y colaborador. */
function resetFilters(): void {
    filters.branch_id = '';
    filters.employee_id = '';
    apply();
}

/* Generar periodos por aniversario */
const isGenerating = ref(false);

function generatePeriods(): void {
    if (isGenerating.value) {
        return;
    }

    isGenerating.value = true;

    router.post(
        '/vacations/periods/generate',
        { year: generateYear.value },
        {
            preserveScroll: true,
            onFinish: () => {
                isGenerating.value = false;
            },
        },
    );
}

/* Alta de solicitud */
const requestFormOpen = ref(false);

const requestForm = useForm<VacationRequestFormData>({
    employee_id: '',
    start_date: '',
    end_date: '',
    notes: '',
});

const requestDateError = computed(() => {
    if (requestForm.start_date === '' || requestForm.end_date === '') {
        return null;
    }

    return requestForm.start_date > requestForm.end_date
        ? 'La fecha final no puede ser anterior a la inicial.'
        : null;
});

function openRequestForm(): void {
    requestForm.reset();
    requestForm.clearErrors();
    requestFormOpen.value = true;
}

function handleRequestFormOpen(open: boolean): void {
    requestFormOpen.value = open;

    if (!open) {
        requestForm.reset();
        requestForm.clearErrors();
    }
}

function submitRequest(): void {
    if (requestForm.processing || requestDateError.value !== null) {
        return;
    }

    requestForm.post('/vacations/requests', {
        preserveScroll: true,
        onSuccess: () => {
            handleRequestFormOpen(false);
        },
    });
}

/* Aprobar / rechazar solicitud */
const decisionOpen = ref(false);
const decisionTarget = ref<VacationRequestRow | null>(null);
const decisionKind = ref<VacationDecision>('approved');
const decisionNotes = ref('');

const decisionForm = useForm<VacationDecisionFormData>({
    decision: 'approved',
    notes: '',
});

const decisionTitle = computed(() =>
    decisionKind.value === 'approved'
        ? 'Aprobar solicitud de vacaciones'
        : 'Rechazar solicitud de vacaciones',
);

const decisionDescription = computed(() => {
    const target = decisionTarget.value;

    if (!target) {
        return '';
    }

    return `${target.employee.full_name} · ${formatDate(target.start_date)} — ${formatDate(target.end_date)} · ${formatDays(target.days)} días. El saldo se ajusta en el servidor.`;
});

function openDecision(row: VacationRequestRow, kind: VacationDecision): void {
    decisionTarget.value = row;
    decisionKind.value = kind;
    decisionNotes.value = '';
    decisionForm.clearErrors();
    decisionForm.defaults({ decision: kind, notes: '' });
    decisionForm.reset();
    decisionOpen.value = true;
}

function handleDecisionOpen(open: boolean): void {
    decisionOpen.value = open;

    if (!open) {
        decisionTarget.value = null;
        decisionNotes.value = '';
        decisionForm.clearErrors();
    }
}

function submitDecision(): void {
    const target = decisionTarget.value;

    if (!target || decisionForm.processing) {
        return;
    }

    decisionForm.decision = decisionKind.value;
    decisionForm.notes = decisionNotes.value;

    decisionForm.post(`/vacations/requests/${target.id}/decide`, {
        preserveScroll: true,
        onSuccess: () => {
            handleDecisionOpen(false);
        },
    });
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Vacaciones" />

        <PageHeader
            title="Vacaciones"
            description="Periodos por aniversario y solicitudes del personal. Los días, saldos y ajustes los calcula el backend."
        >
            <template #actions>
                <Button
                    v-if="props.can.manage"
                    variant="outline"
                    :disabled="isGenerating"
                    @click="generatePeriods"
                >
                    <Spinner v-if="isGenerating" />
                    <CalendarDays v-else aria-hidden="true" />
                    Generar periodos {{ generateYear }}
                </Button>
                <Button v-if="props.can.manage" @click="openRequestForm">
                    <Plus aria-hidden="true" />
                    Nueva solicitud
                </Button>
            </template>
        </PageHeader>

        <FlashAlert />

        <form
            class="flex flex-col gap-3 rounded-lg border border-border bg-card p-3 md:flex-row md:items-end md:justify-between"
            @submit.prevent="apply"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
            >
                <div class="grid gap-1.5">
                    <Label
                        for="vacation-year"
                        class="text-xs text-muted-foreground"
                        >Año</Label
                    >
                    <Select v-model="yearModel">
                        <SelectTrigger
                            id="vacation-year"
                            class="w-full sm:w-32"
                        >
                            <SelectValue placeholder="Selecciona el año" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in yearOptions"
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
                        for="vacation-branch"
                        class="text-xs text-muted-foreground"
                        >Sucursal</Label
                    >
                    <Select v-model="branchModel">
                        <SelectTrigger
                            id="vacation-branch"
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
                        for="vacation-employee-filter"
                        class="text-xs text-muted-foreground"
                        >Colaborador</Label
                    >
                    <div
                        v-if="employeeOptions.length > 0"
                        class="w-full sm:w-64"
                    >
                        <EmployeeSearchSelect
                            input-id="vacation-employee-filter"
                            :model-value="filters.employee_id"
                            :options="employeeOptions"
                            placeholder="Nombre o número"
                            @update:model-value="setEmployeeFilter"
                        />
                    </div>
                    <Input
                        v-else
                        id="vacation-employee-filter"
                        v-model="filters.employee_id"
                        type="number"
                        min="1"
                        inputmode="numeric"
                        placeholder="ID de colaborador"
                        class="sm:w-44"
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
                    @click="resetFilters"
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

        <p
            v-if="props.rules?.legal_notice"
            role="note"
            class="rounded-lg border border-sky-700/30 bg-sky-50 px-3 py-2 text-xs text-sky-900 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-100"
        >
            {{ props.rules.legal_notice }}
        </p>

        <div class="space-y-4">
            <div
                class="flex gap-1 border-b border-border"
                role="tablist"
                aria-label="Secciones de vacaciones"
            >
                <button
                    id="vacation-tab-periods"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'periods'"
                    aria-controls="vacation-panel-periods"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                    :class="
                        activeTab === 'periods'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'periods'"
                >
                    Periodos por aniversario
                </button>
                <button
                    id="vacation-tab-requests"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'requests'"
                    aria-controls="vacation-panel-requests"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                    :class="
                        activeTab === 'requests'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'requests'"
                >
                    Solicitudes
                </button>
            </div>

            <section
                v-show="activeTab === 'periods'"
                id="vacation-panel-periods"
                role="tabpanel"
                aria-labelledby="vacation-tab-periods"
                tabindex="0"
            >
                <DataTable
                    :empty="props.periods.data.length === 0"
                    :loading="isFiltering"
                    caption="Periodos de vacaciones por aniversario"
                >
                    <template #body>
                        <thead
                            class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            <tr>
                                <th scope="col" class="px-3 py-2.5">
                                    Colaborador
                                </th>
                                <th scope="col" class="px-3 py-2.5">
                                    Sucursal
                                </th>
                                <th scope="col" class="px-3 py-2.5">Periodo</th>
                                <th scope="col" class="px-3 py-2.5 text-right">
                                    Antigüedad
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-right">
                                    Otorgados
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-right">
                                    Tomados
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-right">
                                    Ajustes
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-right">
                                    Pendientes
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-right">
                                    Disponibles
                                </th>
                                <th scope="col" class="px-3 py-2.5">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="period in props.periods.data"
                                :key="period.id"
                                class="border-t border-border align-middle hover:bg-muted/30"
                            >
                                <td class="px-3 py-2.5">
                                    <span
                                        class="block font-medium text-foreground"
                                    >
                                        {{ period.employee.full_name }}
                                    </span>
                                    <span
                                        class="font-mono text-xs text-muted-foreground"
                                    >
                                        {{ period.employee.employee_number }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    {{ period.branch?.name ?? '—' }}
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    {{ formatDate(period.period_start) }}
                                    <span class="text-muted-foreground">→</span>
                                    {{ formatDate(period.period_end) }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ period.anniversary_years }} años
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ formatDays(period.days_granted) }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ formatDays(period.days_taken) }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ formatDays(period.days_adjustment) }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right font-medium whitespace-nowrap tabular-nums"
                                >
                                    {{ formatDays(period.days_pending) }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{
                                        formatDays(
                                            period.days_available ?? null,
                                        )
                                    }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <StatusBadge
                                        :variant="
                                            vacationPeriodStatusVariant(
                                                period.status,
                                            )
                                        "
                                    >
                                        {{
                                            vacationPeriodStatusLabel(
                                                period.status,
                                            )
                                        }}
                                    </StatusBadge>
                                </td>
                            </tr>
                        </tbody>
                    </template>

                    <template #empty>
                        <EmptyState
                            :icon="Plane"
                            title="Sin periodos de vacaciones"
                            :description="
                                props.can.manage
                                    ? 'Genera los periodos del año por aniversario para comenzar a registrar solicitudes.'
                                    : 'Aún no hay periodos generados para el filtro actual.'
                            "
                        >
                            <template v-if="props.can.manage" #action>
                                <Button
                                    variant="outline"
                                    :disabled="isGenerating"
                                    @click="generatePeriods"
                                >
                                    <CalendarDays aria-hidden="true" />
                                    Generar periodos {{ generateYear }}
                                </Button>
                            </template>
                        </EmptyState>
                    </template>

                    <Pagination
                        :links="props.periods.links"
                        :meta="props.periods.meta ?? null"
                    />
                </DataTable>
            </section>

            <section
                v-show="activeTab === 'requests'"
                id="vacation-panel-requests"
                role="tabpanel"
                aria-labelledby="vacation-tab-requests"
                tabindex="0"
            >
                <DataTable
                    :empty="props.requests.data.length === 0"
                    :loading="isFiltering"
                    caption="Solicitudes de vacaciones"
                >
                    <template #body>
                        <thead
                            class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            <tr>
                                <th scope="col" class="px-3 py-2.5">
                                    Colaborador
                                </th>
                                <th scope="col" class="px-3 py-2.5">
                                    Periodo solicitado
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-right">
                                    Días
                                </th>
                                <th scope="col" class="px-3 py-2.5">Estado</th>
                                <th scope="col" class="px-3 py-2.5">
                                    Solicitó
                                </th>
                                <th scope="col" class="px-3 py-2.5">
                                    Decisión
                                </th>
                                <th scope="col" class="px-3 py-2.5">Notas</th>
                                <th scope="col" class="px-3 py-2.5 text-right">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="request in props.requests.data"
                                :key="request.id"
                                class="border-t border-border align-middle hover:bg-muted/30"
                            >
                                <td class="px-3 py-2.5">
                                    <span
                                        class="block font-medium text-foreground"
                                    >
                                        {{ request.employee.full_name }}
                                    </span>
                                    <span
                                        class="font-mono text-xs text-muted-foreground"
                                    >
                                        {{ request.employee.employee_number }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    {{ formatDate(request.start_date) }}
                                    <span class="text-muted-foreground">→</span>
                                    {{ formatDate(request.end_date) }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ formatDays(request.days) }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <StatusBadge
                                        :variant="
                                            vacationRequestStatusVariant(
                                                request.status,
                                            )
                                        "
                                    >
                                        {{
                                            vacationRequestStatusLabel(
                                                request.status,
                                            )
                                        }}
                                    </StatusBadge>
                                </td>
                                <td class="max-w-[12rem] px-3 py-2.5">
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                    >
                                        {{ actorLabel(request.requested_by) }}
                                    </span>
                                </td>
                                <td class="max-w-[14rem] px-3 py-2.5">
                                    <span
                                        v-if="request.decided_at"
                                        class="block truncate text-xs text-muted-foreground"
                                        :title="`${actorLabel(request.decided_by)} · ${formatDateTime(request.decided_at)}`"
                                    >
                                        {{ actorLabel(request.decided_by) }} ·
                                        {{ formatDateTime(request.decided_at) }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                    >
                                        Sin decisión
                                    </span>
                                </td>
                                <td class="max-w-[14rem] px-3 py-2.5">
                                    <span
                                        class="block truncate text-muted-foreground"
                                        :title="request.notes ?? undefined"
                                    >
                                        {{ request.notes ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <template
                                            v-if="
                                                request.status === 'pending' &&
                                                props.can.approve
                                            "
                                        >
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="text-emerald-700 dark:text-emerald-300"
                                                @click="
                                                    openDecision(
                                                        request,
                                                        'approved',
                                                    )
                                                "
                                            >
                                                <Check aria-hidden="true" />
                                                Aprobar
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive-strong"
                                                @click="
                                                    openDecision(
                                                        request,
                                                        'rejected',
                                                    )
                                                "
                                            >
                                                <X aria-hidden="true" />
                                                Rechazar
                                            </Button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </template>

                    <template #empty>
                        <EmptyState
                            :icon="CalendarCheck"
                            title="Sin solicitudes de vacaciones"
                            :description="
                                props.can.manage
                                    ? 'Registra la primera solicitud para que el colaborador pueda tomar sus días.'
                                    : 'Aún no hay solicitudes registradas para el filtro actual.'
                            "
                        >
                            <template v-if="props.can.manage" #action>
                                <Button @click="openRequestForm">
                                    <Plus aria-hidden="true" />
                                    Nueva solicitud
                                </Button>
                            </template>
                        </EmptyState>
                    </template>

                    <Pagination
                        :links="props.requests.links"
                        :meta="props.requests.meta ?? null"
                    />
                </DataTable>
            </section>
        </div>

        <Dialog :open="requestFormOpen" @update:open="handleRequestFormOpen">
            <DialogScrollContent class="sm:max-w-lg">
                <DialogHeader class="space-y-2">
                    <DialogTitle>Nueva solicitud de vacaciones</DialogTitle>
                    <DialogDescription>
                        Los días y el saldo disponible se validan en el
                        servidor; la solicitud queda pendiente de aprobación.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitRequest">
                    <FormField
                        label="Colaborador"
                        for="vacation-form-employee"
                        :error="requestForm.errors.employee_id"
                        required
                    >
                        <EmployeeSearchSelect
                            v-if="employeeOptions.length > 0"
                            input-id="vacation-form-employee"
                            v-model="requestForm.employee_id"
                            :options="employeeOptions"
                            :invalid="Boolean(requestForm.errors.employee_id)"
                        />
                        <Input
                            v-else
                            id="vacation-form-employee"
                            v-model="requestForm.employee_id"
                            type="number"
                            min="1"
                            inputmode="numeric"
                            placeholder="ID de colaborador"
                            :aria-invalid="
                                requestForm.errors.employee_id
                                    ? true
                                    : undefined
                            "
                        />
                    </FormField>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <FormField
                            label="Fecha inicial"
                            for="vacation-form-start"
                            :error="requestForm.errors.start_date"
                            required
                        >
                            <Input
                                id="vacation-form-start"
                                v-model="requestForm.start_date"
                                type="date"
                            />
                        </FormField>

                        <FormField
                            label="Fecha final"
                            for="vacation-form-end"
                            :error="requestForm.errors.end_date"
                            required
                        >
                            <Input
                                id="vacation-form-end"
                                v-model="requestForm.end_date"
                                type="date"
                            />
                        </FormField>
                    </div>

                    <InputError :message="requestDateError ?? undefined" />

                    <FormField
                        label="Notas"
                        for="vacation-form-notes"
                        :error="requestForm.errors.notes"
                    >
                        <Textarea
                            id="vacation-form-notes"
                            v-model="requestForm.notes"
                            rows="3"
                            maxlength="500"
                            placeholder="Comentario para la aprobación"
                        />
                    </FormField>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="requestForm.processing"
                            @click="handleRequestFormOpen(false)"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            :disabled="
                                requestForm.processing ||
                                requestDateError !== null
                            "
                        >
                            <Spinner v-if="requestForm.processing" />
                            Registrar solicitud
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <DecisionDialog
            :open="decisionOpen"
            :title="decisionTitle"
            :description="decisionDescription"
            :notes="decisionNotes"
            :notes-label="
                decisionKind === 'approved'
                    ? 'Notas de la aprobación (opcional)'
                    : 'Motivo del rechazo'
            "
            :notes-hint="
                decisionKind === 'rejected'
                    ? 'Explica al colaborador por qué se rechaza la solicitud.'
                    : 'Puedes dejar una referencia interna para auditoría.'
            "
            :confirm-label="
                decisionKind === 'approved' ? 'Aprobar' : 'Rechazar'
            "
            :variant="decisionKind === 'approved' ? 'default' : 'destructive'"
            :processing="decisionForm.processing"
            :error="decisionForm.errors.notes ?? decisionForm.errors.decision"
            @update:open="handleDecisionOpen"
            @update:notes="decisionNotes = $event"
            @confirm="submitDecision"
        />
    </div>
</template>
