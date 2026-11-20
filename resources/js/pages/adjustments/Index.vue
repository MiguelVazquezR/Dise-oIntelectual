<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BadgeDollarSign,
    Check,
    Plus,
    RotateCcw,
    Search,
    ShieldAlert,
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
import { formatDateTime, formatDays, formatMoney } from '@/lib/format';
import {
    actorLabel,
    adjustmentStatusLabel,
    adjustmentStatusVariant,
    payrollConceptTypeLabel,
    payrollConceptTypeVariant,
} from '@/lib/labels';
import { filterValue, toEmployeeOptions, toSelectOptions } from '@/lib/options';
import type {
    AdjustmentDecision,
    AdjustmentDecisionFormData,
    AdjustmentFormData,
    AdjustmentIndexProps,
    AdjustmentRow,
    SelectOption,
} from '@/types';

const props = defineProps<AdjustmentIndexProps>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Nómina', href: '/payroll/periods' },
            { title: 'Bonos y descuentos', href: '/adjustments' },
        ],
    },
});

const STATUS_OPTIONS: SelectOption[] = ['pending', 'approved', 'rejected'].map(
    (value) => ({ value, label: adjustmentStatusLabel(value) }),
);

const TYPE_OPTIONS: SelectOption[] = ['earning', 'deduction'].map((value) => ({
    value,
    label: payrollConceptTypeLabel(value),
}));

/**
 * El backend es la fuente de la regla; este texto sólo garantiza que el
 * aviso de revisión sea visible también si la prop llega vacía.
 */
const warningText = computed(
    () =>
        props.warning?.trim() ||
        'Los descuentos marcados como sensibles requieren revisión adicional antes de aprobarse.',
);

const { filters, isFiltering, apply, reset } = useListFilters('/adjustments', {
    period_id: filterValue(props.filters.period_id),
    branch_id: filterValue(props.filters.branch_id),
    employee_id: filterValue(props.filters.employee_id),
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
});

const periodOptions = computed(() => toSelectOptions(props.options?.periods));
const branchOptions = computed(() => toSelectOptions(props.options?.branches));
const conceptOptions = computed(() => toSelectOptions(props.options?.concepts));
const employeeOptions = computed(() =>
    toEmployeeOptions(props.options?.employees),
);

const hasFilters = computed(
    () =>
        filters.period_id !== '' ||
        filters.branch_id !== '' ||
        filters.employee_id !== '' ||
        filters.status !== '' ||
        filters.type !== '',
);

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

const statusModel = computed({
    get: () => (filters.status === '' ? 'all' : filters.status),
    set: (value: string) => {
        filters.status = value === 'all' ? '' : value;
        apply();
    },
});

const typeModel = computed({
    get: () => (filters.type === '' ? 'all' : filters.type),
    set: (value: string) => {
        filters.type = value === 'all' ? '' : value;
        apply();
    },
});

function setEmployeeFilter(value: string): void {
    filters.employee_id = value;
    apply();
}

/* Alta de ajuste */
const formOpen = ref(false);

const form = useForm<AdjustmentFormData>({
    employee_id: '',
    payroll_concept_id: '',
    payroll_period_id: '',
    amount: '',
    quantity: '1',
    reason: '',
});

const formPeriodModel = computed({
    get: () =>
        form.payroll_period_id === '' ? 'none' : form.payroll_period_id,
    set: (value: string) => {
        form.payroll_period_id = value === 'none' ? '' : value;
    },
});

function openCreate(): void {
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function handleFormOpen(open: boolean): void {
    formOpen.value = open;

    if (!open) {
        form.reset();
        form.clearErrors();
    }
}

function submitForm(): void {
    if (form.processing) {
        return;
    }

    form.post('/adjustments', {
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false;
            form.reset();
        },
    });
}

/* Aprobar / rechazar */
const decisionOpen = ref(false);
const decisionTarget = ref<AdjustmentRow | null>(null);
const decisionKind = ref<AdjustmentDecision>('approved');
const decisionNotes = ref('');

const decisionForm = useForm<AdjustmentDecisionFormData>({
    decision: 'approved',
    notes: '',
});

const decisionTitle = computed(() =>
    decisionKind.value === 'approved'
        ? 'Aprobar bono o descuento'
        : 'Rechazar bono o descuento',
);

const decisionDescription = computed(() => {
    const target = decisionTarget.value;

    if (!target) {
        return '';
    }

    return `${target.employee.full_name} · ${target.concept.name} · ${formatMoney(target.amount)}. La decisión queda registrada con tu usuario.`;
});

function openDecision(row: AdjustmentRow, kind: AdjustmentDecision): void {
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

    decisionForm.post(`/adjustments/${target.id}/decide`, {
        preserveScroll: true,
        onSuccess: () => {
            handleDecisionOpen(false);
        },
    });
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Bonos y descuentos" />

        <PageHeader
            title="Bonos y descuentos"
            description="Ajustes por concepto que se aplican a la pre-nómina. Se capturan como pendientes y requieren aprobación antes de calcular."
        >
            <template #actions>
                <Button v-if="props.can.manage" @click="openCreate">
                    <Plus aria-hidden="true" />
                    Nuevo ajuste
                </Button>
            </template>
        </PageHeader>

        <FlashAlert />

        <p
            role="note"
            class="flex items-start gap-2 rounded-lg border border-amber-600/40 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-100"
        >
            <ShieldAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                <strong class="font-semibold">Revisión de importes: </strong>
                {{ warningText }}
            </span>
        </p>

        <p
            v-if="!props.can.manage && !props.can.approve"
            role="note"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
        >
            Solo lectura: no tienes permiso para capturar ni decidir ajustes de
            nómina.
        </p>

        <DataTable
            :empty="props.adjustments.data.length === 0"
            :loading="isFiltering"
            caption="Listado de bonos y descuentos"
        >
            <template #header>
                <form
                    class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between"
                    @submit.prevent="apply"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
                    >
                        <div class="grid gap-1.5">
                            <Label
                                for="adjustment-period"
                                class="text-xs text-muted-foreground"
                                >Periodo</Label
                            >
                            <Select v-model="periodModel">
                                <SelectTrigger
                                    id="adjustment-period"
                                    class="w-full sm:w-56"
                                >
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
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
                            <Label
                                for="adjustment-branch"
                                class="text-xs text-muted-foreground"
                                >Sucursal</Label
                            >
                            <Select v-model="branchModel">
                                <SelectTrigger
                                    id="adjustment-branch"
                                    class="w-full sm:w-44"
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
                                for="adjustment-employee-filter"
                                class="text-xs text-muted-foreground"
                                >Colaborador</Label
                            >
                            <div class="w-full sm:w-64">
                                <EmployeeSearchSelect
                                    v-if="employeeOptions.length > 0"
                                    input-id="adjustment-employee-filter"
                                    :model-value="filters.employee_id"
                                    :options="employeeOptions"
                                    placeholder="Nombre o número"
                                    @update:model-value="setEmployeeFilter"
                                />
                                <Input
                                    v-else
                                    id="adjustment-employee-filter"
                                    v-model="filters.employee_id"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    placeholder="ID del colaborador"
                                    @change="apply"
                                />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="adjustment-status"
                                class="text-xs text-muted-foreground"
                                >Estado</Label
                            >
                            <Select v-model="statusModel">
                                <SelectTrigger
                                    id="adjustment-status"
                                    class="w-full sm:w-40"
                                >
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem
                                        v-for="option in STATUS_OPTIONS"
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
                                for="adjustment-type"
                                class="text-xs text-muted-foreground"
                                >Tipo</Label
                            >
                            <Select v-model="typeModel">
                                <SelectTrigger
                                    id="adjustment-type"
                                    class="w-full sm:w-40"
                                >
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem
                                        v-for="option in TYPE_OPTIONS"
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
                        <Spinner
                            v-if="isFiltering"
                            class="text-muted-foreground"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
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
                        <th scope="col" class="px-3 py-2.5">Colaborador</th>
                        <th scope="col" class="px-3 py-2.5">Sucursal</th>
                        <th scope="col" class="px-3 py-2.5">Concepto</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Importe
                        </th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Cantidad
                        </th>
                        <th scope="col" class="px-3 py-2.5">Motivo</th>
                        <th scope="col" class="px-3 py-2.5">Estado</th>
                        <th scope="col" class="px-3 py-2.5">Aprobación</th>
                        <th scope="col" class="px-3 py-2.5">Sensible</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="adjustment in props.adjustments.data"
                        :key="adjustment.id"
                        class="border-t border-border align-middle hover:bg-muted/30"
                    >
                        <td class="px-3 py-2.5">
                            <span class="block font-medium text-foreground">
                                {{ adjustment.employee.full_name }}
                            </span>
                            <span
                                v-if="adjustment.employee.employee_number"
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ adjustment.employee.employee_number }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ adjustment.branch?.name ?? '—' }}
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="block text-foreground">
                                {{ adjustment.concept.name }}
                            </span>
                            <span
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ adjustment.concept.code }}
                            </span>
                            <StatusBadge
                                class="mt-1"
                                :variant="
                                    payrollConceptTypeVariant(
                                        adjustment.concept.type,
                                    )
                                "
                            >
                                {{
                                    payrollConceptTypeLabel(
                                        adjustment.concept.type,
                                    )
                                }}
                            </StatusBadge>
                        </td>
                        <td
                            class="px-3 py-2.5 text-right font-medium whitespace-nowrap tabular-nums"
                        >
                            {{ formatMoney(adjustment.amount) }}
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ formatDays(adjustment.quantity) }}
                        </td>
                        <td class="max-w-[16rem] px-3 py-2.5">
                            <span
                                class="block truncate text-muted-foreground"
                                :title="adjustment.reason"
                            >
                                {{ adjustment.reason }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                :variant="
                                    adjustmentStatusVariant(adjustment.status)
                                "
                            >
                                {{ adjustmentStatusLabel(adjustment.status) }}
                            </StatusBadge>
                        </td>
                        <td class="max-w-[14rem] px-3 py-2.5">
                            <span
                                v-if="adjustment.approved_at"
                                class="block truncate text-xs text-muted-foreground"
                                :title="`${actorLabel(adjustment.approved_by)} · ${formatDateTime(adjustment.approved_at)}`"
                            >
                                {{ actorLabel(adjustment.approved_by) }} ·
                                {{ formatDateTime(adjustment.approved_at) }}
                            </span>
                            <span
                                v-else
                                class="block text-xs text-muted-foreground"
                            >
                                Sin decisión
                            </span>
                            <span
                                class="block truncate text-[11px] text-muted-foreground"
                                :title="`Registró: ${actorLabel(adjustment.created_by)}`"
                            >
                                Solicitó:
                                {{ actorLabel(adjustment.created_by) }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                v-if="adjustment.is_sensitive"
                                variant="danger"
                            >
                                <ShieldAlert aria-hidden="true" />
                                Sensible
                            </StatusBadge>
                            <span v-else class="text-xs text-muted-foreground">
                                —
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <template
                                    v-if="
                                        adjustment.status === 'pending' &&
                                        props.can.approve
                                    "
                                >
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="text-emerald-700 dark:text-emerald-300"
                                        @click="
                                            openDecision(adjustment, 'approved')
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
                                            openDecision(adjustment, 'rejected')
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
                    :icon="BadgeDollarSign"
                    title="Sin bonos ni descuentos"
                    :description="
                        hasFilters
                            ? 'No hay ajustes con los filtros aplicados. Ajusta el periodo, la sucursal o el colaborador.'
                            : 'Captura el primer ajuste para que el cálculo de la pre-nómina lo considere.'
                    "
                >
                    <template v-if="props.can.manage" #action>
                        <Button @click="openCreate">
                            <Plus aria-hidden="true" />
                            Nuevo ajuste
                        </Button>
                    </template>
                </EmptyState>
            </template>

            <Pagination
                :links="props.adjustments.links"
                :meta="props.adjustments.meta ?? null"
            />
        </DataTable>

        <Dialog :open="formOpen" @update:open="handleFormOpen">
            <DialogScrollContent class="sm:max-w-xl">
                <DialogHeader class="space-y-2">
                    <DialogTitle>Nuevo bono o descuento</DialogTitle>
                    <DialogDescription>
                        El ajuste se registra como pendiente de aprobación. Si
                        el concepto es sensible, el descuento requiere revisión
                        adicional antes de aplicarse a la pre-nómina.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitForm">
                    <FormField
                        label="Colaborador"
                        for="adjustment-form-employee"
                        :error="form.errors.employee_id"
                        required
                    >
                        <EmployeeSearchSelect
                            v-if="employeeOptions.length > 0"
                            input-id="adjustment-form-employee"
                            v-model="form.employee_id"
                            :options="employeeOptions"
                            :invalid="Boolean(form.errors.employee_id)"
                        />
                        <Input
                            v-else
                            id="adjustment-form-employee"
                            v-model="form.employee_id"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="ID del colaborador"
                            :aria-invalid="
                                Boolean(form.errors.employee_id) || undefined
                            "
                        />
                        <p
                            v-if="employeeOptions.length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            No hay catálogo de colaboradores disponible en este
                            momento; captura el ID interno y se validará al
                            guardar.
                        </p>
                    </FormField>

                    <FormField
                        label="Concepto"
                        for="adjustment-form-concept"
                        :error="form.errors.payroll_concept_id"
                        required
                    >
                        <Select v-model="form.payroll_concept_id">
                            <SelectTrigger
                                id="adjustment-form-concept"
                                class="w-full"
                            >
                                <SelectValue
                                    placeholder="Selecciona el concepto"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in conceptOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <FormField
                            label="Periodo de pre-nómina"
                            for="adjustment-form-period"
                            :error="form.errors.payroll_period_id"
                            hint="Opcional: sin periodo, el ajuste se aplica al siguiente cálculo."
                        >
                            <Select v-model="formPeriodModel">
                                <SelectTrigger
                                    id="adjustment-form-period"
                                    class="w-full"
                                >
                                    <SelectValue placeholder="Sin periodo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Sin periodo asignado
                                    </SelectItem>
                                    <SelectItem
                                        v-for="option in periodOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            label="Cantidad"
                            for="adjustment-form-quantity"
                            :error="form.errors.quantity"
                            hint="Unidades o días, con hasta dos decimales."
                            required
                        >
                            <Input
                                id="adjustment-form-quantity"
                                v-model="form.quantity"
                                type="number"
                                min="0.01"
                                step="0.01"
                                inputmode="decimal"
                            />
                        </FormField>
                    </div>

                    <FormField
                        label="Importe"
                        for="adjustment-form-amount"
                        :error="form.errors.amount"
                        hint="Importe en pesos con dos decimales; el servidor lo redondea al aplicar (S-05)."
                        required
                    >
                        <Input
                            id="adjustment-form-amount"
                            v-model="form.amount"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            placeholder="0.00"
                        />
                    </FormField>

                    <FormField
                        label="Motivo"
                        for="adjustment-form-reason"
                        :error="form.errors.reason"
                        hint="Justificación para la revisión y la auditoría."
                        required
                    >
                        <Textarea
                            id="adjustment-form-reason"
                            v-model="form.reason"
                            rows="3"
                            maxlength="255"
                            placeholder="Describe el motivo del bono o descuento"
                        />
                    </FormField>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="handleFormOpen(false)"
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            <Spinner v-if="form.processing" />
                            Registrar ajuste
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
                    ? 'Explica por qué se rechaza el ajuste; el colaborador y auditoría lo verán.'
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
