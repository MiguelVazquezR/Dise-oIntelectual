<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatMoney } from '@/lib/format';
import {
    payrollConceptCalculationLabel,
    payrollConceptTypeLabel,
    payrollConceptTypeVariant,
} from '@/lib/labels';
import type {
    PayrollConceptFormData,
    PayrollConceptIndexProps,
    PayrollConceptRow,
    SelectOption,
} from '@/types';

const props = defineProps<PayrollConceptIndexProps>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Nómina', href: '/payroll/periods' },
            { title: 'Conceptos', href: '/concepts' },
        ],
    },
});

const TYPE_OPTIONS: SelectOption[] = [
    { value: 'earning', label: payrollConceptTypeLabel('earning') },
    { value: 'deduction', label: payrollConceptTypeLabel('deduction') },
];

const CALCULATION_OPTIONS: SelectOption[] = [
    {
        value: 'fixed',
        label: payrollConceptCalculationLabel('fixed'),
    },
    {
        value: 'per_quantity',
        label: payrollConceptCalculationLabel('per_quantity'),
    },
];

const isPaginated = computed(() => !Array.isArray(props.concepts));

const rows = computed<PayrollConceptRow[]>(() =>
    Array.isArray(props.concepts) ? props.concepts : props.concepts.data,
);

const links = computed(() =>
    Array.isArray(props.concepts) ? [] : props.concepts.links,
);

const meta = computed(() =>
    Array.isArray(props.concepts) ? null : (props.concepts.meta ?? null),
);

/* Alta y edición */
const formOpen = ref(false);
const editing = ref<PayrollConceptRow | null>(null);

const form = useForm<PayrollConceptFormData>({
    code: '',
    name: '',
    type: '',
    calculation: 'fixed',
    default_amount: '',
    is_sensitive: false,
    is_active: true,
    notes: '',
});

const formTitle = computed(() =>
    editing.value ? 'Editar concepto' : 'Nuevo concepto',
);

function emptyForm(): PayrollConceptFormData {
    return {
        code: '',
        name: '',
        type: '',
        calculation: 'fixed',
        default_amount: '',
        is_sensitive: false,
        is_active: true,
        notes: '',
    };
}

function resetForm(values: PayrollConceptFormData): void {
    form.defaults(values);
    form.reset();
    form.clearErrors();
}

function openCreate(): void {
    editing.value = null;
    resetForm(emptyForm());
    formOpen.value = true;
}

function openEdit(row: PayrollConceptRow): void {
    editing.value = row;
    resetForm({
        code: row.code,
        name: row.name,
        type: row.type,
        calculation: row.calculation,
        default_amount:
            row.default_amount === null ? '' : String(row.default_amount),
        is_sensitive: row.is_sensitive,
        is_active: row.is_active,
        notes: row.notes ?? '',
    });
    formOpen.value = true;
}

function handleFormOpen(open: boolean): void {
    formOpen.value = open;

    if (!open) {
        editing.value = null;
        resetForm(emptyForm());
    }
}

function submitForm(): void {
    if (form.processing) {
        return;
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            handleFormOpen(false);
        },
    };

    if (editing.value) {
        form.put(`/concepts/${editing.value.id}`, options);

        return;
    }

    form.post('/concepts', options);
}

/* Eliminación */
const deleteOpen = ref(false);
const deleteTarget = ref<PayrollConceptRow | null>(null);
const deleting = ref(false);

function openDelete(row: PayrollConceptRow): void {
    deleteTarget.value = row;
    deleteOpen.value = true;
}

function handleDeleteOpen(open: boolean): void {
    deleteOpen.value = open;

    if (!open && !deleting.value) {
        deleteTarget.value = null;
    }
}

function confirmDelete(): void {
    const target = deleteTarget.value;

    if (!target || deleting.value) {
        return;
    }

    deleting.value = true;

    router.delete(`/concepts/${target.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
        },
        onSuccess: () => {
            deleteOpen.value = false;
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Conceptos de nómina" />

        <PageHeader
            title="Conceptos de nómina"
            description="Catálogo de percepciones y deducciones que la pre-nómina aplica. El código es único y los conceptos sensibles requieren revisión."
        >
            <template #actions>
                <Button v-if="props.can.manage" @click="openCreate">
                    <Plus aria-hidden="true" />
                    Nuevo concepto
                </Button>
            </template>
        </PageHeader>

        <FlashAlert />

        <p
            v-if="props.notice"
            role="note"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
        >
            {{ props.notice }}
        </p>

        <p
            v-if="!props.can.manage"
            role="note"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
        >
            Solo lectura: no tienes permiso para crear, editar ni eliminar
            conceptos de nómina.
        </p>

        <DataTable
            :empty="rows.length === 0"
            caption="Catálogo de conceptos de nómina"
        >
            <template #body>
                <thead
                    class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th scope="col" class="px-3 py-2.5">Código</th>
                        <th scope="col" class="px-3 py-2.5">Nombre</th>
                        <th scope="col" class="px-3 py-2.5">Tipo</th>
                        <th scope="col" class="px-3 py-2.5">Cálculo</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Importe por defecto
                        </th>
                        <th scope="col" class="px-3 py-2.5">Sensible</th>
                        <th scope="col" class="px-3 py-2.5">Activo</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="concept in rows"
                        :key="concept.id"
                        class="border-t border-border align-middle hover:bg-muted/30"
                    >
                        <td
                            class="px-3 py-2.5 font-mono text-xs whitespace-nowrap text-foreground"
                        >
                            {{ concept.code }}
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="block font-medium text-foreground">
                                {{ concept.name }}
                            </span>
                            <span
                                v-if="concept.notes"
                                class="block max-w-[22rem] truncate text-xs text-muted-foreground"
                                :title="concept.notes"
                            >
                                {{ concept.notes }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                :variant="
                                    payrollConceptTypeVariant(concept.type)
                                "
                            >
                                {{ payrollConceptTypeLabel(concept.type) }}
                            </StatusBadge>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{
                                payrollConceptCalculationLabel(
                                    concept.calculation,
                                )
                            }}
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ formatMoney(concept.default_amount) }}
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                v-if="concept.is_sensitive"
                                variant="danger"
                            >
                                Sensible
                            </StatusBadge>
                            <span v-else class="text-xs text-muted-foreground">
                                —
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                :variant="
                                    concept.is_active ? 'success' : 'neutral'
                                "
                            >
                                {{ concept.is_active ? 'Activo' : 'Inactivo' }}
                            </StatusBadge>
                        </td>
                        <td class="px-3 py-2.5">
                            <div
                                v-if="props.can.manage"
                                class="flex items-center justify-end gap-1"
                            >
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    :aria-label="`Editar concepto ${concept.name}`"
                                    @click="openEdit(concept)"
                                >
                                    <Pencil aria-hidden="true" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-destructive-strong"
                                    :aria-label="`Eliminar concepto ${concept.name}`"
                                    @click="openDelete(concept)"
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </template>

            <template #empty>
                <EmptyState
                    :icon="Tags"
                    title="Sin conceptos de nómina"
                    :description="
                        props.can.manage
                            ? 'Crea el primer concepto para que la pre-nómina tenga percepciones y deducciones disponibles.'
                            : 'El catálogo está vacío. Un usuario con permiso de administración debe capturarlo.'
                    "
                >
                    <template v-if="props.can.manage" #action>
                        <Button @click="openCreate">
                            <Plus aria-hidden="true" />
                            Nuevo concepto
                        </Button>
                    </template>
                </EmptyState>
            </template>

            <Pagination v-if="isPaginated" :links="links" :meta="meta" />
        </DataTable>

        <Dialog :open="formOpen" @update:open="handleFormOpen">
            <DialogScrollContent class="sm:max-w-xl">
                <DialogHeader class="space-y-2">
                    <DialogTitle>{{ formTitle }}</DialogTitle>
                    <DialogDescription>
                        El servidor valida que el código sea único y normaliza
                        los importes a dos decimales.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitForm">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <FormField
                            label="Código"
                            for="concept-form-code"
                            :error="form.errors.code"
                            hint="Único; mayúsculas, números y guion bajo (ej. BONO_001)."
                            required
                        >
                            <Input
                                id="concept-form-code"
                                v-model="form.code"
                                type="text"
                                maxlength="30"
                                autocomplete="off"
                            />
                        </FormField>

                        <FormField
                            label="Nombre"
                            for="concept-form-name"
                            :error="form.errors.name"
                            required
                        >
                            <Input
                                id="concept-form-name"
                                v-model="form.name"
                                type="text"
                                maxlength="120"
                                autocomplete="off"
                            />
                        </FormField>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <FormField
                            label="Tipo"
                            for="concept-form-type"
                            :error="form.errors.type"
                            required
                        >
                            <Select v-model="form.type">
                                <SelectTrigger
                                    id="concept-form-type"
                                    class="w-full"
                                >
                                    <SelectValue
                                        placeholder="Selecciona el tipo"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in TYPE_OPTIONS"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            label="Cálculo"
                            for="concept-form-calculation"
                            :error="form.errors.calculation"
                            required
                        >
                            <Select v-model="form.calculation">
                                <SelectTrigger
                                    id="concept-form-calculation"
                                    class="w-full"
                                >
                                    <SelectValue
                                        placeholder="Selecciona el cálculo"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in CALCULATION_OPTIONS"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                    </div>

                    <FormField
                        label="Importe por defecto"
                        for="concept-form-amount"
                        :error="form.errors.default_amount"
                        hint="Opcional. Monto sugerido al aplicar el concepto; puede ajustarse en cada movimiento."
                    >
                        <Input
                            id="concept-form-amount"
                            v-model="form.default_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            placeholder="0.00"
                        />
                    </FormField>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="flex items-start gap-2">
                            <Checkbox
                                id="concept-form-sensitive"
                                v-model="form.is_sensitive"
                            />
                            <div class="grid gap-1">
                                <Label for="concept-form-sensitive">
                                    Concepto sensible
                                </Label>
                                <p class="text-xs text-muted-foreground">
                                    Exige revisión adicional al aplicarse como
                                    descuento.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-2">
                            <Checkbox
                                id="concept-form-active"
                                v-model="form.is_active"
                            />
                            <div class="grid gap-1">
                                <Label for="concept-form-active">Activo</Label>
                                <p class="text-xs text-muted-foreground">
                                    Sólo los conceptos activos se ofrecen en
                                    ajustes y cálculos nuevos.
                                </p>
                            </div>
                        </div>
                    </div>

                    <FormField
                        label="Notas"
                        for="concept-form-notes"
                        :error="form.errors.notes"
                        hint="Uso interno: cuándo aplica y cómo se calcula."
                    >
                        <Textarea
                            id="concept-form-notes"
                            v-model="form.notes"
                            rows="3"
                            maxlength="500"
                            placeholder="Descripción del concepto"
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
                            {{ editing ? 'Guardar cambios' : 'Crear concepto' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <ConfirmDialog
            :open="deleteOpen"
            title="Eliminar concepto"
            :description="
                deleteTarget
                    ? `Se eliminará el concepto ${deleteTarget.code} — ${deleteTarget.name}. Si tiene movimientos históricos, el servidor puede impedir la eliminación; en ese caso desactívalo en lugar de borrarlo.`
                    : ''
            "
            confirm-label="Eliminar concepto"
            :processing="deleting"
            variant="destructive"
            @update:open="handleDeleteOpen"
            @confirm="confirmDelete"
        />
    </div>
</template>
