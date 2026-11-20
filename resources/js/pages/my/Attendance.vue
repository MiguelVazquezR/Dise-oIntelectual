<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarCheck,
    CalendarDays,
    ClipboardClock,
    Plane,
    Timer,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/useNavigation';
import { formatDate, formatMinutesAsHours, formatTime } from '@/lib/format';
import {
    attendanceStatusLabel,
    attendanceStatusVariant,
    incidentTypeLabel,
    incidentTypeVariant,
} from '@/lib/labels';
import type { AttendanceRow, MyAttendanceProps } from '@/types';

const props = defineProps<MyAttendanceProps>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mi asistencia', href: '/my/attendance' }],
    },
});

const timezone = computed<string | undefined>(
    () => props.employee?.branch?.timezone ?? undefined,
);

const { hasPermission } = usePermissions();
const canRecord = computed(() => hasPermission('attendance.record'));

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
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Mi asistencia" />

        <PageHeader
            title="Mi asistencia"
            description="Consulta de solo lectura de tus días trabajados. Si detectas una diferencia, contacta a recursos humanos."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/my/vacations">
                        <Plane aria-hidden="true" />
                        Mis vacaciones
                    </Link>
                </Button>
                <Button v-if="canRecord" variant="outline" as-child>
                    <Link href="/kiosk">
                        <ClipboardClock aria-hidden="true" />
                        Ir al checador
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <FlashAlert />

        <EmptyState
            v-if="!props.employee"
            :icon="UserRound"
            title="Tu cuenta no está vinculada a un colaborador"
            description="Para consultar tu asistencia, recursos humanos debe vincular tu usuario con tu expediente de colaborador. Mientras tanto, usa el checador con tu número de empleado y PIN."
        >
            <template #action>
                <Button v-if="canRecord" variant="outline" as-child>
                    <Link href="/kiosk">
                        <ClipboardClock aria-hidden="true" />
                        Ir al checador
                    </Link>
                </Button>
            </template>
        </EmptyState>

        <template v-else>
            <section
                class="rounded-lg border border-border bg-card p-4"
                aria-labelledby="my-attendance-employee"
            >
                <h2
                    id="my-attendance-employee"
                    class="text-sm font-semibold text-foreground"
                >
                    {{ props.employee.full_name }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ props.employee.employee_number }} ·
                    {{ props.employee.branch?.name ?? 'Sin sucursal' }}
                </p>
                <p
                    v-if="props.period"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    Periodo consultado: {{ formatDate(props.period.from) }} —
                    {{ formatDate(props.period.to) }}
                </p>
            </section>

            <div
                v-if="props.totals"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5"
            >
                <StatCard
                    label="Tiempo trabajado"
                    :value="formatMinutesAsHours(props.totals.worked_minutes)"
                    :icon="ClipboardClock"
                    hint="Total del periodo"
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
                    hint="Total del periodo"
                />
                <StatCard
                    label="Faltas"
                    :value="props.totals.absences ?? 0"
                    :icon="AlertTriangle"
                    hint="Días registrados"
                />
                <StatCard
                    label="Vacaciones"
                    :value="props.totals.vacations ?? 0"
                    :icon="CalendarDays"
                    hint="Días en el periodo"
                />
            </div>

            <DataTable
                :empty="props.days.length === 0"
                caption="Mis días de asistencia"
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
                                <span class="font-mono text-xs">
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
                        title="Sin días registrados"
                        description="Aún no hay asistencias en el periodo consultado. Si crees que es un error, contacta a recursos humanos."
                    />
                </template>
            </DataTable>
        </template>
    </div>
</template>
