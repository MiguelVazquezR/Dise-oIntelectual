<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
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
import { toSelectOptions } from '@/lib/options';
import type {
    Paginated,
    RawSelectOption,
    ScheduleFilters,
    SchedulePermissions,
    ScheduleRow,
} from '@/types';

type Props = {
    filters: ScheduleFilters;
    schedules: Paginated<ScheduleRow>;
    options: {
        branches: RawSelectOption[];
    };
    can: SchedulePermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Horarios', href: '/schedules' }],
    },
});

const { filters, isFiltering, apply, applyDebounced, reset } = useListFilters(
    '/schedules',
    {
        branch_id: props.filters.branch_id ?? '',
        search: props.filters.search ?? '',
    },
);

watch(() => filters.search, applyDebounced);

const branchOptions = computed(() => toSelectOptions(props.options?.branches));

const branchModel = computed({
    get: () => (filters.branch_id === '' ? 'all' : filters.branch_id),
    set: (value: string) => {
        filters.branch_id = value === 'all' ? '' : value;
        apply();
    },
});

const deleteTarget = ref<ScheduleRow | null>(null);
const deleting = ref(false);

function confirmDelete(): void {
    const target = deleteTarget.value;

    if (!target) {
        return;
    }

    deleting.value = true;

    router.delete(`/schedules/${target.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}

function handleDialogOpen(open: boolean): void {
    if (!open) {
        deleteTarget.value = null;
    }
}
</script>

<template>
    <Head title="Horarios" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Horarios"
            description="Horarios semanales por sucursal, con vigencia, descansos y tolerancia de retardo."
        >
            <template #actions>
                <Button v-if="props.can.manage" as-child>
                    <Link href="/schedules/create">
                        <Plus aria-hidden="true" />
                        Nuevo horario
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <DataTable
            :empty="props.schedules.data.length === 0"
            caption="Listado de horarios"
        >
            <template #header>
                <form
                    class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
                    @submit.prevent="apply"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="grid gap-1.5">
                            <Label
                                for="schedule-search"
                                class="text-xs text-muted-foreground"
                                >Buscar</Label
                            >
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="schedule-search"
                                    v-model="filters.search"
                                    type="search"
                                    placeholder="Nombre del horario"
                                    class="pl-9 sm:w-64"
                                    autocomplete="off"
                                />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="schedule-branch"
                                class="text-xs text-muted-foreground"
                                >Sucursal</Label
                            >
                            <Select v-model="branchModel">
                                <SelectTrigger
                                    id="schedule-branch"
                                    class="w-full sm:w-56"
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
                        <th scope="col" class="px-3 py-2.5">Horario</th>
                        <th scope="col" class="px-3 py-2.5">Sucursal</th>
                        <th scope="col" class="px-3 py-2.5">Vigencia</th>
                        <th scope="col" class="px-3 py-2.5">Estado</th>
                        <th scope="col" class="px-3 py-2.5 text-right">Días</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="schedule in props.schedules.data"
                        :key="schedule.id"
                        class="border-t border-border align-middle hover:bg-muted/30"
                    >
                        <td class="px-3 py-2.5">
                            <span class="font-medium text-foreground">{{
                                schedule.name
                            }}</span>
                        </td>
                        <td class="px-3 py-2.5">
                            {{ schedule.branch?.name ?? 'Todas' }}
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ formatDate(schedule.effective_from) }}
                            <span class="text-muted-foreground">→</span>
                            {{
                                schedule.effective_to
                                    ? formatDate(schedule.effective_to)
                                    : 'Sin fin'
                            }}
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                :variant="
                                    schedule.is_active ? 'success' : 'neutral'
                                "
                            >
                                {{ schedule.is_active ? 'Activo' : 'Inactivo' }}
                            </StatusBadge>
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums">
                            {{ schedule.days_count }}
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    v-if="props.can.manage"
                                    variant="ghost"
                                    size="icon-sm"
                                    as-child
                                >
                                    <Link
                                        :href="`/schedules/${schedule.id}/edit`"
                                        :aria-label="`Editar ${schedule.name}`"
                                    >
                                        <Pencil aria-hidden="true" />
                                    </Link>
                                </Button>
                                <Button
                                    v-if="props.can.manage"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-destructive-strong"
                                    :aria-label="`Eliminar ${schedule.name}`"
                                    @click="deleteTarget = schedule"
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
                    :icon="CalendarClock"
                    title="Sin horarios registrados"
                    :description="
                        filters.search || filters.branch_id
                            ? 'No hay horarios con los filtros aplicados. Ajusta la búsqueda e inténtalo de nuevo.'
                            : 'Crea un horario semanal para que el cálculo de asistencias conozca turnos, descansos y tolerancias.'
                    "
                >
                    <template v-if="props.can.manage" #action>
                        <Button as-child>
                            <Link href="/schedules/create">
                                <Plus aria-hidden="true" />
                                Nuevo horario
                            </Link>
                        </Button>
                    </template>
                </EmptyState>
            </template>

            <Pagination
                :links="props.schedules.links"
                :meta="props.schedules.meta ?? null"
            />
        </DataTable>

        <ConfirmDialog
            :open="deleteTarget !== null"
            title="Eliminar horario"
            :description="
                deleteTarget
                    ? `Se eliminará «${deleteTarget.name}» y sus días configurados. Esta acción no se puede deshacer.`
                    : ''
            "
            confirm-label="Eliminar horario"
            :processing="deleting"
            @update:open="handleDialogOpen"
            @confirm="confirmDelete"
        />
    </div>
</template>
