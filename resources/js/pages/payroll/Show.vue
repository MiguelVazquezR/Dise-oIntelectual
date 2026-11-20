<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BadgeCheck,
    Calculator,
    CircleDollarSign,
    Info,
    Lock,
    Receipt,
    TrendingDown,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import {
    payFrequencyLabel,
    payrollPeriodStatusLabel,
    payrollPeriodStatusVariant,
    payrollRunStatusLabel,
    payrollRunStatusVariant,
} from '@/lib/labels';
import type { PayrollShowProps } from '@/types';

type PayrollAction = 'calculate' | 'review' | 'close';

const props = defineProps<PayrollShowProps>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Nómina', href: '/payroll/periods' },
            { title: 'Detalle del periodo', href: '/payroll/periods' },
        ],
    },
});

const isClosed = computed(() => props.period.status === 'closed');

/** D-14: aviso permanente; el backend envía además su propio `notice`. */
const DEFAULT_NOTICE =
    'Pre-nómina para revisión; no constituye CFDI ni cálculo fiscal definitivo.';

const backendNotice = computed(() => props.notice?.trim() ?? '');

const isReadOnly = computed(
    () =>
        isClosed.value ||
        (!props.can.calculate && !props.can.review && !props.can.close),
);

const periodDescription = computed(() => {
    const schedule = props.period.schedule?.name ?? 'Esquema sin nombre';
    const frequency = props.period.schedule?.frequency
        ? ` · ${payFrequencyLabel(props.period.schedule.frequency)}`
        : '';

    return `${schedule}${frequency} · Pago el ${formatDate(props.period.payment_date)}`;
});

const periodTitle = computed(
    () =>
        `Pre-nómina ${formatDate(props.period.start_date)} — ${formatDate(props.period.end_date)}`,
);

const employeesCount = computed(() => {
    const explicit = props.period.items_count;

    if (explicit !== null && explicit !== undefined) {
        return Number(explicit);
    }

    return props.runs.reduce(
        (total, run) => total + Number(run.items_count ?? 0),
        0,
    );
});

const receiptItems = computed(() => props.items ?? []);

const closedAt = computed(
    () => props.period.closed_at ?? props.closed_snapshot_at ?? null,
);

/* Acciones del periodo */
const actionProcessing = ref<PayrollAction | null>(null);
const recalculateOpen = ref(false);
const closeOpen = ref(false);

function startAction(action: PayrollAction): boolean {
    if (actionProcessing.value !== null || isClosed.value) {
        return false;
    }

    actionProcessing.value = action;

    return true;
}

function calculate(): void {
    if (props.period.status === 'reviewed') {
        recalculateOpen.value = true;

        return;
    }

    runCalculate();
}

function runCalculate(force = false): void {
    if (!startAction('calculate')) {
        return;
    }

    // `force` es obligatorio para recalcular un periodo ya revisado (§7.4).
    router.post(
        `/payroll/periods/${props.period.id}/calculate`,
        force ? { force: true } : {},
        {
            preserveScroll: true,
            onFinish: () => {
                actionProcessing.value = null;
                recalculateOpen.value = false;
            },
        },
    );
}

function review(): void {
    if (!startAction('review')) {
        return;
    }

    router.post(
        `/payroll/periods/${props.period.id}/review`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                actionProcessing.value = null;
            },
        },
    );
}

function closePeriod(): void {
    if (!startAction('close')) {
        return;
    }

    router.post(
        `/payroll/periods/${props.period.id}/close`,
        { confirm: true },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeOpen.value = false;
            },
            onFinish: () => {
                actionProcessing.value = null;
            },
        },
    );
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head
            :title="`Pre-nómina ${formatDate(props.period.start_date)} — ${formatDate(props.period.end_date)}`"
        />

        <PageHeader :title="periodTitle" :description="periodDescription">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/payroll/periods">
                        <ArrowLeft aria-hidden="true" />
                        Volver al listado
                    </Link>
                </Button>

                <Button
                    v-if="props.can.close"
                    variant="outline"
                    :disabled="isClosed || actionProcessing !== null"
                    @click="closeOpen = true"
                >
                    <Lock aria-hidden="true" />
                    Cerrar periodo
                </Button>

                <Button
                    v-if="props.can.review"
                    variant="outline"
                    :disabled="isClosed || actionProcessing !== null"
                    @click="review"
                >
                    <Spinner v-if="actionProcessing === 'review'" />
                    <BadgeCheck v-else aria-hidden="true" />
                    Marcar como revisada
                </Button>

                <Button
                    v-if="props.can.calculate"
                    :disabled="isClosed || actionProcessing !== null"
                    @click="calculate"
                >
                    <Spinner v-if="actionProcessing === 'calculate'" />
                    <Calculator v-else aria-hidden="true" />
                    {{
                        props.period.status === 'open'
                            ? 'Calcular'
                            : 'Recalcular'
                    }}
                </Button>
            </template>
        </PageHeader>

        <FlashAlert />

        <div
            class="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-card px-4 py-3"
        >
            <StatusBadge
                :variant="payrollPeriodStatusVariant(props.period.status)"
            >
                {{ payrollPeriodStatusLabel(props.period.status) }}
            </StatusBadge>
            <p class="text-sm text-muted-foreground">
                {{ props.runs.length }}
                {{ props.runs.length === 1 ? 'sucursal' : 'sucursales' }} ·
                {{ employeesCount }}
                {{ employeesCount === 1 ? 'colaborador' : 'colaboradores' }}
            </p>
            <p
                v-if="props.closed_snapshot_at"
                class="ml-auto text-xs text-muted-foreground"
            >
                Snapshot congelado el
                {{ formatDateTime(props.closed_snapshot_at) }}
            </p>
        </div>

        <p
            v-if="isClosed"
            role="note"
            class="flex items-start gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
        >
            <Lock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                Periodo cerrado
                <template v-if="closedAt">
                    el {{ formatDateTime(closedAt) }}
                </template>
                . Vista de solo lectura: el snapshot de la pre-nómina está
                congelado y no se puede recalcular.
            </span>
        </p>

        <p
            v-else-if="isReadOnly"
            role="note"
            class="flex items-start gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                Solo lectura: no tienes permiso para calcular, revisar ni cerrar
                este periodo.
            </span>
        </p>

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

        <section
            aria-label="Totales del periodo"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <StatCard
                label="Percepciones"
                :value="formatMoney(props.totals?.earnings ?? null)"
                :icon="Wallet"
                hint="Control interno, sin prestaciones fiscales"
            />
            <StatCard
                label="Descuentos"
                :value="formatMoney(props.totals?.deductions ?? null)"
                :icon="TrendingDown"
            />
            <StatCard
                label="Neto del periodo"
                :value="formatMoney(props.totals?.net ?? null)"
                :icon="CircleDollarSign"
                hint="Redondeo a 2 decimales al presentar (S-05)"
            />
            <StatCard
                label="Colaboradores calculados"
                :value="employeesCount"
                :icon="Users"
            />
        </section>

        <section class="space-y-3" aria-labelledby="payroll-runs">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2
                        id="payroll-runs"
                        class="text-base font-semibold text-foreground"
                    >
                        Cálculo por sucursal
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Cada sucursal se calcula y revisa de forma independiente
                        dentro del periodo.
                    </p>
                </div>
            </div>

            <DataTable
                :empty="props.runs.length === 0"
                caption="Runs de pre-nómina por sucursal"
            >
                <template #body>
                    <thead
                        class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th scope="col" class="px-3 py-2.5">Sucursal</th>
                            <th scope="col" class="px-3 py-2.5 text-right">
                                Colaboradores
                            </th>
                            <th scope="col" class="px-3 py-2.5 text-right">
                                Percepciones
                            </th>
                            <th scope="col" class="px-3 py-2.5 text-right">
                                Descuentos
                            </th>
                            <th scope="col" class="px-3 py-2.5 text-right">
                                Neto
                            </th>
                            <th scope="col" class="px-3 py-2.5">Estado</th>
                            <th scope="col" class="px-3 py-2.5">Calculado</th>
                            <th scope="col" class="px-3 py-2.5">Revisado</th>
                            <th scope="col" class="px-3 py-2.5">Cerrado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="run in props.runs"
                            :key="run.id"
                            class="border-t border-border align-middle hover:bg-muted/30"
                        >
                            <td
                                class="px-3 py-2.5 font-medium whitespace-nowrap"
                            >
                                {{ run.branch?.name ?? '—' }}
                            </td>
                            <td
                                class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ run.items_count ?? '—' }}
                            </td>
                            <td
                                class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ formatMoney(run.totals?.earnings ?? null) }}
                            </td>
                            <td
                                class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                            >
                                {{
                                    formatMoney(run.totals?.deductions ?? null)
                                }}
                            </td>
                            <td
                                class="px-3 py-2.5 text-right font-medium whitespace-nowrap tabular-nums"
                            >
                                {{ formatMoney(run.totals?.net ?? null) }}
                            </td>
                            <td class="px-3 py-2.5">
                                <StatusBadge
                                    :variant="
                                        payrollRunStatusVariant(run.status)
                                    "
                                >
                                    {{ payrollRunStatusLabel(run.status) }}
                                </StatusBadge>
                            </td>
                            <td
                                class="px-3 py-2.5 text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{
                                    run.calculated_at
                                        ? formatDateTime(run.calculated_at)
                                        : '—'
                                }}
                            </td>
                            <td
                                class="px-3 py-2.5 text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{
                                    run.reviewed_at
                                        ? formatDateTime(run.reviewed_at)
                                        : '—'
                                }}
                            </td>
                            <td
                                class="px-3 py-2.5 text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{
                                    run.closed_at
                                        ? formatDateTime(run.closed_at)
                                        : '—'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </template>

                <template #empty>
                    <EmptyState
                        :icon="Calculator"
                        title="Periodo sin cálculo"
                        description="Ejecuta Calcular para generar los runs por sucursal y los recibos de cada colaborador."
                    >
                        <template
                            v-if="props.can.calculate && !isClosed"
                            #action
                        >
                            <Button
                                :disabled="actionProcessing !== null"
                                @click="calculate"
                            >
                                <Spinner
                                    v-if="actionProcessing === 'calculate'"
                                />
                                <Calculator v-else aria-hidden="true" />
                                Calcular periodo
                            </Button>
                        </template>
                    </EmptyState>
                </template>

                <div
                    v-if="props.totals"
                    class="flex flex-wrap items-center justify-end gap-x-6 gap-y-1 text-sm"
                >
                    <span class="text-muted-foreground">
                        Percepciones:
                        <strong class="font-medium text-foreground">{{
                            formatMoney(props.totals.earnings)
                        }}</strong>
                    </span>
                    <span class="text-muted-foreground">
                        Descuentos:
                        <strong class="font-medium text-foreground">{{
                            formatMoney(props.totals.deductions)
                        }}</strong>
                    </span>
                    <span class="text-muted-foreground">
                        Neto:
                        <strong class="font-medium text-foreground">{{
                            formatMoney(props.totals.net)
                        }}</strong>
                    </span>
                </div>
            </DataTable>
        </section>

        <section
            v-if="receiptItems.length > 0"
            class="space-y-3"
            aria-labelledby="payroll-receipts"
        >
            <div>
                <h2
                    id="payroll-receipts"
                    class="text-base font-semibold text-foreground"
                >
                    Recibos por colaborador
                </h2>
                <p class="text-sm text-muted-foreground">
                    Documento preliminar imprimible; no constituye CFDI.
                </p>
            </div>

            <DataTable
                :empty="false"
                caption="Recibos de pre-nómina por colaborador"
            >
                <template #body>
                    <thead
                        class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th scope="col" class="px-3 py-2.5">Colaborador</th>
                            <th scope="col" class="px-3 py-2.5">Sucursal</th>
                            <th scope="col" class="px-3 py-2.5 text-right">
                                Neto
                            </th>
                            <th scope="col" class="px-3 py-2.5 text-right">
                                <span class="sr-only">Recibo</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in receiptItems"
                            :key="item.id"
                            class="border-t border-border align-middle hover:bg-muted/30"
                        >
                            <td class="px-3 py-2.5">
                                <span class="block font-medium text-foreground">
                                    {{ item.employee.full_name }}
                                </span>
                                <span
                                    v-if="item.employee.employee_number"
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ item.employee.employee_number }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                {{ item.branch?.name ?? '—' }}
                            </td>
                            <td
                                class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                            >
                                {{
                                    formatMoney(
                                        item.totals?.net ??
                                            item.net_amount ??
                                            null,
                                    )
                                }}
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex justify-end">
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link
                                            :href="`/payroll/items/${item.id}/receipt`"
                                            :aria-label="`Ver recibo de ${item.employee.full_name}`"
                                        >
                                            <Receipt aria-hidden="true" />
                                            Ver recibo
                                        </Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </template>
            </DataTable>
        </section>

        <ConfirmDialog
            :open="recalculateOpen"
            title="Recalcular periodo revisado"
            description="Este periodo ya fue marcado como revisado. Recalcular reemplaza los conceptos e importes calculados y deja traza de la operación; confirma solo si el cambio es intencional."
            confirm-label="Recalcular"
            :processing="actionProcessing === 'calculate'"
            variant="destructive"
            @update:open="recalculateOpen = $event"
            @confirm="runCalculate(true)"
        />

        <ConfirmDialog
            :open="closeOpen"
            title="Cerrar periodo de pre-nómina"
            description="Al cerrar el periodo se congela el snapshot de la pre-nómina: no se podrá recalcular ni modificar los importes, y los recibos quedan como documento histórico. Esta acción no genera CFDI."
            confirm-label="Cerrar periodo"
            :processing="actionProcessing === 'close'"
            variant="destructive"
            @update:open="closeOpen = $event"
            @confirm="closePeriod"
        />
    </div>
</template>
