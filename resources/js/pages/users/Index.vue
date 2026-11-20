<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus, RotateCcw, Search, UserCog } from '@lucide/vue';
import { computed, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
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
    NamedRef,
    Paginated,
    RawSelectOption,
    UserFilters,
    UserPermissions,
    UserRow,
} from '@/types';

type Props = {
    filters: UserFilters;
    users: Paginated<UserRow>;
    options: {
        roles: RawSelectOption[];
        branches: RawSelectOption[];
    };
    can: UserPermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios', href: '/users' }],
    },
});

const { filters, isFiltering, apply, applyDebounced, reset } = useListFilters(
    '/users',
    {
        search: props.filters.search ?? '',
        role_slug: props.filters.role_slug ?? '',
    },
);

watch(() => filters.search, applyDebounced);

const roleOptions = computed(() =>
    toSelectOptions(props.options?.roles, 'slug'),
);

const roleModel = computed({
    get: () => (filters.role_slug === '' ? 'all' : filters.role_slug),
    set: (value: string) => {
        filters.role_slug = value === 'all' ? '' : value;
        apply();
    },
});

function refLabel(reference: NamedRef): string {
    if (typeof reference === 'string') {
        return reference;
    }

    return (
        reference.name ??
        reference.slug ??
        (reference.id !== undefined ? String(reference.id) : '')
    );
}

function refLabels(references: NamedRef[]): string {
    return references
        .map(refLabel)
        .filter((label) => label !== '')
        .join(', ');
}
</script>

<template>
    <Head title="Usuarios" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Usuarios"
            description="Cuentas con acceso al sistema, sus roles y las sucursales que pueden consultar."
        >
            <template #actions>
                <Button v-if="props.can.manage" as-child>
                    <Link href="/users/create">
                        <Plus aria-hidden="true" />
                        Nuevo usuario
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <DataTable
            :empty="props.users.data.length === 0"
            caption="Listado de usuarios"
        >
            <template #header>
                <form
                    class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
                    @submit.prevent="apply"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="grid gap-1.5">
                            <Label
                                for="user-search"
                                class="text-xs text-muted-foreground"
                                >Buscar</Label
                            >
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="user-search"
                                    v-model="filters.search"
                                    type="search"
                                    placeholder="Nombre o correo"
                                    class="pl-9 sm:w-64"
                                    autocomplete="off"
                                />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="user-role"
                                class="text-xs text-muted-foreground"
                                >Rol</Label
                            >
                            <Select v-model="roleModel">
                                <SelectTrigger
                                    id="user-role"
                                    class="w-full sm:w-48"
                                >
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem
                                        v-for="option in roleOptions"
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
                        <th scope="col" class="px-3 py-2.5">Usuario</th>
                        <th scope="col" class="px-3 py-2.5">Correo</th>
                        <th scope="col" class="px-3 py-2.5">Roles</th>
                        <th scope="col" class="px-3 py-2.5">Sucursales</th>
                        <th scope="col" class="px-3 py-2.5">Alta</th>
                        <th scope="col" class="px-3 py-2.5 text-right">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in props.users.data"
                        :key="user.id"
                        class="border-t border-border align-middle hover:bg-muted/30"
                    >
                        <td class="px-3 py-2.5">
                            <span class="font-medium text-foreground">{{
                                user.name
                            }}</span>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="break-all text-muted-foreground">{{
                                user.email
                            }}</span>
                        </td>
                        <td class="max-w-[16rem] px-3 py-2.5">
                            <span
                                class="block truncate"
                                :title="refLabels(user.roles)"
                                >{{ refLabels(user.roles) || '—' }}</span
                            >
                        </td>
                        <td class="max-w-[18rem] px-3 py-2.5">
                            <span
                                class="block truncate text-muted-foreground"
                                :title="refLabels(user.branches)"
                                >{{ refLabels(user.branches) || '—' }}</span
                            >
                        </td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            {{ formatDate(user.created_at) }}
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
                                        :href="`/users/${user.id}/edit`"
                                        :aria-label="`Editar ${user.name}`"
                                    >
                                        <Pencil aria-hidden="true" />
                                    </Link>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </template>

            <template #empty>
                <EmptyState
                    :icon="UserCog"
                    title="Sin usuarios registrados"
                    :description="
                        filters.search || filters.role_slug
                            ? 'No hay usuarios con los filtros aplicados. Ajusta la búsqueda e inténtalo de nuevo.'
                            : 'Crea la primera cuenta para que el equipo pueda acceder al sistema.'
                    "
                >
                    <template v-if="props.can.manage" #action>
                        <Button as-child>
                            <Link href="/users/create">
                                <Plus aria-hidden="true" />
                                Nuevo usuario
                            </Link>
                        </Button>
                    </template>
                </EmptyState>
            </template>

            <Pagination
                :links="props.users.links"
                :meta="props.users.meta ?? null"
            />
        </DataTable>
    </div>
</template>
