<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarCheck,
    KeyRound,
    Pencil,
    Plane,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    formatDate,
    formatDateTime,
    formatDays,
    formatMoney,
} from '@/lib/format';
import {
    attendanceEventLabel,
    attendanceEventVariant,
    attendanceMethodLabel,
    employeeStatusLabel,
    employeeStatusVariant,
    payFrequencyLabel,
} from '@/lib/labels';
import type {
    AttendanceEventSummary,
    EmployeeCompensation,
    EmployeeDetail,
    EmployeePermissions,
    VacationSummary,
} from '@/types';

type Props = {
    employee: EmployeeDetail;
    compensations: EmployeeCompensation[];
    recent_events: AttendanceEventSummary[];
    vacation_summary: VacationSummary;
    can: EmployeePermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Colaboradores', href: '/employees' },
            { title: 'Ficha del colaborador', href: '/employees' },
        ],
    },
});

const fullName = computed(() =>
    [
        props.employee.first_name,
        props.employee.last_name,
        props.employee.second_last_name,
    ]
        .filter((part): part is string => Boolean(part))
        .join(' '),
);

const initials = computed(() =>
    [props.employee.first_name, props.employee.last_name]
        .filter((part): part is string => Boolean(part))
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);
</script>

<template>
    <Head :title="`Ficha de ${fullName}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="fullName" :description="props.employee.position">
            <template #actions>
                <Button v-if="props.can.manage" as-child>
                    <Link :href="`/employees/${props.employee.id}/edit`">
                        <Pencil aria-hidden="true" />
                        Editar colaborador
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link href="/employees">
                        <ArrowLeft aria-hidden="true" />
                        Volver al listado
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section
            class="rounded-lg border border-border bg-card p-5"
            aria-labelledby="employee-details"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                    aria-hidden="true"
                >
                    {{ initials }}
                </span>
                <div class="min-w-0">
                    <h2
                        id="employee-details"
                        class="truncate text-base font-semibold"
                    >
                        {{ fullName }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ props.employee.employee_number }}
                    </p>
                </div>
                <div class="ml-auto">
                    <StatusBadge
                        :variant="employeeStatusVariant(props.employee.status)"
                    >
                        {{ employeeStatusLabel(props.employee.status) }}
                    </StatusBadge>
                </div>
            </div>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-muted-foreground">Puesto</dt>
                    <dd class="text-sm text-foreground">
                        {{ props.employee.position }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Departamento</dt>
                    <dd class="text-sm text-foreground">
                        {{ props.employee.department ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Fecha de ingreso
                    </dt>
                    <dd class="text-sm text-foreground">
                        {{ formatDate(props.employee.hire_date) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Correo</dt>
                    <dd class="text-sm break-all text-foreground">
                        {{ props.employee.email ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Teléfono</dt>
                    <dd class="text-sm text-foreground">
                        {{ props.employee.phone ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">PIN</dt>
                    <dd class="mt-1 flex flex-wrap items-center gap-2">
                        <StatusBadge
                            :variant="
                                props.employee.has_pin ? 'success' : 'warning'
                            "
                        >
                            {{
                                props.employee.has_pin ? 'Asignado' : 'Sin PIN'
                            }}
                        </StatusBadge>
                        <span
                            v-if="props.employee.pin_updated_at"
                            class="text-xs text-muted-foreground"
                        >
                            Actualizado el
                            {{ formatDateTime(props.employee.pin_updated_at) }}
                        </span>
                    </dd>
                </div>
            </dl>

            <p
                v-if="props.can.manage"
                class="mt-4 flex items-start gap-2 rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground"
            >
                <KeyRound class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                El PIN se asigna desde «Editar colaborador». Por seguridad nunca
                se muestra su valor.
            </p>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Días otorgados"
                :value="formatDays(props.vacation_summary.days_granted)"
                :icon="Plane"
                hint="Periodo de vacaciones vigente"
            />
            <StatCard
                label="Días tomados"
                :value="formatDays(props.vacation_summary.days_taken)"
                :icon="CalendarCheck"
                hint="Solicitudes aprobadas"
            />
            <StatCard
                label="Días pendientes"
                :value="formatDays(props.vacation_summary.days_pending)"
                :icon="CalendarCheck"
                hint="Saldo disponible"
            />
            <StatCard
                label="Salario base vigente"
                :value="
                    props.employee.compensation
                        ? formatMoney(props.employee.compensation.base_salary)
                        : '—'
                "
                :icon="Wallet"
                :hint="
                    props.employee.compensation
                        ? payFrequencyLabel(
                              props.employee.compensation.pay_frequency,
                          )
                        : 'Sin compensación registrada'
                "
            />
        </div>

        <section class="space-y-3" aria-labelledby="employee-compensations">
            <h2
                id="employee-compensations"
                class="text-sm font-semibold text-foreground"
            >
                Historial de compensaciones
            </h2>

            <DataTable
                :empty="props.compensations.length === 0"
                caption="Historial de compensaciones del colaborador"
            >
                <template #body>
                    <thead
                        class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th scope="col" class="px-3 py-2.5">Vigencia</th>
                            <th scope="col" class="px-3 py-2.5">
                                Salario base
                            </th>
                            <th scope="col" class="px-3 py-2.5">Frecuencia</th>
                            <th scope="col" class="px-3 py-2.5">Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(compensation, index) in props.compensations"
                            :key="compensation.id ?? index"
                            class="border-t border-border align-middle"
                        >
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                {{ formatDate(compensation.effective_from) }}
                                <span class="text-muted-foreground">→</span>
                                {{
                                    compensation.effective_to
                                        ? formatDate(compensation.effective_to)
                                        : 'Actual'
                                }}
                            </td>
                            <td class="px-3 py-2.5 font-medium tabular-nums">
                                {{ formatMoney(compensation.base_salary) }}
                            </td>
                            <td class="px-3 py-2.5">
                                {{
                                    payFrequencyLabel(
                                        compensation.pay_frequency,
                                    )
                                }}
                            </td>
                            <td class="max-w-[22rem] px-3 py-2.5">
                                <span
                                    class="block truncate text-muted-foreground"
                                    :title="compensation.notes ?? undefined"
                                >
                                    {{ compensation.notes ?? '—' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </template>

                <template #empty>
                    <EmptyState
                        :icon="Wallet"
                        title="Sin historial de compensaciones"
                        description="Captura la compensación inicial desde la edición del colaborador."
                    />
                </template>
            </DataTable>
        </section>

        <section class="space-y-3" aria-labelledby="employee-events">
            <h2
                id="employee-events"
                class="text-sm font-semibold text-foreground"
            >
                Últimos marcajes
            </h2>

            <DataTable
                :empty="props.recent_events.length === 0"
                caption="Últimos marcajes del colaborador"
            >
                <template #body>
                    <thead
                        class="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th scope="col" class="px-3 py-2.5">
                                Fecha y hora
                            </th>
                            <th scope="col" class="px-3 py-2.5">Tipo</th>
                            <th scope="col" class="px-3 py-2.5">Método</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="event in props.recent_events"
                            :key="event.id"
                            class="border-t border-border align-middle"
                        >
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                {{ formatDateTime(event.occurred_at) }}
                            </td>
                            <td class="px-3 py-2.5">
                                <StatusBadge
                                    :variant="
                                        attendanceEventVariant(event.type)
                                    "
                                >
                                    {{ attendanceEventLabel(event.type) }}
                                </StatusBadge>
                            </td>
                            <td class="px-3 py-2.5">
                                <StatusBadge variant="info">
                                    {{ attendanceMethodLabel(event.method) }}
                                </StatusBadge>
                            </td>
                        </tr>
                    </tbody>
                </template>

                <template #empty>
                    <EmptyState
                        :icon="CalendarCheck"
                        title="Sin marcajes registrados"
                        description="Los marcajes del checador (PIN o facial) aparecerán aquí."
                    />
                </template>
            </DataTable>
        </section>
    </div>
</template>
