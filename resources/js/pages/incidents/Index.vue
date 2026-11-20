<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    X,
} from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
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
    incidentStatusLabel,
    incidentStatusVariant,
    incidentTypeLabel,
    incidentTypeVariant,
} from '@/lib/labels';
import { toEmployeeOptions, toSelectOptions, filterValue } from '@/lib/options';
import type {
    IncidentDecision,
    IncidentDecisionFormData,
    IncidentFormData,
    IncidentIndexProps,
    IncidentRow,
} from '@/types';

const props = defineProps<IncidentIndexProps>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Incidencias', href: '/incidents' }],
    },
});

const MAX_EVIDENCE_BYTES = 5 * 1024 * 1024;
const ALLOWED_EVIDENCE_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

const { filters, isFiltering, apply, reset } = useListFilters('/incidents', {
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    branch_id: filterValue(props.filters.branch_id),
    employee_id: filterValue(props.filters.employee_id),
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
});

const branchOptions = computed(() => toSelectOptions(props.options?.branches));
const typeOptions = computed(() => toSelectOptions(props.options?.types));
const statusOptions = computed(() => toSelectOptions(props.options?.statuses));
const employeeOptions = computed(() =>
    toEmployeeOptions(props.options?.employees),
);

const hasFilters = computed(
    () =>
        filters.from !== '' ||
        filters.to !== '' ||
        filters.branch_id !== '' ||
        filters.employee_id !== '' ||
        filters.type !== '' ||
        filters.status !== '',
);

const branchModel = computed({
    get: () => (filters.branch_id === '' ? 'all' : filters.branch_id),
    set: (value: string) => {
        filters.branch_id = value === 'all' ? '' : value;
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

const statusModel = computed({
    get: () => (filters.status === '' ? 'all' : filters.status),
    set: (value: string) => {
        filters.status = value === 'all' ? '' : value;
        apply();
    },
});

function setEmployeeFilter(value: string): void {
    filters.employee_id = value;
    apply();
}

function typeLabel(value: string): string {
    return (
        typeOptions.value.find((option) => option.value === value)?.label ??
        incidentTypeLabel(value)
    );
}

/* Alta y edición */
const formOpen = ref(false);
const editing = ref<IncidentRow | null>(null);
const evidenceError = ref<string | null>(null);
const evidenceInput = ref<HTMLInputElement | null>(null);

const form = useForm<IncidentFormData>({
    employee_id: '',
    type: '',
    start_date: '',
    end_date: '',
    quantity: '1',
    reason: '',
    evidence: null,
});

const formTitle = computed(() =>
    editing.value ? 'Editar incidencia' : 'Nueva incidencia',
);

const dateRangeError = computed(() => {
    if (form.start_date === '' || form.end_date === '') {
        return null;
    }

    return form.start_date > form.end_date
        ? 'La fecha final no puede ser anterior a la inicial.'
        : null;
});

function emptyForm(): IncidentFormData {
    return {
        employee_id: '',
        type: '',
        start_date: '',
        end_date: '',
        quantity: '1',
        reason: '',
        evidence: null,
    };
}

function resetForm(values: IncidentFormData): void {
    form.defaults(values);
    form.reset();
    form.clearErrors();
    evidenceError.value = null;

    void nextTick(() => {
        if (evidenceInput.value) {
            evidenceInput.value.value = '';
        }
    });
}

function openCreate(): void {
    editing.value = null;
    resetForm(emptyForm());
    formOpen.value = true;
}

function openEdit(row: IncidentRow): void {
    editing.value = row;
    resetForm({
        employee_id: String(row.employee.id),
        type: row.type,
        start_date: row.start_date.slice(0, 10),
        end_date: row.end_date.slice(0, 10),
        quantity: String(row.quantity ?? ''),
        reason: row.reason ?? '',
        evidence: null,
    });
    formOpen.value = true;
}

function handleFormOpen(open: boolean): void {
    formOpen.value = open;

    if (!open) {
        editing.value = null;
        form.reset();
        form.clearErrors();
        evidenceError.value = null;

        if (evidenceInput.value) {
            evidenceInput.value.value = '';
        }
    }
}

function onEvidenceChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    evidenceError.value = null;

    if (!file) {
        form.evidence = null;

        return;
    }

    if (file.size > MAX_EVIDENCE_BYTES) {
        evidenceError.value = 'El archivo supera el límite de 5 MB.';
        form.evidence = null;
        input.value = '';

        return;
    }

    if (!ALLOWED_EVIDENCE_TYPES.includes(file.type)) {
        evidenceError.value = 'Solo se aceptan archivos PDF, JPG o PNG.';
        form.evidence = null;
        input.value = '';

        return;
    }

    form.evidence = file;
}

function submitForm(): void {
    if (form.processing || dateRangeError.value !== null) {
        return;
    }

    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            handleFormOpen(false);
        },
    };

    if (editing.value) {
        form.put(`/incidents/${editing.value.id}`, options);

        return;
    }

    form.post('/incidents', options);
}

/* Aprobar / rechazar */
const decisionOpen = ref(false);
const decisionTarget = ref<IncidentRow | null>(null);
const decisionKind = ref<IncidentDecision>('approved');
const decisionNotes = ref('');

const decisionForm = useForm<IncidentDecisionFormData>({
    decision: 'approved',
    notes: '',
});

const decisionTitle = computed(() =>
    decisionKind.value === 'approved'
        ? 'Aprobar incidencia'
        : 'Rechazar incidencia',
);

const decisionDescription = computed(() => {
    const target = decisionTarget.value;

    if (!target) {
        return '';
    }

    return `${target.employee.full_name} · ${typeLabel(target.type)} · ${formatDate(target.start_date)} — ${formatDate(target.end_date)}. La decisión queda registrada con tu usuario.`;
});

function openDecision(row: IncidentRow, kind: IncidentDecision): void {
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

    decisionForm.post(`/incidents/${target.id}/decide`, {
        preserveScroll: true,
        onSuccess: () => {
            handleDecisionOpen(false);
        },
    });
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Incidencias" />

        <PageHeader
            title="Incidencias"
            description="Registro de faltas, permisos, vacaciones y demás incidencias. La aprobación la valida el backend y puede ajustar saldos."
        >
            <template #actions>
                <Button v-if="props.can.manage" @click="openCreate">
                    <Plus aria-hidden="true" />
                    Nueva incidencia
                </Button>
            </template>
        </PageHeader>

        <FlashAlert />

        <p
            v-if="!props.can.manage && !props.can.approve"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
            role="note"
        >
            Solo lectura: no tienes permiso para capturar ni decidir
            incidencias.
        </p>

        <DataTable
            :empty="props.incidents.data.length === 0"
            :loading="isFiltering"
            caption="Listado de incidencias"
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
                                for="incident-from"
                                class="text-xs text-muted-foreground"
                                >Desde</Label
                            >
                            <Input
                                id="incident-from"
                                v-model="filters.from"
                                type="date"
                                class="sm:w-40"
                                @change="apply"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="incident-to"
                                class="text-xs text-muted-foreground"
                                >Hasta</Label
                            >
                            <Input
                                id="incident-to"
                                v-model="filters.to"
                                type="date"
                                class="sm:w-40"
                                @change="apply"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="incident-branch"
                                class="text-xs text-muted-foreground"
                                >Sucursal</Label
                            >
                            <Select v-model="branchModel">
                                <SelectTrigger
                                    id="incident-branch"
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
                                for="incident-employee-filter"
                                class="text-xs text-muted-foreground"
                                >Colaborador</Label
                            >
                            <div class="w-full sm:w-64">
                                <EmployeeSearchSelect
                                    input-id="incident-employee-filter"
                                    :model-value="filters.employee_id"
                                    :options="employeeOptions"
                                    placeholder="Nombre o número"
                                    @update:model-value="setEmployeeFilter"
                                />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="incident-type"
                                class="text-xs text-muted-foreground"
                                >Tipo</Label
                            >
                            <Select v-model="typeModel">
                                <SelectTrigger
                                    id="incident-type"
                                    class="w-full sm:w-44"
                                >
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem
                                        v-for="option in typeOptions"
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
                                for="incident-status"
                                class="text-xs text-muted-foreground"
                                >Estado</Label
                            >
                            <Select v-model="statusModel">
                                <SelectTrigger
                                    id="incident-status"
                                    class="w-full sm:w-40"
                                >
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem
                                        v-for="option in statusOptions"
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
                        <th scope="col" class="px-3 py-2.5">Tipo</th>
                        <th scope="col" class="px-3 py-2.5">Periodo</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Cantidad
                        </th>
                        <th scope="col" class="px-3 py-2.5">Estado</th>
                        <th scope="col" class="px-3 py-2.5">Motivo</th>
                        <th scope="col" class="px-3 py-2.5">Decisión</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="incident in props.incidents.data"
                        :key="incident.id"
                        class="border-t border-border align-middle hover:bg-muted/30"
                    >
                        <td class="px-3 py-2.5">
                            <span class="block font-medium text-foreground">
                                {{ incident.employee.full_name }}
                            </span>
                            <span
                                v-if="incident.employee.employee_number"
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ incident.employee.employee_number }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ incident.branch?.name ?? '—' }}
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                :variant="incidentTypeVariant(incident.type)"
                            >
                                {{ typeLabel(incident.type) }}
                            </StatusBadge>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ formatDate(incident.start_date) }}
                            <span class="text-muted-foreground">→</span>
                            {{ formatDate(incident.end_date) }}
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ formatDays(incident.quantity) }}
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                :variant="
                                    incidentStatusVariant(incident.status)
                                "
                            >
                                {{ incidentStatusLabel(incident.status) }}
                            </StatusBadge>
                        </td>
                        <td class="max-w-[16rem] px-3 py-2.5">
                            <span
                                class="block truncate text-muted-foreground"
                                :title="incident.reason ?? undefined"
                            >
                                {{ incident.reason ?? '—' }}
                            </span>
                            <a
                                v-if="incident.evidence_url"
                                :href="incident.evidence_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-[11px] text-primary underline underline-offset-4"
                            >
                                Ver evidencia
                            </a>
                            <span
                                v-else-if="incident.has_evidence"
                                class="mt-1 block text-[11px] text-muted-foreground"
                            >
                                Con evidencia adjunta
                            </span>
                        </td>
                        <td class="max-w-[14rem] px-3 py-2.5">
                            <span
                                v-if="incident.decided_at"
                                class="block truncate text-xs text-muted-foreground"
                                :title="`${actorLabel(incident.decided_by)} · ${formatDateTime(incident.decided_at)}`"
                            >
                                {{ actorLabel(incident.decided_by) }} ·
                                {{ formatDateTime(incident.decided_at) }}
                            </span>
                            <span v-else class="text-xs text-muted-foreground">
                                Sin decisión
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <template
                                    v-if="
                                        incident.status === 'pending' &&
                                        props.can.approve
                                    "
                                >
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="text-emerald-700 dark:text-emerald-300"
                                        @click="
                                            openDecision(incident, 'approved')
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
                                            openDecision(incident, 'rejected')
                                        "
                                    >
                                        <X aria-hidden="true" />
                                        Rechazar
                                    </Button>
                                </template>
                                <Button
                                    v-if="
                                        incident.status === 'pending' &&
                                        props.can.manage
                                    "
                                    variant="ghost"
                                    size="icon-sm"
                                    :aria-label="`Editar incidencia de ${incident.employee.full_name}`"
                                    @click="openEdit(incident)"
                                >
                                    <Pencil aria-hidden="true" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </template>

            <template #empty>
                <EmptyState
                    :icon="AlertTriangle"
                    title="Sin incidencias registradas"
                    :description="
                        hasFilters
                            ? 'No hay incidencias con los filtros aplicados. Ajusta el rango o el colaborador.'
                            : 'Captura la primera incidencia para que el cálculo de asistencia y nómina la considere.'
                    "
                >
                    <template v-if="props.can.manage" #action>
                        <Button @click="openCreate">
                            <Plus aria-hidden="true" />
                            Nueva incidencia
                        </Button>
                    </template>
                </EmptyState>
            </template>

            <Pagination
                :links="props.incidents.links"
                :meta="props.incidents.meta ?? null"
            />
        </DataTable>

        <Dialog :open="formOpen" @update:open="handleFormOpen">
            <DialogScrollContent class="sm:max-w-xl">
                <DialogHeader class="space-y-2">
                    <DialogTitle>{{ formTitle }}</DialogTitle>
                    <DialogDescription>
                        El servidor obtiene la sucursal del colaborador y valida
                        que no exista una incidencia duplicada para el mismo
                        periodo.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitForm">
                    <FormField
                        label="Colaborador"
                        for="incident-form-employee"
                        :error="form.errors.employee_id"
                        required
                    >
                        <EmployeeSearchSelect
                            input-id="incident-form-employee"
                            v-model="form.employee_id"
                            :options="employeeOptions"
                            :invalid="Boolean(form.errors.employee_id)"
                            :disabled="editing !== null"
                        />
                        <p v-if="editing" class="text-xs text-muted-foreground">
                            El colaborador no se puede cambiar al editar; si te
                            equivocaste, cancela y registra una incidencia
                            nueva.
                        </p>
                    </FormField>

                    <FormField
                        label="Tipo de incidencia"
                        for="incident-form-type"
                        :error="form.errors.type"
                        required
                    >
                        <Select v-model="form.type">
                            <SelectTrigger
                                id="incident-form-type"
                                class="w-full"
                            >
                                <SelectValue placeholder="Selecciona el tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in typeOptions"
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
                            label="Fecha inicial"
                            for="incident-form-start"
                            :error="form.errors.start_date"
                            required
                        >
                            <Input
                                id="incident-form-start"
                                v-model="form.start_date"
                                type="date"
                            />
                        </FormField>

                        <FormField
                            label="Fecha final"
                            for="incident-form-end"
                            :error="form.errors.end_date"
                            required
                        >
                            <Input
                                id="incident-form-end"
                                v-model="form.end_date"
                                type="date"
                            />
                        </FormField>
                    </div>

                    <InputError :message="dateRangeError ?? undefined" />

                    <FormField
                        label="Cantidad"
                        for="incident-form-quantity"
                        :error="form.errors.quantity"
                        hint="Días u horas según el tipo, con hasta dos decimales."
                        required
                    >
                        <Input
                            id="incident-form-quantity"
                            v-model="form.quantity"
                            type="number"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                        />
                    </FormField>

                    <FormField
                        label="Motivo"
                        for="incident-form-reason"
                        :error="form.errors.reason"
                    >
                        <Textarea
                            id="incident-form-reason"
                            v-model="form.reason"
                            rows="3"
                            maxlength="255"
                            placeholder="Describe el motivo de la incidencia"
                        />
                    </FormField>

                    <div class="grid gap-1.5">
                        <Label for="incident-form-evidence">
                            Evidencia (opcional)
                        </Label>
                        <input
                            id="incident-form-evidence"
                            ref="evidenceInput"
                            type="file"
                            accept="application/pdf,image/jpeg,image/png"
                            class="block w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            :aria-invalid="
                                evidenceError || form.errors.evidence
                                    ? true
                                    : undefined
                            "
                            @change="onEvidenceChange"
                        />
                        <p class="text-xs text-muted-foreground">
                            PDF, JPG o PNG de hasta 5 MB.
                        </p>
                        <InputError
                            :message="
                                evidenceError ??
                                form.errors.evidence ??
                                undefined
                            "
                        />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="handleFormOpen(false)"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            :disabled="
                                form.processing || dateRangeError !== null
                            "
                        >
                            <Spinner v-if="form.processing" />
                            {{
                                editing
                                    ? 'Guardar cambios'
                                    : 'Registrar incidencia'
                            }}
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
                    ? 'Explica al colaborador por qué se rechaza la incidencia.'
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
