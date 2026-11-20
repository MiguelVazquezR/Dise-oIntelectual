<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, KeyRound, Save } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmployeeFormFields from '@/components/EmployeeFormFields.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    employee: EmployeeRecord;
    options: EmployeeOptions;
    can: EmployeePermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Colaboradores', href: '/employees' },
            { title: 'Editar colaborador', href: '/employees' },
        ],
    },
});

const branchOptions = toSelectOptions(props.options?.branches);
const statusOptions = toSelectOptions(props.options?.statuses);
const payFrequencyOptions = toSelectOptions(props.options?.pay_frequencies);

const form = useEmployeeForm(props.employee, {
    status: 'active',
    payFrequency: 'monthly',
});

const canManage = computed(() => props.can.manage);

const pinDialogOpen = ref(false);
const pinForm = useForm({
    pin: '',
    pin_confirmation: '',
});

function openPinDialog(): void {
    pinForm.reset();
    pinForm.clearErrors();
    pinDialogOpen.value = true;
}

function submitPin(): void {
    pinForm.post(`/employees/${props.employee.id}/pin`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            pinDialogOpen.value = false;
            pinForm.reset();
        },
    });
}

function submit(): void {
    form.transform(transformEmployeeForm);
    form.put(`/employees/${props.employee.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Editar colaborador" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`${props.employee.first_name} ${props.employee.last_name}`"
            description="Actualiza los datos del colaborador. La baja se registra cambiando el estado a «Baja»; el historial se conserva."
        >
            <template #actions>
                <Button
                    v-if="canManage"
                    variant="outline"
                    type="button"
                    @click="openPinDialog"
                >
                    <KeyRound aria-hidden="true" />
                    Asignar PIN
                </Button>
                <Button variant="outline" as-child>
                    <Link href="/employees">
                        <ArrowLeft aria-hidden="true" />
                        Volver al listado
                    </Link>
                </Button>
            </template>
        </PageHeader>

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
                    Guardar cambios
                </Button>
            </div>
        </form>

        <Dialog v-model:open="pinDialogOpen">
            <DialogContent>
                <form class="space-y-4" @submit.prevent="submitPin">
                    <DialogHeader class="space-y-2">
                        <DialogTitle>PIN del checador</DialogTitle>
                        <DialogDescription>
                            El PIN se guarda cifrado en el servidor y no puede
                            consultarse después. Debe tener entre 4 y 6 dígitos
                            numéricos.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-1.5">
                        <Label for="pin">PIN</Label>
                        <Input
                            id="pin"
                            v-model="pinForm.pin"
                            type="password"
                            inputmode="numeric"
                            autocomplete="new-password"
                            pattern="[0-9]{4,6}"
                            minlength="4"
                            maxlength="6"
                            required
                            aria-describedby="pin-help"
                        />
                        <p id="pin-help" class="text-xs text-muted-foreground">
                            Nunca se muestra el PIN guardado ni se envía al
                            navegador.
                        </p>
                        <InputError :message="pinForm.errors.pin" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="pin-confirmation">Confirmar PIN</Label>
                        <Input
                            id="pin-confirmation"
                            v-model="pinForm.pin_confirmation"
                            type="password"
                            inputmode="numeric"
                            autocomplete="new-password"
                            pattern="[0-9]{4,6}"
                            minlength="4"
                            maxlength="6"
                            required
                        />
                        <InputError
                            :message="pinForm.errors.pin_confirmation"
                        />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="pinForm.processing"
                            @click="pinDialogOpen = false"
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="pinForm.processing">
                            <Spinner v-if="pinForm.processing" />
                            <KeyRound v-else aria-hidden="true" />
                            Guardar PIN
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
