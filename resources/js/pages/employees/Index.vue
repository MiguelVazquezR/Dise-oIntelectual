<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Ban,
    Download,
    Eye,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Users,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
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
import { formatDate } from '@/lib/format';
import { employeeStatusLabel, employeeStatusVariant } from '@/lib/labels';
import { toSelectOptions } from '@/lib/options';
import type {
    EmployeeFilters,
    EmployeeOptions,
    EmployeePermissions,
    EmployeeRow,
    Paginated,
    SelectOption,
} from '@/types';

type Props = {
    filters: EmployeeFilters;
    employees: Paginated<EmployeeRow>;
    options: EmployeeOptions;
    can: EmployeePermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Colaboradores', href: '/employees' }],
    },
});

const { filters, isFiltering, apply, applyDebounced, reset } = useListFilters(
    '/employees',
    {
        search: props.filters.search ?? '',
        branch_id: props.filters.branch_id ?? '',
        status: props.filters.status ?? '',
    },
);

watch(() => filters.search, applyDebounced);

const branchOptions = computed(() => toSelectOptions(props.options?.branches));

const fallbackStatuses: SelectOption[] = [
    { value: 'active', label: 'Activo' },
    { value: 'inactive', label: 'Inactivo' },
    { value: 'terminated', label: 'Baja' },
];

const statusOptions = computed(() => {
    const options = toSelectOptions(props.options?.statuses);

    return options.length > 0 ? options : fallbackStatuses;
});

const branchModel = computed({
    get: () => (filters.branch_id === '' ? 'all' : filters.branch_id),
    set: (value: string) => {
        filters.branch_id = value === 'all' ? '' : value;
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

/** Enlace de exportación con los filtros aplicados (ARCHITECTURE §7.1). */
const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (filters.search !== '') {
        params.set('search', filters.search);
    }

    if (filters.branch_id !== '') {
        params.set('branch_id', filters.branch_id);
    }

    if (filters.status !== '') {
        params.set('status', filters.status);
    }

    const query = params.toString();

    return query === '' ? '/employees/export' : `/employees/export?${query}`;
});
</script>

<template>
    <Head title="Colaboradores" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Colaboradores"
            description="Plantilla por sucursal. La baja se registra editando el colaborador y cambiando su estado a «Baja»."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <a :href="exportUrl">
                        <Download aria-hidden="true" />
                        Exportar CSV
                    </a>
                </Button>
                <Button v-if="props.can.manage" as-child>
                    <Link href="/employees/create">
                        <Plus aria-hidden="true" />
                        Nuevo colaborador
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <DataTable
            :empty="props.employees.data.length === 0"
            caption="Listado de colaboradores"
        >
            <template #header>
                <form
                    class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
                    @submit.prevent="apply"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="grid gap-1.5">
                            <Label
                                for="employee-search"
                                class="text-xs text-muted-foreground"
                                >Buscar</Label
                            >
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="employee-search"
                                    v-model="filters.search"
                                    type="search"
                                    placeholder="Número, nombre o puesto"
                                    class="pl-9 sm:w-64"
                                    autocomplete="off"
                                />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="employee-branch"
                                class="text-xs text-muted-foreground"
                                >Sucursal</Label
                            >
                            <Select v-model="branchModel">
                                <SelectTrigger
                                    id="employee-branch"
                                    class="w-full sm:w-52"
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
                                for="employee-status"
                                class="text-xs text-muted-foreground"
                                >Estado</Label
                            >
                            <Select v-model="statusModel">
                                <SelectTrigger
                                    id="employee-status"
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
                        <th scope="col" class="px-3 py-2.5">Número</th>
                        <th scope="col" class="px-3 py-2.5">Nombre</th>
                        <th scope="col" class="px-3 py-2.5">Sucursal</th>
                        <th scope="col" class="px-3 py-2.5">Puesto</th>
                        <th scope="col" class="px-3 py-2.5">Ingreso</th>
                        <th scope="col" class="px-3 py-2.5">Estado</th>
                        <th scope="col" class="px-3 py-2.5">PIN</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="employee in props.employees.data"
                        :key="employee.id"
                        class="border-t border-border align-middle hover:bg-muted/30"
                    >
                        <td
                            class="px-3 py-2.5 font-mono text-xs whitespace-nowrap text-muted-foreground"
                        >
                            {{ employee.employee_number }}
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="font-medium text-foreground">{{
                                employee.full_name
                            }}</span>
                        </td>
                        <td class="px-3 py-2.5">
                            {{ employee.branch?.name ?? '—' }}
                        </td>
                        <td class="max-w-[16rem] px-3 py-2.5">
                            <span
                                class="block truncate"
                                :title="employee.position"
                                >{{ employee.position }}</span
                            >
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ formatDate(employee.hire_date) }}
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                :variant="
                                    employeeStatusVariant(employee.status)
                                "
                            >
                                {{ employeeStatusLabel(employee.status) }}
                            </StatusBadge>
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                :variant="
                                    employee.has_pin ? 'success' : 'warning'
                                "
                            >
                                {{ employee.has_pin ? 'Asignado' : 'Sin PIN' }}
                            </StatusBadge>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link
                                        :href="`/employees/${employee.id}`"
                                        :aria-label="`Ver ficha de ${employee.full_name}`"
                                    >
                                        <Eye aria-hidden="true" />
                                    </Link>
                                </Button>
                                <Button
                                    v-if="props.can.manage"
                                    variant="ghost"
                                    size="icon-sm"
                                    as-child
                                >
                                    <Link
                                        :href="`/employees/${employee.id}/edit`"
                                        :aria-label="`Editar ${employee.full_name}`"
                                    >
                                        <Pencil aria-hidden="true" />
                                    </Link>
                                </Button>
                                <Button
                                    v-if="
                                        props.can.manage &&
                                        employee.status !== 'terminated'
                                    "
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-destructive-strong"
                                    as-child
                                >
                                    <Link
                                        :href="`/employees/${employee.id}/edit`"
                                        :title="`Registrar la baja de ${employee.full_name}: cambia el estado a «Baja» en su ficha`"
                                        :aria-label="`Registrar baja de ${employee.full_name} (se edita el estado en su ficha)`"
                                    >
                                        <Ban aria-hidden="true" />
                                    </Link>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </template>

            <template #empty>
                <EmptyState
                    :icon="Users"
                    title="Sin colaboradores registrados"
                    :description="
                        filters.search || filters.branch_id || filters.status
                            ? 'No hay colaboradores con los filtros aplicados. Ajusta la búsqueda e inténtalo de nuevo.'
                            : 'Captura al primer colaborador para comenzar a registrar asistencias y pre-nómina.'
                    "
                >
                    <template v-if="props.can.manage" #action>
                        <Button as-child>
                            <Link href="/employees/create">
                                <Plus aria-hidden="true" />
                                Nuevo colaborador
                            </Link>
                        </Button>
                    </template>
                </EmptyState>
            </template>

            <Pagination
                :links="props.employees.links"
                :meta="props.employees.meta ?? null"
            />
        </DataTable>

        <p class="text-xs text-muted-foreground">
            La baja no elimina al colaborador: se registra en la ficha cambiando
            su estado a «Baja» para conservar el historial de asistencias y
            nómina.
        </p>
    </div>
</template>
