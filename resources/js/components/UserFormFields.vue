<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { UserFormData } from '@/types';

type Option = {
    id: number;
    label: string;
};

type Props = {
    form: InertiaForm<UserFormData>;
    roles: Option[];
    branches: Option[];
    isEdit?: boolean;
    canManage: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    isEdit: false,
});

const form = props.form;

function toggleRole(id: number): void {
    const index = form.role_ids.indexOf(id);

    if (index >= 0) {
        form.role_ids.splice(index, 1);

        return;
    }

    form.role_ids.push(id);
}

function toggleBranch(id: number): void {
    const index = form.branch_ids.indexOf(id);

    if (index >= 0) {
        form.branch_ids.splice(index, 1);

        return;
    }

    form.branch_ids.push(id);
}
</script>

<template>
    <div class="space-y-6">
        <section class="space-y-4 rounded-lg border border-border bg-card p-5">
            <h2 class="text-sm font-semibold text-foreground">Cuenta</h2>

            <div class="grid gap-4 md:grid-cols-2">
                <FormField
                    label="Nombre"
                    for="user-name"
                    :error="form.errors.name"
                    required
                >
                    <Input
                        id="user-name"
                        v-model="form.name"
                        required
                        maxlength="120"
                        autocomplete="name"
                        :disabled="!props.canManage"
                    />
                </FormField>

                <FormField
                    label="Correo electrónico"
                    for="user-email"
                    :error="form.errors.email"
                    required
                >
                    <Input
                        id="user-email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        :disabled="!props.canManage"
                    />
                </FormField>

                <FormField
                    label="Contraseña"
                    for="user-password"
                    :error="form.errors.password"
                    :hint="
                        props.isEdit
                            ? 'Deja en blanco para no cambiar la contraseña. Mínimo 12 caracteres.'
                            : 'Mínimo 12 caracteres.'
                    "
                    :required="!props.isEdit"
                    class="md:col-span-2"
                >
                    <Input
                        id="user-password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        minlength="12"
                        :required="!props.isEdit"
                        :disabled="!props.canManage"
                    />
                </FormField>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <fieldset
                class="space-y-3 rounded-lg border border-border bg-card p-5"
            >
                <legend class="px-1 text-sm font-semibold text-foreground">
                    Roles <span class="text-destructive-strong">*</span>
                </legend>
                <p class="text-xs text-muted-foreground">
                    Selecciona al menos un rol. Los permisos efectivos se
                    resuelven en el backend.
                </p>

                <div class="grid gap-2 sm:grid-cols-2">
                    <div
                        v-for="role in props.roles"
                        :key="role.id"
                        class="flex items-center gap-2"
                    >
                        <Checkbox
                            :id="`role-${role.id}`"
                            :model-value="form.role_ids.includes(role.id)"
                            :disabled="!props.canManage"
                            @update:model-value="toggleRole(role.id)"
                        />
                        <Label
                            :for="`role-${role.id}`"
                            class="text-sm font-normal"
                        >
                            {{ role.label }}
                        </Label>
                    </div>
                </div>

                <p
                    v-if="props.roles.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No hay roles disponibles.
                </p>

                <InputError :message="form.errors.role_ids" />
            </fieldset>

            <fieldset
                class="space-y-3 rounded-lg border border-border bg-card p-5"
            >
                <legend class="px-1 text-sm font-semibold text-foreground">
                    Sucursales
                </legend>
                <p class="text-xs text-muted-foreground">
                    Solo se podrán consultar las sucursales asignadas. Sin
                    sucursales no se otorga acceso global.
                </p>

                <div class="grid gap-2 sm:grid-cols-2">
                    <div
                        v-for="branch in props.branches"
                        :key="branch.id"
                        class="flex items-center gap-2"
                    >
                        <Checkbox
                            :id="`branch-${branch.id}`"
                            :model-value="form.branch_ids.includes(branch.id)"
                            :disabled="!props.canManage"
                            @update:model-value="toggleBranch(branch.id)"
                        />
                        <Label
                            :for="`branch-${branch.id}`"
                            class="text-sm font-normal"
                        >
                            {{ branch.label }}
                        </Label>
                    </div>
                </div>

                <p
                    v-if="props.branches.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No hay sucursales disponibles.
                </p>

                <InputError :message="form.errors.branch_ids" />
            </fieldset>
        </div>
    </div>
</template>
