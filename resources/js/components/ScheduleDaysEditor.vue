<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
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
import type { NormalizedWeekday } from '@/lib/scheduleForm';
import type { ScheduleDayForm, ScheduleSegmentForm } from '@/types';

type Props = {
    weekdays: NormalizedWeekday[];
    /** Errores de validación del backend con claves `days.{i}.segments.{j}.campo`. */
    errors?: Record<string, string | undefined>;
    disabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    errors: () => ({}),
    disabled: false,
});

const days = defineModel<ScheduleDayForm[]>({ required: true });

function addSegment(dayIndex: number): void {
    days.value[dayIndex].segments.push({
        start_time: '09:00',
        end_time: '14:00',
        kind: 'work',
    });
}

function removeSegment(dayIndex: number, segmentIndex: number): void {
    days.value[dayIndex].segments.splice(segmentIndex, 1);
}

function crossesMidnight(segment: ScheduleSegmentForm): boolean {
    return (
        segment.start_time !== '' &&
        segment.end_time !== '' &&
        segment.end_time <= segment.start_time
    );
}

function weekdayLabel(dayIndex: number): string {
    return props.weekdays[dayIndex]?.label ?? String(dayIndex + 1);
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="(day, dayIndex) in days"
            :key="day.weekday"
            class="rounded-lg border border-border bg-card p-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-foreground">
                    {{ weekdayLabel(dayIndex) }}
                </h3>

                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            :id="`rest-day-${day.weekday}`"
                            v-model="day.is_rest_day"
                            :disabled="props.disabled"
                        />
                        <Label
                            :for="`rest-day-${day.weekday}`"
                            class="text-sm font-normal"
                        >
                            Día de descanso
                        </Label>
                    </div>

                    <div class="grid gap-1">
                        <Label
                            :for="`tolerance-${day.weekday}`"
                            class="text-xs text-muted-foreground"
                        >
                            Tolerancia de retardo (min)
                        </Label>
                        <Input
                            :id="`tolerance-${day.weekday}`"
                            v-model="day.late_tolerance_minutes"
                            type="number"
                            min="0"
                            max="240"
                            step="1"
                            inputmode="numeric"
                            class="w-28"
                            :disabled="props.disabled || day.is_rest_day"
                        />
                    </div>
                </div>
            </div>

            <InputError
                class="mt-1"
                :message="
                    props.errors[`days.${dayIndex}.late_tolerance_minutes`]
                "
            />

            <p
                v-if="day.is_rest_day"
                class="mt-3 rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground"
            >
                Día de descanso: no se registran segmentos.
            </p>

            <div v-else class="mt-3 space-y-2">
                <div
                    v-for="(segment, segmentIndex) in day.segments"
                    :key="segmentIndex"
                    class="rounded-md border border-border/70 p-2.5"
                >
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="grid gap-1">
                            <Label
                                :for="`kind-${day.weekday}-${segmentIndex}`"
                                class="text-xs text-muted-foreground"
                                >Tipo</Label
                            >
                            <Select
                                v-model="segment.kind"
                                :disabled="props.disabled"
                            >
                                <SelectTrigger
                                    :id="`kind-${day.weekday}-${segmentIndex}`"
                                    class="w-32"
                                    aria-label="Tipo de segmento"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="work">Turno</SelectItem>
                                    <SelectItem value="break"
                                        >Descanso</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-1">
                            <Label
                                :for="`start-${day.weekday}-${segmentIndex}`"
                                class="text-xs text-muted-foreground"
                                >Inicio</Label
                            >
                            <Input
                                :id="`start-${day.weekday}-${segmentIndex}`"
                                v-model="segment.start_time"
                                type="time"
                                class="w-32"
                                :disabled="props.disabled"
                            />
                        </div>

                        <div class="grid gap-1">
                            <Label
                                :for="`end-${day.weekday}-${segmentIndex}`"
                                class="text-xs text-muted-foreground"
                                >Fin</Label
                            >
                            <Input
                                :id="`end-${day.weekday}-${segmentIndex}`"
                                v-model="segment.end_time"
                                type="time"
                                class="w-32"
                                :disabled="props.disabled"
                            />
                        </div>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="text-destructive-strong"
                            :disabled="props.disabled"
                            :aria-label="`Quitar segmento ${segmentIndex + 1} de ${weekdayLabel(dayIndex)}`"
                            @click="removeSegment(dayIndex, segmentIndex)"
                        >
                            <Trash2 aria-hidden="true" />
                        </Button>
                    </div>

                    <p
                        v-if="crossesMidnight(segment)"
                        class="mt-1 text-xs text-amber-700 dark:text-amber-300"
                    >
                        El turno cruza medianoche (fin ≤ inicio); se interpreta
                        como fin al día siguiente.
                    </p>

                    <div class="mt-1 grid gap-1 sm:grid-cols-3">
                        <InputError
                            :message="
                                props.errors[
                                    `days.${dayIndex}.segments.${segmentIndex}.start_time`
                                ]
                            "
                        />
                        <InputError
                            :message="
                                props.errors[
                                    `days.${dayIndex}.segments.${segmentIndex}.end_time`
                                ]
                            "
                        />
                        <InputError
                            :message="
                                props.errors[
                                    `days.${dayIndex}.segments.${segmentIndex}.kind`
                                ]
                            "
                        />
                    </div>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="props.disabled"
                    @click="addSegment(dayIndex)"
                >
                    <Plus aria-hidden="true" />
                    Añadir segmento
                </Button>

                <p
                    v-if="!day.segments.length"
                    class="text-xs text-muted-foreground"
                >
                    Agrega al menos un segmento de turno para este día.
                </p>

                <InputError
                    :message="props.errors[`days.${dayIndex}.segments`]"
                />
            </div>
        </div>
    </div>
</template>
