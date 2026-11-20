<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/FormField.vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { EmployeeFormData, SelectOption } from '@/types';

type Props = {
    form: InertiaForm<EmployeeFormData>;
    branches: SelectOption[];
    statuses: SelectOption[];
    payFrequencies: SelectOption[];
    canManage: boolean;
};

const props = defineProps<Props>();

const form = props.form;

const compensationErrors = computed(() => ({
    base_salary: form.errors['compensation.base_salary'],
    pay_frequency: form.errors['compensation.pay_frequency'],
    effective_from: form.errors['compensation.effective_from'],
    notes: form.errors['compensation.notes'],
}));
</script>

<template>
    <div class="space-y-6">
        <section class="space-y-4 rounded-lg border border-border bg-card p-5">
            <h2 class="text-sm font-semibold text-foreground">
                Datos personales
            </h2>

            <div class="grid gap-4 md:grid-cols-2">
                <FormField
                    label="Número de colaborador"
                    for="employee-number"
                    :error="form.errors.employee_number"
                    hint="Identificador único, por ejemplo EMP-0001."
                    required
                >
                    <Input
                        id="employee-number"
                        v-model="form.employee_number"
                        required
                        maxlength="30"
                        autocomplete="off"
                        :disabled="!props.canManage"
                    />
                </FormField>

                <FormField
                    label="Correo electrónico"
                    for="employee-email"
                    :error="form.errors.email"
                    hint="Opcional."
                >
                    <Input
                        id="employee-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        :disabled="!props.canManage"
                    />
                </FormField>

                <FormField
                    label="Nombre"
                    for="first-name"
                    :error="form.errors.first_name"
                    required
                >
                    <Input
                        id="first-name"
                        v-model="form.first_name"
                        required
                        maxlength="120"
                        autocomplete="given-name"
                        :disabled="!props.canManage"
                    />
                </FormField>

                <FormField
                    label="Apellido paterno"
                    for="last-name"
                    :error="form.errors.last_name"
                    required
                >
                    <Input
                        id="last-name"
                        v-model="form.last_name"
                        required
                        maxlength="120"
                        autocomplete="family-name"
                        :disabled="!props.canManage"
                    />
                </FormField>

                <FormField
                    label="Apellido materno"
                    for="second-last-name"
                    :error="form.errors.second_last_name"
                    hint="Opcional."
                >
                    <Input
                        id="second-last-name"
                        v-model="form.second_last_name"
                        maxlength="120"
                        autocomplete="additional-name"
                        :disabled="!props.canManage"
                    />
                </FormField>

                <FormField
                    label="Teléfono"
                    for="employee-phone"
                    :error="form.errors.phone"
                    hint="Opcional."
                >
                    <Input
                        id="employee-phone"
                        v-model="form.phone"
                        type="tel"
                        autocomplete="tel"
                        :disabled="!props.canManage"
                    />
                </FormField>
            </div>
        </section>

        <section class="space-y-4 rounded-lg border border-border bg-card p-5">
            <h2 class="text-sm font-semibold text-foreground">
                Datos laborales
            </h2>

            <div class="grid gap-4 md:grid-cols-2">
                <FormField
                    label="Sucursal"
                    for="employee-branch"
                    :error="form.errors.branch_id"
                    required
                >
                    <Select
                        v-model="form.branch_id"
                        :disabled="!props.canManage"
                    >
                        <SelectTrigger
                            id="employee-branch"
                            class="w-full"
                            aria-label="Sucursal"
                        >
                            <SelectValue
                                placeholder="Selecciona una sucursal"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in props.branches"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>

                <FormField
                    label="Puesto"
                    for="employee-position"
                    :error="form.errors.position"
                    required
                >
                    <Input
                        id="employee-position"
                        v-model="form.position"
                        required
                        maxlength="120"
                        autocomplete="organization-title"
                        :disabled="!props.canManage"
                    />
                </FormField>

                <FormField
                    label="Departamento"
                    for="employee-department"
                    :error="form.errors.department"
                    hint="Opcional."
                >
                    <Input
                        id="employee-department"
                        v-model="form.department"
                        maxlength="120"
                        :disabled="!props.canManage"
                    />
                </FormField>

                <FormField
                    label="Fecha de ingreso"
                    for="hire-date"
                    :error="form.errors.hire_date"
                    required
                >
                    <Input
                        id="hire-date"
                        v-model="form.hire_date"
                        type="date"
                        required
                        :disabled="!props.canManage"
                    />
                </FormField>

                <FormField
                    label="Estado"
                    for="employee-status"
                    :error="form.errors.status"
                    hint="La baja se registra cambiando el estado a «Baja»; no elimina el historial."
                    required
                >
                    <Select v-model="form.status" :disabled="!props.canManage">
                        <SelectTrigger
                            id="employee-status"
                            class="w-full"
                            aria-label="Estado del colaborador"
                        >
                            <SelectValue placeholder="Selecciona" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in props.statuses"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>
            </div>
        </section>

        <section class="space-y-4 rounded-lg border border-border bg-card p-5">
            <div class="space-y-1">
                <h2 class="text-sm font-semibold text-foreground">
                    Compensación
                </h2>
                <p class="text-xs text-muted-foreground">
                    El salario base se captura como monto bruto por periodo, de
                    acuerdo con la frecuencia de pago.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <FormField
                    label="Salario base"
                    for="base-salary"
                    :error="compensationErrors.base_salary"
                    hint="Monto en pesos, sin símbolo ni comas."
                    required
                >
                    <Input
                        id="base-salary"
                        v-model="form.compensation.base_salary"
                        inputmode="decimal"
                        autocomplete="off"
                        placeholder="8500.00"
                        required
                        :disabled="!props.canManage"
                    />
                </FormField>

                <FormField
                    label="Frecuencia de pago"
                    for="pay-frequency"
                    :error="compensationErrors.pay_frequency"
                    required
                >
                    <Select
                        v-model="form.compensation.pay_frequency"
                        :disabled="!props.canManage"
                    >
                        <SelectTrigger
                            id="pay-frequency"
                            class="w-full"
                            aria-label="Frecuencia de pago"
                        >
                            <SelectValue placeholder="Selecciona" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in props.payFrequencies"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>

                <FormField
                    label="Vigente desde"
                    for="compensation-from"
                    :error="compensationErrors.effective_from"
                    hint="Si lo dejas vacío se usa la fecha de ingreso."
                >
                    <Input
                        id="compensation-from"
                        v-model="form.compensation.effective_from"
                        type="date"
                        :disabled="!props.canManage"
                    />
                </FormField>

                <FormField
                    label="Notas"
                    for="compensation-notes"
                    :error="compensationErrors.notes"
                    hint="Opcional. Uso interno."
                >
                    <Textarea
                        id="compensation-notes"
                        v-model="form.compensation.notes"
                        rows="3"
                        maxlength="255"
                        :disabled="!props.canManage"
                    />
                </FormField>
            </div>
        </section>
    </div>
</template>
