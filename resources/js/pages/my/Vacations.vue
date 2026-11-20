<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarCheck, ClipboardClock, Plane, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/useNavigation';
import { formatDate, formatDateTime, formatDays } from '@/lib/format';
import {
    vacationPeriodStatusLabel,
    vacationPeriodStatusVariant,
    vacationRequestStatusLabel,
    vacationRequestStatusVariant,
} from '@/lib/labels';
import type { MyVacationsProps } from '@/types';

const props = defineProps<MyVacationsProps>();

const { hasPermission } = usePermissions();
const canRecord = computed(() => hasPermission('attendance.record'));

/** El backend envía `[]` cuando no hay vínculo y un paginado cuando lo hay. */
const requestRows = computed(() => {
    const payload = props.requests;

    if (!payload) {
        return [];
    }

    return Array.isArray(payload) ? payload : (payload.data ?? []);
});

const requestLinks = computed(() => {
    const payload = props.requests;

    return Array.isArray(payload) || !payload ? [] : payload.links;
});

const requestMeta = computed(() => {
    const payload = props.requests;

    return Array.isArray(payload) || !payload ? null : (payload.meta ?? null);
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mis vacaciones', href: '/my/vacations' }],
    },
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Mis vacaciones" />

        <PageHeader
            title="Mis vacaciones"
            description="Consulta de solo lectura de tus periodos y días disponibles. Las solicitudes se registran con recursos humanos."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/my/attendance">
                        <CalendarCheck aria-hidden="true" />
                        Mi asistencia
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
            :description="
                props.notice ??
                'Para consultar tus vacaciones, recursos humanos debe vincular tu usuario con tu expediente de colaborador. Mientras tanto, puedes revisar tus marcajes en el checador.'
            "
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
                aria-labelledby="my-vacations-employee"
            >
                <h2
                    id="my-vacations-employee"
                    class="text-sm font-semibold text-foreground"
                >
                    {{ props.employee.full_name }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ props.employee.employee_number }}
                    <span v-if="props.employee.branch">
                        · {{ props.employee.branch.name }}
                    </span>
                    <span v-if="props.employee.hire_date">
                        · Ingreso: {{ formatDate(props.employee.hire_date) }}
                    </span>
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
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            >
                <StatCard
                    label="Días otorgados"
                    :value="formatDays(props.totals.days_granted)"
                    :icon="Plane"
                    hint="Según tu antigüedad"
                />
                <StatCard
                    label="Días tomados"
                    :value="formatDays(props.totals.days_taken)"
                    :icon="CalendarCheck"
                    hint="Solicitudes aprobadas"
                />
                <StatCard
                    label="Días pendientes"
                    :value="formatDays(props.totals.days_pending)"
                    :icon="CalendarCheck"
                    hint="Solicitudes por aprobar"
                />
                <StatCard
                    label="Días disponibles"
                    :value="formatDays(props.totals.days_available ?? null)"
                    :icon="Plane"
                    hint="Saldo disponible"
                />
            </div>

            <section class="space-y-3" aria-labelledby="my-vacations-periods">
                <h2
                    id="my-vacations-periods"
                    class="text-sm font-semibold text-foreground"
                >
                    Mis periodos
                </h2>

                <DataTable
                    :empty="props.periods.length === 0"
                    caption="Mis periodos de vacaciones"
                >
                    <template #body>
                        <thead
                            class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            <tr>
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
                                v-for="period in props.periods"
                                :key="period.id"
                                class="border-t border-border align-middle hover:bg-muted/30"
                            >
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
                                    class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ formatDays(period.days_pending) }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right font-medium whitespace-nowrap tabular-nums"
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
                            description="Recursos humanos aún no ha generado tus periodos por aniversario. Consulta con el área si tienes dudas sobre tu antigüedad."
                        />
                    </template>
                </DataTable>
            </section>

            <section
                v-if="props.requests"
                class="space-y-3"
                aria-labelledby="my-vacations-requests"
            >
                <h2
                    id="my-vacations-requests"
                    class="text-sm font-semibold text-foreground"
                >
                    Mis solicitudes
                </h2>

                <DataTable
                    :empty="requestRows.length === 0"
                    caption="Mis solicitudes de vacaciones"
                >
                    <template #body>
                        <thead
                            class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            <tr>
                                <th scope="col" class="px-3 py-2.5">
                                    Periodo solicitado
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-right">
                                    Días
                                </th>
                                <th scope="col" class="px-3 py-2.5">Estado</th>
                                <th scope="col" class="px-3 py-2.5">
                                    Decisión
                                </th>
                                <th scope="col" class="px-3 py-2.5">Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="request in requestRows"
                                :key="request.id"
                                class="border-t border-border align-middle hover:bg-muted/30"
                            >
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
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    {{
                                        request.decided_at
                                            ? formatDateTime(request.decided_at)
                                            : 'Sin decisión'
                                    }}
                                </td>
                                <td class="max-w-[16rem] px-3 py-2.5">
                                    <span
                                        class="block truncate text-muted-foreground"
                                        :title="request.notes ?? undefined"
                                    >
                                        {{ request.notes ?? '—' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </template>

                    <template #empty>
                        <EmptyState
                            :icon="CalendarCheck"
                            title="Sin solicitudes registradas"
                            description="Aún no has solicitado vacaciones. Recursos humanos puede registrar la solicitud a tu nombre."
                        />
                    </template>

                    <Pagination :links="requestLinks" :meta="requestMeta" />
                </DataTable>
            </section>
        </template>
    </div>
</template>
