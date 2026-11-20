<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import UserFormFields from '@/components/UserFormFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { toIdOptions } from '@/lib/options';
import type {
    RawSelectOption,
    UserFormData,
    UserPermissions,
    UserRecord,
} from '@/types';

type Props = {
    user: UserRecord | null;
    options: {
        roles: RawSelectOption[];
        branches: RawSelectOption[];
    };
    can: UserPermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Usuarios', href: '/users' },
            { title: 'Nuevo usuario', href: '/users/create' },
        ],
    },
});

const roleOptions = toIdOptions(props.options?.roles);
const branchOptions = toIdOptions(props.options?.branches);

const canManage = computed(() => props.can.manage);

const form = useForm<UserFormData>({
    name: '',
    email: '',
    password: '',
    role_ids: [],
    branch_ids: [],
});

function submit(): void {
    form.post('/users', { preserveScroll: true });
}
</script>

<template>
    <Head title="Nuevo usuario" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Nuevo usuario"
            description="Crea una cuenta de acceso y define sus roles y sucursales."
        />

        <div
            v-if="!canManage"
            class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive-strong"
            role="alert"
        >
            No tienes permiso para administrar usuarios; el formulario no se
            enviará.
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <UserFormFields
                :form="form"
                :roles="roleOptions"
                :branches="branchOptions"
                :can-manage="canManage"
            />

            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link href="/users">
                        <ArrowLeft aria-hidden="true" />
                        Cancelar
                    </Link>
                </Button>
                <Button type="submit" :disabled="form.processing || !canManage">
                    <Spinner v-if="form.processing" />
                    <Save v-else aria-hidden="true" />
                    Crear usuario
                </Button>
            </div>
        </form>
    </div>
</template>
