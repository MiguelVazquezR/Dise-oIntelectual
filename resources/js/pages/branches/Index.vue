<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Building2,
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
import type {
    Branch,
    BranchFilters,
    BranchPermissions,
    Paginated,
} from '@/types';

type Props = {
    filters: BranchFilters;
    branches: Paginated<Branch>;
    can: BranchPermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Sucursales', href: '/branches' }],
    },
});

const { filters, isFiltering, apply, applyDebounced, reset } = useListFilters(
    '/branches',
    {
        search: props.filters.search ?? '',
        status: props.filters.status ?? '',
    },
);

watch(() => filters.search, applyDebounced);

const statusModel = computed({
    get: () => (filters.status === '' ? 'all' : filters.status),
    set: (value: string) => {
        filters.status = value === 'all' ? '' : value;
        apply();
    },
});

const statusOptions = [
    { value: 'active', label: 'Activas' },
    { value: 'inactive', label: 'Inactivas' },
];

const deleteTarget = ref<Branch | null>(null);
const deleting = ref(false);

function confirmDelete(): void {
    const target = deleteTarget.value;

    if (!target) {
        return;
    }

    deleting.value = true;

    router.delete(`/branches/${target.id}`, {
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
    <Head title="Sucursales" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Sucursales"
            description="Administra las sedes de la empresa, su zona horaria y su geocerca."
        >
            <template #actions>
                <Button v-if="props.can.manage" as-child>
                    <Link href="/branches/create">
                        <Plus aria-hidden="true" />
                        Nueva sucursal
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <DataTable
            :empty="props.branches.data.length === 0"
            caption="Listado de sucursales"
        >
            <template #header>
                <form
                    class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
                    @submit.prevent="apply"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="grid gap-1.5">
                            <Label
                                for="branch-search"
                                class="text-xs text-muted-foreground"
                                >Buscar</Label
                            >
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="branch-search"
                                    v-model="filters.search"
                                    type="search"
                                    placeholder="Nombre, clave o dirección"
                                    class="pl-9 sm:w-72"
                                    autocomplete="off"
                                />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="branch-status"
                                class="text-xs text-muted-foreground"
                                >Estado</Label
                            >
                            <Select v-model="statusModel">
                                <SelectTrigger
                                    id="branch-status"
                                    class="w-full sm:w-40"
                                >
                                    <SelectValue placeholder="Todas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas</SelectItem>
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
                        <th scope="col" class="px-3 py-2.5">Nombre</th>
                        <th scope="col" class="px-3 py-2.5">Clave</th>
                        <th scope="col" class="px-3 py-2.5">Dirección</th>
                        <th scope="col" class="px-3 py-2.5">Zona horaria</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Colaboradores
                        </th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            Horarios
                        </th>
                        <th scope="col" class="px-3 py-2.5">Estado</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="branch in props.branches.data"
                        :key="branch.id"
                        class="border-t border-border align-middle hover:bg-muted/30"
                    >
                        <td class="px-3 py-2.5">
                            <span class="font-medium text-foreground">{{
                                branch.name
                            }}</span>
                        </td>
                        <td class="px-3 py-2.5">
                            <span
                                class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground"
                            >
                                {{ branch.code }}
                            </span>
                        </td>
                        <td class="max-w-[18rem] px-3 py-2.5">
                            <span
                                class="block truncate text-muted-foreground"
                                :title="branch.address ?? undefined"
                            >
                                {{ branch.address ?? '—' }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ branch.timezone }}
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums">
                            {{ branch.employees_count }}
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums">
                            {{ branch.schedules_count }}
                        </td>
                        <td class="px-3 py-2.5">
                            <StatusBadge
                                :variant="
                                    branch.is_active ? 'success' : 'neutral'
                                "
                            >
                                {{ branch.is_active ? 'Activa' : 'Inactiva' }}
                            </StatusBadge>
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
                                        :href="`/branches/${branch.id}/edit`"
                                        :aria-label="`Editar ${branch.name}`"
                                    >
                                        <Pencil aria-hidden="true" />
                                    </Link>
                                </Button>
                                <Button
                                    v-if="props.can.manage"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-destructive-strong"
                                    :aria-label="`Eliminar ${branch.name}`"
                                    @click="deleteTarget = branch"
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
                    :icon="Building2"
                    title="Sin sucursales registradas"
                    :description="
                        filters.search || filters.status
                            ? 'No hay resultados con los filtros aplicados. Ajusta la búsqueda e inténtalo de nuevo.'
                            : 'Registra la primera sucursal para comenzar a operar asistencias y horarios.'
                    "
                >
                    <template v-if="props.can.manage" #action>
                        <Button as-child>
                            <Link href="/branches/create">
                                <Plus aria-hidden="true" />
                                Nueva sucursal
                            </Link>
                        </Button>
                    </template>
                </EmptyState>
            </template>

            <Pagination
                :links="props.branches.links"
                :meta="props.branches.meta ?? null"
            />
        </DataTable>

        <ConfirmDialog
            :open="deleteTarget !== null"
            title="Eliminar sucursal"
            :description="
                deleteTarget
                    ? `Se eliminará «${deleteTarget.name}» y sus datos asociados. Esta acción no se puede deshacer.`
                    : ''
            "
            confirm-label="Eliminar sucursal"
            :processing="deleting"
            @update:open="handleDialogOpen"
            @confirm="confirmDelete"
        />
    </div>
</template>
