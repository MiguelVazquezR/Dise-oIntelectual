<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Check, ShieldCheck, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { PermissionGroup, RolePermissions, RoleRow } from '@/types';

type Props = {
    roles: RoleRow[];
    permission_groups: PermissionGroup[];
    can: RolePermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles', href: '/roles' }],
    },
});

const selectedSlug = ref<string>(props.roles[0]?.slug ?? '');

const selectedRole = computed<RoleRow | null>(
    () => props.roles.find((role) => role.slug === selectedSlug.value) ?? null,
);

const form = useForm<{ permissions: string[] }>({
    permissions: [],
});

watch(
    selectedRole,
    (role) => {
        const permissions = role ? [...role.permissions] : [];

        form.permissions = permissions;
        form.defaults({ permissions: [...permissions] });
        form.clearErrors();
    },
    { immediate: true },
);

const isSystemRole = computed(() => selectedRole.value?.is_system === true);

const isReadOnly = computed(
    () =>
        selectedRole.value === null || isSystemRole.value || !props.can.manage,
);

function groupLabel(group: string): string {
    const spaced = group.replace(/[_-]+/g, ' ');

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

function isChecked(slug: string): boolean {
    return form.permissions.includes(slug);
}

function togglePermission(slug: string): void {
    if (isReadOnly.value) {
        return;
    }

    const index = form.permissions.indexOf(slug);

    if (index >= 0) {
        form.permissions.splice(index, 1);

        return;
    }

    form.permissions.push(slug);
}

function save(): void {
    const role = selectedRole.value;

    if (!role || role.is_system) {
        return;
    }

    // `routes/admin.php` declara `PUT /roles/{role}` con binding implícito por
    // id; §7.1 lo redacta como `{slug}`. Se usa el id hasta que el backend
    // defina `getRouteKeyName()` sobre el slug.
    form.put(`/roles/${role.id}`, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Roles y permisos" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Roles y permisos"
            description="Los permisos efectivos los valida el backend; aquí solo se asignan al rol elegido."
        />

        <div
            v-if="!props.can.manage"
            class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive-strong"
            role="alert"
        >
            Solo lectura: no tienes el permiso «roles.manage».
        </div>

        <div class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <section class="space-y-2" aria-labelledby="roles-list">
                <h2
                    id="roles-list"
                    class="px-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Roles
                </h2>

                <ul class="space-y-2">
                    <li v-for="role in props.roles" :key="role.id">
                        <button
                            type="button"
                            class="w-full rounded-lg border p-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                            :class="
                                role.slug === selectedSlug
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border bg-card hover:bg-muted/50'
                            "
                            :aria-pressed="role.slug === selectedSlug"
                            @click="selectedSlug = role.slug"
                        >
                            <span
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="text-sm font-medium text-foreground"
                                >
                                    {{ role.name }}
                                </span>
                                <StatusBadge
                                    v-if="role.is_system"
                                    variant="info"
                                >
                                    Sistema
                                </StatusBadge>
                            </span>
                            <span
                                class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <Users class="size-3.5" aria-hidden="true" />
                                {{ role.users_count }}
                                {{
                                    role.users_count === 1
                                        ? 'usuario'
                                        : 'usuarios'
                                }}
                            </span>
                        </button>
                    </li>
                </ul>

                <p
                    v-if="props.roles.length === 0"
                    class="rounded-lg border border-dashed border-border p-3 text-sm text-muted-foreground"
                >
                    No hay roles configurados.
                </p>
            </section>

            <section
                class="rounded-lg border border-border bg-card"
                aria-labelledby="role-permissions"
            >
                <div
                    class="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0 space-y-1">
                        <h2
                            id="role-permissions"
                            class="flex flex-wrap items-center gap-2 text-base font-semibold"
                        >
                            {{ selectedRole?.name ?? 'Permisos' }}
                            <StatusBadge
                                v-if="selectedRole?.is_system"
                                variant="info"
                            >
                                Sistema
                            </StatusBadge>
                        </h2>
                        <p
                            v-if="selectedRole?.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ selectedRole.description }}
                        </p>
                        <p
                            v-if="selectedRole"
                            class="text-xs text-muted-foreground"
                        >
                            {{ form.permissions.length }} permisos
                            seleccionados. El rol debe conservar al menos uno.
                        </p>
                    </div>

                    <Button
                        v-if="selectedRole && !isSystemRole"
                        type="button"
                        :disabled="
                            form.processing || isReadOnly || !form.isDirty
                        "
                        @click="save"
                    >
                        <Spinner v-if="form.processing" />
                        <Check v-else aria-hidden="true" />
                        Guardar permisos
                    </Button>
                </div>

                <div class="space-y-6 p-4">
                    <p
                        v-if="isSystemRole"
                        class="flex items-start gap-2 rounded-md bg-primary/5 px-3 py-2 text-xs text-muted-foreground"
                    >
                        <ShieldCheck
                            class="mt-0.5 size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        El rol «{{ selectedRole?.name }}» es de sistema: siempre
                        tiene todos los permisos y no puede modificarse.
                    </p>

                    <fieldset
                        v-for="group in props.permission_groups"
                        :key="group.group"
                        class="space-y-3"
                    >
                        <legend
                            class="px-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            {{ groupLabel(group.group) }}
                        </legend>

                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            <div
                                v-for="permission in group.permissions"
                                :key="permission.slug"
                                class="flex items-start gap-2"
                            >
                                <Checkbox
                                    :id="`permission-${permission.slug}`"
                                    :model-value="isChecked(permission.slug)"
                                    :disabled="isReadOnly"
                                    class="mt-0.5"
                                    @update:model-value="
                                        togglePermission(permission.slug)
                                    "
                                />
                                <Label
                                    :for="`permission-${permission.slug}`"
                                    class="text-sm leading-snug font-normal"
                                >
                                    {{ permission.name }}
                                    <span
                                        class="block font-mono text-[11px] text-muted-foreground"
                                    >
                                        {{ permission.slug }}
                                    </span>
                                </Label>
                            </div>
                        </div>
                    </fieldset>

                    <EmptyState
                        v-if="!selectedRole"
                        :icon="ShieldCheck"
                        title="Selecciona un rol"
                        description="Elige un rol de la lista para revisar y ajustar sus permisos."
                    />

                    <p
                        v-if="form.errors.permissions"
                        class="text-sm text-destructive-strong"
                        role="alert"
                    >
                        {{ form.errors.permissions }}
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
