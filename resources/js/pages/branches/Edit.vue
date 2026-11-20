<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { BranchPermissions, BranchRecord, SelectOption } from '@/types';

type Props = {
    branch: BranchRecord;
    timezones: string[];
    can: BranchPermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sucursales', href: '/branches' },
            { title: 'Editar sucursal', href: '/branches' },
        ],
    },
});

const timezoneOptions: SelectOption[] = props.timezones.map((timezone) => ({
    value: timezone,
    label: timezone,
}));

function stringValue(value: string | number | null): string {
    return value === null ? '' : String(value);
}

const form = useForm({
    name: props.branch.name,
    code: props.branch.code,
    address: props.branch.address ?? '',
    latitude: stringValue(props.branch.latitude),
    longitude: stringValue(props.branch.longitude),
    geofence_radius_m: stringValue(props.branch.geofence_radius_m),
    timezone: props.branch.timezone,
    is_active: props.branch.is_active,
});

function submit(): void {
    form.put(`/branches/${props.branch.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Editar sucursal" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="props.branch.name"
            description="Actualiza los datos de la sucursal. Los cambios aplican a los próximos marcajes y horarios."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/branches">
                        <ArrowLeft aria-hidden="true" />
                        Volver al listado
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="!props.can.manage"
            class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive-strong"
            role="alert"
        >
            No tienes permiso para administrar sucursales; el formulario no se
            enviará.
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div
                    class="space-y-6 rounded-lg border border-border bg-card p-5"
                >
                    <section class="space-y-4">
                        <h2 class="text-sm font-semibold text-foreground">
                            Identificación
                        </h2>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Nombre"
                                for="name"
                                :error="form.errors.name"
                                required
                            >
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    required
                                    maxlength="120"
                                    autocomplete="organization"
                                />
                            </FormField>

                            <FormField
                                label="Clave"
                                for="code"
                                :error="form.errors.code"
                                hint="Hasta 20 caracteres; debe ser única."
                                required
                            >
                                <Input
                                    id="code"
                                    v-model="form.code"
                                    required
                                    maxlength="20"
                                    autocomplete="off"
                                />
                            </FormField>
                        </div>

                        <FormField
                            label="Dirección"
                            for="address"
                            :error="form.errors.address"
                            required
                        >
                            <Input
                                id="address"
                                v-model="form.address"
                                required
                                autocomplete="street-address"
                            />
                        </FormField>

                        <FormField
                            label="Zona horaria"
                            for="timezone"
                            :error="form.errors.timezone"
                            hint="Identificador IANA, por ejemplo America/Mexico_City."
                            required
                        >
                            <Select v-model="form.timezone">
                                <SelectTrigger
                                    id="timezone"
                                    class="w-full"
                                    aria-label="Zona horaria"
                                >
                                    <SelectValue
                                        placeholder="Selecciona una zona horaria"
                                    />
                                </SelectTrigger>
                                <SelectContent class="max-h-72">
                                    <SelectItem
                                        v-for="option in timezoneOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                    </section>

                    <section class="space-y-4">
                        <div class="space-y-1">
                            <h2 class="text-sm font-semibold text-foreground">
                                Geocerca
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Opcional. Se usa para validar que el marcaje se
                                realice dentro de la sucursal.
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Latitud"
                                for="latitude"
                                :error="form.errors.latitude"
                                hint="Opcional. Entre −90 y 90."
                            >
                                <Input
                                    id="latitude"
                                    v-model="form.latitude"
                                    type="number"
                                    step="0.0000001"
                                    inputmode="decimal"
                                    autocomplete="off"
                                />
                            </FormField>

                            <FormField
                                label="Longitud"
                                for="longitude"
                                :error="form.errors.longitude"
                                hint="Opcional. Entre −180 y 180."
                            >
                                <Input
                                    id="longitude"
                                    v-model="form.longitude"
                                    type="number"
                                    step="0.0000001"
                                    inputmode="decimal"
                                    autocomplete="off"
                                />
                            </FormField>

                            <FormField
                                label="Radio de la geocerca"
                                for="geofence_radius_m"
                                :error="form.errors.geofence_radius_m"
                                hint="Opcional. Metros, entre 10 y 5000."
                                class="sm:col-span-2"
                            >
                                <Input
                                    id="geofence_radius_m"
                                    v-model="form.geofence_radius_m"
                                    type="number"
                                    min="10"
                                    max="5000"
                                    step="1"
                                    inputmode="numeric"
                                    autocomplete="off"
                                />
                            </FormField>
                        </div>
                    </section>
                </div>

                <aside
                    class="h-fit space-y-4 rounded-lg border border-border bg-card p-5"
                >
                    <h2 class="text-sm font-semibold text-foreground">
                        Estado
                    </h2>

                    <div class="flex items-start gap-3">
                        <Checkbox id="is_active" v-model="form.is_active" />
                        <div class="grid gap-1">
                            <Label for="is_active">Sucursal activa</Label>
                            <p class="text-xs text-muted-foreground">
                                Puedes desactivarla y reactivarla cuando lo
                                necesites.
                            </p>
                        </div>
                    </div>
                    <InputError :message="form.errors.is_active" />
                </aside>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link href="/branches">
                        <ArrowLeft aria-hidden="true" />
                        Cancelar
                    </Link>
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing || !props.can.manage"
                >
                    <Spinner v-if="form.processing" />
                    <Save v-else aria-hidden="true" />
                    Guardar cambios
                </Button>
            </div>
        </form>
    </div>
</template>
