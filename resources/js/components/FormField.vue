<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    /** `id` del control asociado (accesibilidad label → input). */
    for?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    for: undefined,
    error: undefined,
    hint: undefined,
    required: false,
});
</script>

<template>
    <div class="grid gap-1.5" :class="cn(props.class)">
        <Label :for="props.for">
            {{ props.label }}
            <span
                v-if="props.required"
                class="text-destructive-strong"
                aria-hidden="true"
                >*</span
            >
        </Label>

        <slot />

        <p v-if="props.hint" class="text-xs text-muted-foreground">
            {{ props.hint }}
        </p>

        <InputError :message="props.error" />
    </div>
</template>
