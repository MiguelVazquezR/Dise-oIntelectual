<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CalendarRange, Eye, Info, Plus, RotateCcw, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
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
import { useListFilters } from '@/composables/useListFilters';
import { formatDate, formatMoney } from '@/lib/format';
import {
    payFrequencyLabel,
    payrollPeriodStatusLabel,
    payrollPeriodStatusVariant,
} from '@/lib/labels';
import { filterValue, toSelectOptions } from '@/lib/options';
import type {
    PayrollIndexProps,
    PayrollPeriodFormData,
    SelectOption,
} from '@/types';

const props = defineProps<PayrollIndexProps>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Nómina', href: '/payroll/periods' },
            { title: 'Periodos', href: '/payroll/periods' },
        ],
    },
});

/** D-14: aviso permanente; el backend puede enviar además su propio `notice`. */
const DEFAULT_NOTICE =
    'Pre-nómina para revisión; no constituye CFDI ni cálculo fiscal definitivo.';

const backendNotice = computed(() => props.notice?.trim() ?? '');

const STATUS_OPTIONS: SelectOption[] = [
    'open',
    'calculated',
    'reviewed',
    'closed',
].map((value) => ({ value, label: payrollPeriodStatusLabel(value) }));

const { filters, isFiltering, apply, reset } = useListFilters(
    '/payroll/periods',
    {
        schedule_id: filterValue(props.filters.schedule_id),
        status: props.filters.status ?? '',
        from: props.filters.from ?? '',
        to: props.filters.to ?? '',
    },
);

const scheduleOptions = computed(() =>
    toSelectOptions(props.options?.schedules),
);

const hasFilters = computed(
    () =>
        filters.schedule_id !== '' ||
        filters.status !== '' ||
        filters.from !== '' ||
        filters.to !== '',
);

const scheduleModel = computed({
    get: () => (filters.schedule_id === '' ? 'all' : filters.schedule_id),
    set: (value: string) => {
        filters.schedule_id = value === 'all' ? '' : value;
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

/* Alta de periodo */
const formOpen = ref(false);

const form = useForm<PayrollPeriodFormData>({
    payroll_schedule_id: '',
    start_date: '',
    end_date: '',
    payment_date: '',
});

const dateRangeError = computed(() => {
    if (form.start_date === '' || form.end_date === '') {
        return null;
    }

    return form.end_date < form.start_date
        ? 'La fecha final no puede ser anterior a la inicial.'
        : null;
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
    if (form.processing || dateRangeError.value !== null) {
        return;
    }

    form.post('/payroll/periods', {
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Periodos de pre-nómina" />

        <PageHeader
            title="Periodos de pre-nómina"
            description="Cada periodo agrupa el cálculo por sucursal. Los importes son control interno: no se calculan impuestos ni cuotas."
        >
            <template #actions>
                <Button v-if="props.can.calculate" @click="openCreate">
                    <Plus aria-hidden="true" />
                    Nuevo periodo
                </Button>
            </template>
        </PageHeader>

        <FlashAlert />

        <div
            role="note"
            class="flex items-start gap-2 rounded-lg border border-sky-700/30 bg-sky-50 px-3 py-2 text-sm text-sky-900 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-100"
        >
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <div class="space-y-1">
                <p class="font-medium">{{ DEFAULT_NOTICE }}</p>
                <p v-if="backendNotice" class="text-xs">
                    {{ backendNotice }}
                </p>
            </div>
        </div>

        <p
            v-if="!props.can.calculate && !props.can.review && !props.can.close"
            role="note"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
        >
            Solo lectura: no tienes permiso para calcular, revisar ni cerrar
            periodos de pre-nómina.
        </p>

        <DataTable
            :empty="props.periods.data.length === 0"
            :loading="isFiltering"
            caption="Listado de periodos de pre-nómina"
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
                                for="payroll-schedule"
                                class="text-xs text-muted-foreground"
                                >Esquema</Label
                            >
                            <Select v-model="scheduleModel">
                                <SelectTrigger
                                    id="payroll-schedule"
                                    class="w-full sm:w-52"
                                >
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem
                                        v-for="option in scheduleOptions"
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
                                for="payroll-status"
                                class="text-xs text-muted-foreground"
                                >Estado</Label
                            >
                            <Select v-model="statusModel">
                                <SelectTrigger
                                    id="payroll-status"
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
                                for="payroll-from"
                                class="text-xs text-muted-foreground"
                                >Desde</Label
                            >
                            <Input
                                id="payroll-from"
                                v-model="filters.from"
                                type="date"
                                class="sm:w-40"
                                @change="apply"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="payroll-to"
                                class="text-xs text-muted-foreground"
                                >Hasta</Label
                            >
                            <Input
                                id="payroll-to"
                                v-model="filters.to"
                                type="date"
                                class="sm:w-40"
                                @change="apply"
                            />
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
                        <th scope="col" class="px-3 py-2.5">Esquema</th>
                        <th scope="col" class="px-3 py-2.5">Periodo</th>
                        <th scope="col" class="px-3 py-2.5">Día de pago</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Sucursales
                        </th>
                        <th scope="col" class="px-3 py-2.5">Estado</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Percepciones
                        </th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Descuentos
                        </th>
                        <th scope="col" class="px-3 py-2.5 text-right">Neto</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="period in props.periods.data"
                        :key="period.id"
                        class="border-t border-border align-middle hover:bg-muted/30"
                    >
                        <td class="px-3 py-2.5">
                            <span class="block font-medium text-foreground">
                                {{ period.schedule?.name ?? '—' }}
                            </span>
                            <span
                                v-if="period.schedule?.frequency"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    payFrequencyLabel(period.schedule.frequency)
                                }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ formatDate(period.start_date) }}
                            <span class="text-muted-foreground">→</span>
                            {{ formatDate(period.end_date) }}
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ formatDate(period.payment_date) }}
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ period.runs_count ?? '—' }}
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                :variant="
                                    payrollPeriodStatusVariant(period.status)
                                "
                            >
                                {{ payrollPeriodStatusLabel(period.status) }}
                            </StatusBadge>
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ formatMoney(period.totals?.earnings ?? null) }}
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ formatMoney(period.totals?.deductions ?? null) }}
                        </td>
                        <td
                            class="px-3 py-2.5 text-right font-medium whitespace-nowrap tabular-nums"
                        >
                            {{ formatMoney(period.totals?.net ?? null) }}
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-end">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link
                                        :href="`/payroll/periods/${period.id}`"
                                        :aria-label="`Ver periodo del ${formatDate(period.start_date)} al ${formatDate(period.end_date)}`"
                                    >
                                        <Eye aria-hidden="true" />
                                        Ver
                                    </Link>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </template>

            <template #empty>
                <EmptyState
                    :icon="CalendarRange"
                    title="Sin periodos de pre-nómina"
                    :description="
                        hasFilters
                            ? 'No hay periodos con los filtros aplicados. Ajusta el esquema, el estado o el rango de fechas.'
                            : 'Crea el primer periodo para calcular la pre-nómina por sucursal.'
                    "
                >
                    <template v-if="props.can.calculate" #action>
                        <Button @click="openCreate">
                            <Plus aria-hidden="true" />
                            Nuevo periodo
                        </Button>
                    </template>
                </EmptyState>
            </template>

            <Pagination
                :links="props.periods.links"
                :meta="props.periods.meta ?? null"
            />
        </DataTable>

        <Dialog :open="formOpen" @update:open="handleFormOpen">
            <DialogScrollContent class="sm:max-w-lg">
                <DialogHeader class="space-y-2">
                    <DialogTitle>Nuevo periodo de pre-nómina</DialogTitle>
                    <DialogDescription>
                        Define el esquema de pago y el rango de fechas. El
                        servidor valida que no exista otro periodo con el mismo
                        inicio en el esquema.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitForm">
                    <FormField
                        label="Esquema de pago"
                        for="payroll-form-schedule"
                        :error="form.errors.payroll_schedule_id"
                        required
                    >
                        <Select v-model="form.payroll_schedule_id">
                            <SelectTrigger
                                id="payroll-form-schedule"
                                class="w-full"
                            >
                                <SelectValue
                                    placeholder="Selecciona el esquema"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in scheduleOptions"
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
                            for="payroll-form-start"
                            :error="form.errors.start_date"
                            required
                        >
                            <Input
                                id="payroll-form-start"
                                v-model="form.start_date"
                                type="date"
                            />
                        </FormField>

                        <FormField
                            label="Fecha final"
                            for="payroll-form-end"
                            :error="form.errors.end_date"
                            required
                        >
                            <Input
                                id="payroll-form-end"
                                v-model="form.end_date"
                                type="date"
                            />
                        </FormField>
                    </div>

                    <p
                        v-if="dateRangeError"
                        class="text-sm text-destructive-strong"
                        role="alert"
                    >
                        {{ dateRangeError }}
                    </p>

                    <FormField
                        label="Día de pago"
                        for="payroll-form-payment"
                        :error="form.errors.payment_date"
                        hint="Fecha en que se pagaría el periodo; no genera dispersión."
                        required
                    >
                        <Input
                            id="payroll-form-payment"
                            v-model="form.payment_date"
                            type="date"
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
                        <Button
                            type="submit"
                            :disabled="
                                form.processing || dateRangeError !== null
                            "
                        >
                            <Spinner v-if="form.processing" />
                            Crear periodo
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
