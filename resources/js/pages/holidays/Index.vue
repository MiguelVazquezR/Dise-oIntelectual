<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    Info,
    Lock,
    Plus,
    RotateCcw,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
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
import { useListFilters } from '@/composables/useListFilters';
import { formatDate, formatMonthYear } from '@/lib/format';
import { holidayTypeLabel, holidayTypeVariant } from '@/lib/labels';
import { toSelectOptions, filterValue } from '@/lib/options';
import type {
    HolidayFormData,
    HolidayIndexProps,
    HolidayMonthGroup,
    HolidayRow,
} from '@/types';

const props = defineProps<HolidayIndexProps>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Festivos', href: '/holidays' }],
    },
});

type MonthGroup = {
    key: string;
    label: string;
    rows: HolidayRow[];
};

const currentYear = new Date().getFullYear();

const { filters, isFiltering, apply } = useListFilters('/holidays', {
    year: filterValue(props.filters.year),
    branch_id: filterValue(props.filters.branch_id),
    type: props.filters.type ?? '',
});

const branchOptions = computed(() => toSelectOptions(props.options?.branches));

const yearOptions = computed(() => {
    const options = toSelectOptions(props.options?.years);

    return options.length > 0
        ? options
        : [{ value: String(currentYear), label: String(currentYear) }];
});

const typeOptions = computed(() => {
    const options = toSelectOptions(props.options?.types);

    return options.length > 0
        ? options
        : [
              { value: 'mandatory', label: 'Obligatorios (LFT)' },
              { value: 'company', label: 'De empresa' },
          ];
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

const typeModel = computed({
    get: () => (filters.type === '' ? 'all' : filters.type),
    set: (value: string) => {
        filters.type = value === 'all' ? '' : value;
        apply();
    },
});

/**
 * El contrato permite que `holidays` llegue plano, agrupado por mes o como
 * diccionario; se normaliza a una sola colección para agrupar en pantalla.
 */
const flatHolidays = computed<HolidayRow[]>(() => {
    const payload = props.holidays;

    if (Array.isArray(payload)) {
        const groups = payload as HolidayMonthGroup[];
        const isGrouped =
            groups.length > 0 &&
            groups.every(
                (group) =>
                    group !== null &&
                    typeof group === 'object' &&
                    (Array.isArray(group.days) ||
                        Array.isArray(group.holidays)),
            );

        return isGrouped
            ? groups.flatMap((group) => group.days ?? group.holidays ?? [])
            : (payload as HolidayRow[]);
    }

    if (payload !== null && typeof payload === 'object') {
        return Object.values(payload).flat();
    }

    return [];
});

const monthGroups = computed<MonthGroup[]>(() => {
    const groups = new Map<string, HolidayRow[]>();

    for (const holiday of flatHolidays.value) {
        const key = holiday.date.slice(0, 7);
        const rows = groups.get(key) ?? [];
        rows.push(holiday);
        groups.set(key, rows);
    }

    return [...groups.entries()]
        .sort(([left], [right]) => left.localeCompare(right))
        .map(([key, rows]) => ({
            key,
            label: formatMonthYear(`${key}-01`),
            rows: [...rows].sort((left, right) =>
                left.date.localeCompare(right.date),
            ),
        }));
});

const isLocked = computed(
    () =>
        props.year_locked === true ||
        flatHolidays.value.some((holiday) => holiday.is_locked),
);

const generateYear = computed(() =>
    filters.year === '' ? String(currentYear) : filters.year,
);

const hasFilters = computed(
    () => filters.branch_id !== '' || filters.type !== '',
);

/** El año siempre se consulta: solo se limpian sucursal y tipo. */
function resetFilters(): void {
    filters.branch_id = '';
    filters.type = '';
    apply();
}

/* Generar festivos oficiales */
const generateConfirmOpen = ref(false);
const isGenerating = ref(false);

function confirmGenerate(): void {
    isGenerating.value = true;

    router.post(
        `/holidays/generate/${generateYear.value}`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                generateConfirmOpen.value = false;
            },
            onFinish: () => {
                isGenerating.value = false;
            },
        },
    );
}

/* Alta de festivo */
const formOpen = ref(false);

const form = useForm<HolidayFormData>({
    name: '',
    date: '',
    type: 'company',
    branch_id: '',
    legal_basis: '',
});

form.transform((data) => ({
    ...data,
    branch_id: data.type === 'company' ? data.branch_id : null,
    legal_basis: data.type === 'mandatory' ? data.legal_basis : null,
}));

function openCreate(): void {
    form.reset();
    form.clearErrors();
    form.type = 'company';
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

    form.post('/holidays', {
        preserveScroll: true,
        onSuccess: () => {
            handleFormOpen(false);
        },
    });
}

/* Baja de festivo */
const deleteTarget = ref<HolidayRow | null>(null);
const deleting = ref(false);

function confirmDelete(): void {
    const target = deleteTarget.value;

    if (!target) {
        return;
    }

    deleting.value = true;

    router.delete(`/holidays/${target.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}

function handleDeleteOpen(open: boolean): void {
    if (!open) {
        deleteTarget.value = null;
    }
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Festivos" />

        <PageHeader
            title="Festivos"
            description="Calendario de días festivos obligatorios y de empresa por sucursal. La generación es idempotente y no modifica años cerrados."
        >
            <template #actions>
                <Button
                    v-if="props.can.manage"
                    variant="outline"
                    :disabled="isGenerating"
                    @click="generateConfirmOpen = true"
                >
                    <Spinner v-if="isGenerating" />
                    <CalendarDays v-else aria-hidden="true" />
                    Generar festivos {{ generateYear }}
                </Button>
                <Button v-if="props.can.manage" @click="openCreate">
                    <Plus aria-hidden="true" />
                    Nuevo festivo
                </Button>
            </template>
        </PageHeader>

        <FlashAlert />

        <p
            v-if="props.legal_notice"
            role="note"
            class="flex items-start gap-2 rounded-lg border border-sky-700/30 bg-sky-50 px-3 py-2 text-sm text-sky-900 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-100"
        >
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                <strong class="font-semibold">Aviso legal:</strong>
                {{ props.legal_notice }}
                Este calendario es informativo y puede requerir actualizaciones:
                confirma las fechas con la fuente oficial antes de usarlo en
                nómina.
            </span>
        </p>

        <p
            v-if="isLocked"
            class="flex items-start gap-2 rounded-lg border border-amber-600/40 bg-amber-50 px-3 py-2 text-sm text-amber-900"
            role="note"
        >
            <Lock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            El año consultado está cerrado o tiene nómina cerrada que lo usa:
            los festivos no se pueden modificar.
        </p>

        <div class="grid gap-3 sm:grid-cols-3">
            <StatCard
                label="Obligatorios"
                :value="props.summary.mandatory ?? 0"
                :icon="CalendarDays"
                hint="Con base legal"
            />
            <StatCard
                label="De empresa"
                :value="props.summary.company ?? 0"
                :icon="CalendarDays"
                hint="Definidos por la empresa"
            />
            <StatCard
                label="Total"
                :value="props.summary.total ?? 0"
                :icon="CalendarDays"
                hint="Festivos en el filtro"
            />
        </div>

        <DataTable
            :empty="monthGroups.length === 0"
            :loading="isFiltering"
            caption="Calendario de festivos agrupado por mes"
        >
            <template #header>
                <form
                    class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
                    @submit.prevent="apply"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="grid gap-1.5">
                            <Label
                                for="holiday-year"
                                class="text-xs text-muted-foreground"
                                >Año</Label
                            >
                            <Select v-model="yearModel">
                                <SelectTrigger
                                    id="holiday-year"
                                    class="w-full sm:w-32"
                                >
                                    <SelectValue
                                        placeholder="Selecciona el año"
                                    />
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
                                for="holiday-branch"
                                class="text-xs text-muted-foreground"
                                >Sucursal</Label
                            >
                            <Select v-model="branchModel">
                                <SelectTrigger
                                    id="holiday-branch"
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
                                for="holiday-type"
                                class="text-xs text-muted-foreground"
                                >Tipo</Label
                            >
                            <Select v-model="typeModel">
                                <SelectTrigger
                                    id="holiday-type"
                                    class="w-full sm:w-40"
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
            </template>

            <template #body>
                <thead
                    class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th scope="col" class="px-3 py-2.5">Festivo</th>
                        <th scope="col" class="px-3 py-2.5">Fecha</th>
                        <th scope="col" class="px-3 py-2.5">Tipo</th>
                        <th scope="col" class="px-3 py-2.5">Sucursal</th>
                        <th scope="col" class="px-3 py-2.5">Base legal</th>
                        <th scope="col" class="px-3 py-2.5">Fuente</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>

                <tbody
                    v-for="group in monthGroups"
                    :key="group.key"
                    class="divide-y divide-border"
                >
                    <tr class="bg-muted/40">
                        <th
                            scope="colgroup"
                            :colspan="7"
                            class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-primary uppercase"
                        >
                            {{ group.label }}
                        </th>
                    </tr>

                    <tr
                        v-for="holiday in group.rows"
                        :key="holiday.id"
                        class="align-middle hover:bg-muted/30"
                    >
                        <td class="px-3 py-2.5">
                            <span class="font-medium text-foreground">
                                {{ holiday.name }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ formatDate(holiday.date) }}
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                :variant="holidayTypeVariant(holiday.type)"
                            >
                                {{ holidayTypeLabel(holiday.type) }}
                            </StatusBadge>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ holiday.branch?.name ?? 'Todas' }}
                        </td>
                        <td class="max-w-[16rem] px-3 py-2.5">
                            <span
                                class="block truncate text-muted-foreground"
                                :title="holiday.legal_basis ?? undefined"
                            >
                                {{ holiday.legal_basis ?? '—' }}
                            </span>
                        </td>
                        <td class="max-w-[12rem] px-3 py-2.5">
                            <a
                                v-if="holiday.source_url"
                                :href="holiday.source_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-xs break-all text-primary underline underline-offset-4 hover:text-primary/80 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                Consultar fuente
                            </a>
                            <span v-else class="text-xs text-muted-foreground">
                                —
                            </span>
                            <span
                                v-if="holiday.source_checked_at"
                                class="mt-0.5 block text-[11px] text-muted-foreground"
                            >
                                Revisado:
                                {{ formatDate(holiday.source_checked_at) }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    v-if="props.can.manage"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-destructive-strong"
                                    :disabled="holiday.is_locked"
                                    :aria-disabled="holiday.is_locked"
                                    :title="
                                        holiday.is_locked
                                            ? 'Año cerrado: no se puede eliminar'
                                            : `Eliminar ${holiday.name}`
                                    "
                                    :aria-label="`Eliminar ${holiday.name}`"
                                    @click="deleteTarget = holiday"
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                                <Lock
                                    v-if="holiday.is_locked"
                                    class="size-3.5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </template>

            <template #empty>
                <EmptyState
                    :icon="CalendarDays"
                    title="Sin festivos para el filtro"
                    :description="
                        hasFilters
                            ? 'No hay festivos registrados con estos filtros. Prueba con otro año o sucursal.'
                            : 'Genera los festivos oficiales del año o registra un festivo de empresa.'
                    "
                >
                    <template v-if="props.can.manage" #action>
                        <Button
                            variant="outline"
                            :disabled="isGenerating"
                            @click="generateConfirmOpen = true"
                        >
                            <CalendarDays aria-hidden="true" />
                            Generar festivos {{ generateYear }}
                        </Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <Dialog :open="formOpen" @update:open="handleFormOpen">
            <DialogScrollContent class="sm:max-w-lg">
                <DialogHeader class="space-y-2">
                    <DialogTitle>Nuevo festivo</DialogTitle>
                    <DialogDescription>
                        Los festivos obligatorios requieren base legal; los de
                        empresa pueden asociarse a una sucursal.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitForm">
                    <FormField
                        label="Nombre"
                        for="holiday-form-name"
                        :error="form.errors.name"
                        required
                    >
                        <Input
                            id="holiday-form-name"
                            v-model="form.name"
                            type="text"
                            maxlength="120"
                            placeholder="Ej. Día de la Independencia"
                        />
                    </FormField>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <FormField
                            label="Fecha"
                            for="holiday-form-date"
                            :error="form.errors.date"
                            required
                        >
                            <Input
                                id="holiday-form-date"
                                v-model="form.date"
                                type="date"
                            />
                        </FormField>

                        <FormField
                            label="Tipo"
                            for="holiday-form-type"
                            :error="form.errors.type"
                            required
                        >
                            <Select v-model="form.type">
                                <SelectTrigger
                                    id="holiday-form-type"
                                    class="w-full"
                                >
                                    <SelectValue
                                        placeholder="Selecciona el tipo"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="mandatory">
                                        Obligatorio (LFT)
                                    </SelectItem>
                                    <SelectItem value="company">
                                        De empresa
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                    </div>

                    <FormField
                        v-if="form.type === 'company'"
                        label="Sucursal"
                        for="holiday-form-branch"
                        :error="form.errors.branch_id"
                        hint="Si se deja vacío, aplica a todas las sucursales."
                    >
                        <Select v-model="form.branch_id">
                            <SelectTrigger
                                id="holiday-form-branch"
                                class="w-full"
                            >
                                <SelectValue
                                    placeholder="Todas las sucursales"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in branchOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        v-if="form.type === 'mandatory'"
                        label="Base legal"
                        for="holiday-form-legal"
                        :error="form.errors.legal_basis"
                        hint="Artículo o disposición que sustenta el festivo."
                    >
                        <Input
                            id="holiday-form-legal"
                            v-model="form.legal_basis"
                            type="text"
                            maxlength="120"
                            placeholder="Ej. Art. 74 LFT"
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
                            Guardar festivo
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>

        <ConfirmDialog
            :open="generateConfirmOpen"
            title="Generar festivos oficiales"
            :description="`Se agregarán los festivos oficiales de ${generateYear} que aún no existan. No se duplican registros ni se modifican años cerrados.`"
            :confirm-label="`Generar ${generateYear}`"
            variant="default"
            :processing="isGenerating"
            @update:open="generateConfirmOpen = $event"
            @confirm="confirmGenerate"
        />

        <ConfirmDialog
            :open="deleteTarget !== null"
            title="Eliminar festivo"
            :description="
                deleteTarget
                    ? `Se eliminará «${deleteTarget.name}» del calendario. Esta acción no se puede deshacer.`
                    : ''
            "
            confirm-label="Eliminar festivo"
            :processing="deleting"
            @update:open="handleDeleteOpen"
            @confirm="confirmDelete"
        />
    </div>
</template>
