<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarDays,
    ClipboardClock,
    Eye,
    RotateCcw,
    Search,
    Timer,
    Users,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatCard from '@/components/StatCard.vue';
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
import { usePermissions } from '@/composables/useNavigation';
import { formatDate, formatMinutesAsHours, formatTime } from '@/lib/format';
import {
    attendanceStatusLabel,
    attendanceStatusVariant,
    incidentTypeLabel,
    incidentTypeVariant,
} from '@/lib/labels';
import { toSelectOptions, filterValue } from '@/lib/options';
import type { AttendanceIndexProps, AttendanceRow } from '@/types';

const props = defineProps<AttendanceIndexProps>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Asistencias', href: '/attendance' }],
    },
});

const { filters, isFiltering, apply, applyDebounced, reset } = useListFilters(
    '/attendance',
    {
        from: props.filters.from ?? '',
        to: props.filters.to ?? '',
        branch_id: filterValue(props.filters.branch_id),
        employee_id: filterValue(props.filters.employee_id),
        search: props.filters.search ?? '',
    },
);

watch(() => filters.search, applyDebounced);

const branchOptions = computed(() => toSelectOptions(props.options?.branches));

const { hasPermission } = usePermissions();
const canRecord = computed(() => hasPermission('attendance.record'));

const branchModel = computed({
    get: () => (filters.branch_id === '' ? 'all' : filters.branch_id),
    set: (value: string) => {
        filters.branch_id = value === 'all' ? '' : value;
        apply();
    },
});

const hasFilters = computed(
    () =>
        filters.from !== '' ||
        filters.to !== '' ||
        filters.branch_id !== '' ||
        filters.employee_id !== '' ||
        filters.search !== '',
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
    const start = formatTime(
        row.break_start,
        row.branch?.timezone ?? undefined,
    );
    const end = formatTime(row.break_end, row.branch?.timezone ?? undefined);

    if (start === '—' && end === '—') {
        return '—';
    }

    return `${start} – ${end}`;
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Asistencias" />

        <PageHeader
            title="Asistencias"
            description="Días trabajados por colaborador con horario programado, marcajes, retardos y horas extra. Los cálculos los determina el backend."
        >
            <template #actions>
                <Button v-if="props.can.incidents" variant="outline" as-child>
                    <Link href="/incidents">
                        <AlertTriangle aria-hidden="true" />
                        Incidencias
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link href="/my/attendance">
                        <CalendarDays aria-hidden="true" />
                        Mi asistencia
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <FlashAlert />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <StatCard
                label="Colaboradores"
                :value="props.summary.employees ?? 0"
                :icon="Users"
                hint="Con registros en el rango"
            />
            <StatCard
                label="Retardos"
                :value="props.summary.late_count ?? 0"
                :icon="Timer"
                hint="Días con retardo"
            />
            <StatCard
                label="Faltas"
                :value="props.summary.absence_count ?? 0"
                :icon="AlertTriangle"
                hint="Días sin marcaje"
            />
            <StatCard
                label="Tiempo trabajado"
                :value="formatMinutesAsHours(props.summary.worked_minutes)"
                :icon="ClipboardClock"
                hint="Suma del rango"
            />
            <StatCard
                label="Horas extra"
                :value="formatMinutesAsHours(props.summary.overtime_minutes)"
                :icon="CalendarDays"
                hint="Suma del rango"
            />
        </div>

        <DataTable
            :empty="props.rows.data.length === 0"
            :loading="isFiltering"
            caption="Días de asistencia por colaborador"
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
                                for="attendance-from"
                                class="text-xs text-muted-foreground"
                                >Desde</Label
                            >
                            <Input
                                id="attendance-from"
                                v-model="filters.from"
                                type="date"
                                class="sm:w-40"
                                @change="apply"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="attendance-to"
                                class="text-xs text-muted-foreground"
                                >Hasta</Label
                            >
                            <Input
                                id="attendance-to"
                                v-model="filters.to"
                                type="date"
                                class="sm:w-40"
                                @change="apply"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="attendance-branch"
                                class="text-xs text-muted-foreground"
                                >Sucursal</Label
                            >
                            <Select v-model="branchModel">
                                <SelectTrigger
                                    id="attendance-branch"
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
                                for="attendance-employee"
                                class="text-xs text-muted-foreground"
                                >ID de colaborador</Label
                            >
                            <Input
                                id="attendance-employee"
                                v-model="filters.employee_id"
                                type="number"
                                min="1"
                                inputmode="numeric"
                                placeholder="Ej. 12"
                                class="sm:w-32"
                                @change="apply"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="attendance-search"
                                class="text-xs text-muted-foreground"
                                >Buscar</Label
                            >
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="attendance-search"
                                    v-model="filters.search"
                                    type="search"
                                    placeholder="Nombre o número"
                                    class="pl-9 sm:w-52"
                                    autocomplete="off"
                                />
                            </div>
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
                        <th scope="col" class="px-3 py-2.5 text-right">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in props.rows.data"
                        :key="`${row.employee.id}-${row.work_date}-${index}`"
                        class="border-t border-border align-middle hover:bg-muted/30"
                    >
                        <td class="px-3 py-2.5">
                            <span class="block font-medium text-foreground">
                                {{ row.employee.full_name }}
                            </span>
                            <span
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ row.employee.employee_number }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ row.branch?.name ?? '—' }}
                        </td>
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
                            {{
                                formatTime(
                                    row.clock_in,
                                    row.branch?.timezone ?? undefined,
                                )
                            }}
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ breakLabel(row) }}
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{
                                formatTime(
                                    row.clock_out,
                                    row.branch?.timezone ?? undefined,
                                )
                            }}
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
                                    :title="incidentTypeLabel(incident.type)"
                                >
                                    {{ incidentTypeLabel(incident.type) }}
                                </StatusBadge>
                            </div>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link
                                        :href="`/attendance/employee/${row.employee.id}`"
                                        :aria-label="`Ver detalle de ${row.employee.full_name}`"
                                    >
                                        <Eye aria-hidden="true" />
                                        Ver detalle
                                    </Link>
                                </Button>
                                <Button
                                    v-if="props.can.incidents"
                                    variant="ghost"
                                    size="icon-sm"
                                    as-child
                                >
                                    <Link
                                        :href="`/incidents?employee_id=${row.employee.id}`"
                                        :aria-label="`Ver incidencias de ${row.employee.full_name}`"
                                    >
                                        <AlertTriangle aria-hidden="true" />
                                    </Link>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </template>

            <template #empty>
                <EmptyState
                    :icon="ClipboardClock"
                    title="Sin días de asistencia"
                    :description="
                        hasFilters
                            ? 'No hay registros con los filtros aplicados. Ajusta el rango de fechas o la sucursal.'
                            : 'Aún no hay asistencias registradas. Los marcajes del checador aparecerán aquí.'
                    "
                >
                    <template #action>
                        <Button variant="outline" as-child>
                            <Link href="/kiosk">
                                <ClipboardClock aria-hidden="true" />
                                Ir al checador
                            </Link>
                        </Button>
                    </template>
                </EmptyState>
            </template>

            <Pagination
                :links="props.rows.links"
                :meta="props.rows.meta ?? null"
            />
        </DataTable>
    </div>
</template>
