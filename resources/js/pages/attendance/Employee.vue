<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarCheck,
    CalendarDays,
    ClipboardClock,
    Timer,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatMinutesAsHours, formatTime } from '@/lib/format';
import {
    attendanceStatusLabel,
    attendanceStatusVariant,
    incidentTypeLabel,
    incidentTypeVariant,
} from '@/lib/labels';
import type { AttendanceEmployeeProps, AttendanceRow } from '@/types';

const props = defineProps<AttendanceEmployeeProps>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Asistencias', href: '/attendance' },
            { title: 'Detalle por colaborador', href: '/attendance' },
        ],
    },
});

const rangeFrom = ref(props.period?.from ?? '');
const rangeTo = ref(props.period?.to ?? '');
const isRefreshing = ref(false);

const rangeError = computed(() => {
    if (rangeFrom.value === '' || rangeTo.value === '') {
        return null;
    }

    return rangeFrom.value > rangeTo.value
        ? 'La fecha inicial no puede ser posterior a la final.'
        : null;
});

const hasDays = computed(() => props.days.length > 0);

const timezone = computed<string | undefined>(
    () => props.employee.branch?.timezone ?? undefined,
);

function scheduleLabel(row: AttendanceRow): string {
    if (!row.scheduled) {
        return 'Sin horario';
    }

    if (row.scheduled.is_rest_day) {
        return 'Descanso';
    }

    if (!row.scheduled.start && !row.scheduled.end) {
        return 'Sin horario';
    }

    return `${row.scheduled.start ?? '—'} – ${row.scheduled.end ?? '—'}`;
}

function breakLabel(row: AttendanceRow): string {
    const start = formatTime(row.break_start, timezone.value);
    const end = formatTime(row.break_end, timezone.value);

    if (start === '—' && end === '—') {
        return '—';
    }

    return `${start} – ${end}`;
}

function applyRange(): void {
    if (rangeError.value !== null) {
        return;
    }

    router.get(
        `/attendance/employee/${props.employee.id}`,
        {
            from: rangeFrom.value,
            to: rangeTo.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                isRefreshing.value = true;
            },
            onFinish: () => {
                isRefreshing.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head :title="`Asistencia de ${props.employee.full_name}`" />

        <PageHeader
            :title="props.employee.full_name"
            :description="`${props.employee.employee_number} · ${props.employee.branch?.name ?? 'Sin sucursal'}`"
        >
            <template #actions>
                <Button v-if="props.can.incidents" variant="outline" as-child>
                    <Link :href="`/incidents?employee_id=${props.employee.id}`">
                        <AlertTriangle aria-hidden="true" />
                        Incidencias
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="`/employees/${props.employee.id}`">
                        <UserRound aria-hidden="true" />
                        Ficha del colaborador
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link href="/attendance">
                        <ArrowLeft aria-hidden="true" />
                        Volver al listado
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <FlashAlert />

        <section
            class="rounded-lg border border-border bg-card p-4"
            aria-labelledby="attendance-range-title"
        >
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <h2
                        id="attendance-range-title"
                        class="text-sm font-semibold text-foreground"
                    >
                        Rango consultado
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ formatDate(props.period?.from) }} —
                        {{ formatDate(props.period?.to) }}
                    </p>
                </div>

                <form
                    class="flex flex-col gap-3 sm:flex-row sm:items-end"
                    @submit.prevent="applyRange"
                >
                    <div class="grid gap-1.5">
                        <Label
                            for="attendance-detail-from"
                            class="text-xs text-muted-foreground"
                            >Desde</Label
                        >
                        <Input
                            id="attendance-detail-from"
                            v-model="rangeFrom"
                            type="date"
                            class="sm:w-40"
                        />
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="attendance-detail-to"
                            class="text-xs text-muted-foreground"
                            >Hasta</Label
                        >
                        <Input
                            id="attendance-detail-to"
                            v-model="rangeTo"
                            type="date"
                            class="sm:w-40"
                        />
                    </div>

                    <Button
                        type="submit"
                        :disabled="isRefreshing || rangeError !== null"
                    >
                        <Spinner v-if="isRefreshing" />
                        <CalendarDays v-else aria-hidden="true" />
                        Aplicar rango
                    </Button>
                </form>
            </div>

            <InputError :message="rangeError ?? undefined" class="mt-2" />
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <StatCard
                label="Tiempo trabajado"
                :value="formatMinutesAsHours(props.totals.worked_minutes)"
                :icon="ClipboardClock"
                hint="Total del rango"
            />
            <StatCard
                label="Retardos"
                :value="formatMinutesAsHours(props.totals.late_minutes)"
                :icon="Timer"
                hint="Minutos acumulados"
            />
            <StatCard
                label="Horas extra"
                :value="formatMinutesAsHours(props.totals.overtime_minutes)"
                :icon="CalendarCheck"
                hint="Total del rango"
            />
            <StatCard
                label="Faltas"
                :value="props.totals.absences ?? 0"
                :icon="AlertTriangle"
                hint="Días sin marcaje"
            />
            <StatCard
                label="Vacaciones"
                :value="props.totals.vacations ?? 0"
                :icon="CalendarDays"
                hint="Días en el rango"
            />
        </div>

        <DataTable
            :empty="!hasDays"
            caption="Días de asistencia del colaborador"
        >
            <template #body>
                <thead
                    class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th scope="col" class="px-3 py-2.5">Día</th>
                        <th scope="col" class="px-3 py-2.5">Horario</th>
                        <th scope="col" class="px-3 py-2.5">Entrada</th>
                        <th scope="col" class="px-3 py-2.5">Descansos</th>
                        <th scope="col" class="px-3 py-2.5">Salida</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Trabajado
                        </th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Retardo
                        </th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Extra
                        </th>
                        <th scope="col" class="px-3 py-2.5">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in props.days"
                        :key="`${row.work_date}-${index}`"
                        class="border-t border-border align-middle hover:bg-muted/30"
                    >
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ formatDate(row.work_date) }}
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            <span
                                class="font-mono text-xs text-foreground"
                                :title="
                                    row.scheduled?.tolerance_minutes
                                        ? `Tolerancia: ${row.scheduled.tolerance_minutes} min`
                                        : undefined
                                "
                            >
                                {{ scheduleLabel(row) }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ formatTime(row.clock_in, timezone) }}
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ breakLabel(row) }}
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ formatTime(row.clock_out, timezone) }}
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ formatMinutesAsHours(row.worked_minutes) }}
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ formatMinutesAsHours(row.late_minutes) }}
                        </td>
                        <td
                            class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ formatMinutesAsHours(row.overtime_minutes) }}
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex flex-wrap items-center gap-1">
                                <StatusBadge
                                    :variant="
                                        attendanceStatusVariant(row.status)
                                    "
                                >
                                    {{ attendanceStatusLabel(row.status) }}
                                </StatusBadge>
                                <StatusBadge
                                    v-for="incident in row.incidents"
                                    :key="incident.id"
                                    :variant="
                                        incidentTypeVariant(incident.type)
                                    "
                                >
                                    {{ incidentTypeLabel(incident.type) }}
                                </StatusBadge>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </template>

            <template #empty>
                <EmptyState
                    :icon="CalendarDays"
                    title="Sin días en el rango"
                    description="Ajusta las fechas para consultar otro periodo de este colaborador."
                />
            </template>
        </DataTable>
    </div>
</template>
