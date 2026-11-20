<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed } from 'vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import ScheduleDaysEditor from '@/components/ScheduleDaysEditor.vue';
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
import { toSelectOptions } from '@/lib/options';
import { buildScheduleDays, normalizeWeekdays } from '@/lib/scheduleForm';
import type {
    RawSelectOption,
    SchedulePermissions,
    ScheduleRecord,
    WeekdayOption,
} from '@/types';

type Props = {
    schedule: ScheduleRecord | null;
    options: {
        branches: RawSelectOption[];
    };
    weekdays: WeekdayOption[];
    can: SchedulePermissions;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Horarios', href: '/schedules' },
            { title: 'Nuevo horario', href: '/schedules/create' },
        ],
    },
});

const weekdayList = normalizeWeekdays(props.weekdays);
const weekdayValues = weekdayList.map((weekday) => weekday.value);

const branchOptions = toSelectOptions(props.options?.branches);

function todayIso(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

const form = useForm({
    name: '',
    branch_id: '',
    effective_from: todayIso(),
    effective_to: '',
    is_active: true,
    days: buildScheduleDays(null, weekdayValues),
});

const dayErrors = computed<Record<string, string | undefined>>(
    () => form.errors as unknown as Record<string, string | undefined>,
);

function submit(): void {
    form.transform((data) => ({
        name: data.name,
        branch_id: data.branch_id === '' ? null : data.branch_id,
        effective_from: data.effective_from,
        effective_to: data.effective_to === '' ? null : data.effective_to,
        is_active: data.is_active,
        days: data.days.map((day) => ({
            weekday: day.weekday,
            is_rest_day: day.is_rest_day,
            late_tolerance_minutes: Number(day.late_tolerance_minutes || 0),
            segments: day.is_rest_day
                ? []
                : day.segments.map((segment) => ({
                      kind: segment.kind,
                      start_time: segment.start_time,
                      end_time: segment.end_time,
                  })),
        })),
    }));

    form.post('/schedules', { preserveScroll: true });
}
</script>

<template>
    <Head title="Nuevo horario" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Nuevo horario"
            description="Define la vigencia y la jornada semanal de una sucursal."
        />

        <div
            v-if="!props.can.manage"
            class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive-strong"
            role="alert"
        >
            No tienes permiso para administrar horarios; el formulario no se
            enviará.
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <section
                class="space-y-4 rounded-lg border border-border bg-card p-5"
            >
                <h2 class="text-sm font-semibold text-foreground">
                    Datos del horario
                </h2>

                <div class="grid gap-4 md:grid-cols-2">
                    <FormField
                        label="Nombre"
                        for="schedule-name"
                        :error="form.errors.name"
                        required
                        class="md:col-span-2"
                    >
                        <Input
                            id="schedule-name"
                            v-model="form.name"
                            required
                            maxlength="120"
                            autocomplete="off"
                            placeholder="Horario administrativo"
                        />
                    </FormField>

                    <FormField
                        label="Sucursal"
                        for="schedule-branch"
                        :error="form.errors.branch_id"
                        required
                    >
                        <Select v-model="form.branch_id">
                            <SelectTrigger
                                id="schedule-branch"
                                class="w-full"
                                aria-label="Sucursal"
                            >
                                <SelectValue
                                    placeholder="Selecciona una sucursal"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in branchOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    <div class="flex items-end">
                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="schedule-active"
                                v-model="form.is_active"
                            />
                            <div class="grid gap-1">
                                <Label for="schedule-active"
                                    >Horario activo</Label
                                >
                                <p class="text-xs text-muted-foreground">
                                    Solo los horarios activos se usan para
                                    calcular asistencias.
                                </p>
                            </div>
                        </div>
                    </div>

                    <FormField
                        label="Vigente desde"
                        for="effective-from"
                        :error="form.errors.effective_from"
                        hint="La vigencia no puede ser anterior al inicio del último periodo cerrado."
                        required
                    >
                        <Input
                            id="effective-from"
                            v-model="form.effective_from"
                            type="date"
                            required
                        />
                    </FormField>

                    <FormField
                        label="Vigente hasta"
                        for="effective-to"
                        :error="form.errors.effective_to"
                        hint="Opcional. Déjalo en blanco si el horario no vence."
                    >
                        <Input
                            id="effective-to"
                            v-model="form.effective_to"
                            type="date"
                        />
                    </FormField>
                </div>

                <InputError :message="form.errors.days" />
            </section>

            <section class="space-y-3">
                <div class="space-y-1">
                    <h2 class="text-sm font-semibold text-foreground">
                        Jornada semanal
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Los valores iniciales son solo una sugerencia; ajústalos
                        a la operación de la sucursal. Los segmentos de tipo
                        descanso no cuentan como tiempo trabajado.
                    </p>
                </div>

                <ScheduleDaysEditor
                    v-model="form.days"
                    :weekdays="weekdayList"
                    :errors="dayErrors"
                    :disabled="!props.can.manage"
                />
            </section>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link href="/schedules">
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
                    Crear horario
                </Button>
            </div>
        </form>
    </div>
</template>
