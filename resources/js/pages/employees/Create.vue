<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed } from 'vue';
import EmployeeFormFields from '@/components/EmployeeFormFields.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    transformEmployeeForm,
    useEmployeeForm,
} from '@/composables/useEmployeeForm';
import { toSelectOptions } from '@/lib/options';
import type {
    EmployeeOptions,
    EmployeePermissions,
    EmployeeRecord,
} from '@/types';

type Props = {
    employee: EmployeeRecord | null;
    options: EmployeeOptions;
    can: EmployeePermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Colaboradores', href: '/employees' },
            { title: 'Nuevo colaborador', href: '/employees/create' },
        ],
    },
});

const branchOptions = toSelectOptions(props.options?.branches);
const statusOptions = toSelectOptions(props.options?.statuses);
const payFrequencyOptions = toSelectOptions(props.options?.pay_frequencies);

const defaultStatus = statusOptions.some((option) => option.value === 'active')
    ? 'active'
    : (statusOptions[0]?.value ?? 'active');

const defaultPayFrequency = payFrequencyOptions.some(
    (option) => option.value === 'monthly',
)
    ? 'monthly'
    : (payFrequencyOptions[0]?.value ?? 'monthly');

const form = useEmployeeForm(null, {
    status: defaultStatus,
    payFrequency: defaultPayFrequency,
});

const canManage = computed(() => props.can.manage);

function submit(): void {
    form.transform(transformEmployeeForm);
    form.post('/employees', { preserveScroll: true });
}
</script>

<template>
    <Head title="Nuevo colaborador" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Nuevo colaborador"
            description="Captura los datos personales, laborales y la compensación inicial. El PIN del checador se asigna después de crear el registro."
        />

        <div
            v-if="!canManage"
            class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive-strong"
            role="alert"
        >
            No tienes permiso para administrar colaboradores; el formulario no
            se enviará.
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <EmployeeFormFields
                :form="form"
                :branches="branchOptions"
                :statuses="statusOptions"
                :pay-frequencies="payFrequencyOptions"
                :can-manage="canManage"
            />

            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link href="/employees">
                        <ArrowLeft aria-hidden="true" />
                        Cancelar
                    </Link>
                </Button>
                <Button type="submit" :disabled="form.processing || !canManage">
                    <Spinner v-if="form.processing" />
                    <Save v-else aria-hidden="true" />
                    Crear colaborador
                </Button>
            </div>
        </form>
    </div>
</template>
