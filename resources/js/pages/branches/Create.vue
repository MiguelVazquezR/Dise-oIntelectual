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
    branch: BranchRecord | null;
    timezones: string[];
    can: BranchPermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sucursales', href: '/branches' },
            { title: 'Nueva sucursal', href: '/branches/create' },
        ],
    },
});

const defaultTimezone = props.timezones.includes('America/Mexico_City')
    ? 'America/Mexico_City'
    : (props.timezones[0] ?? 'America/Mexico_City');

const timezoneOptions: SelectOption[] = props.timezones.map((timezone) => ({
    value: timezone,
    label: timezone,
}));

const form = useForm({
    name: '',
    code: '',
    address: '',
    latitude: '',
    longitude: '',
    geofence_radius_m: '',
    timezone: defaultTimezone,
    is_active: true,
});

function submit(): void {
    form.post('/branches', { preserveScroll: true });
}
</script>

<template>
    <Head title="Nueva sucursal" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Nueva sucursal"
            description="Registra una sede con su zona horaria y, si aplica, su geocerca de marcaje."
        />

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
                                    placeholder="Sucursal Centro"
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
                                    placeholder="SUC-CENTRO"
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
                                placeholder="Calle, número, colonia, ciudad"
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
                                    placeholder="19.4326077"
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
                                    placeholder="-99.133208"
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
                                    placeholder="150"
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
                    Crear sucursal
                </Button>
            </div>
        </form>
    </div>
</template>
