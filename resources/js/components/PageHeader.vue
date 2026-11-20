<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <header
        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        :class="cn(props.class)"
    >
        <div class="min-w-0 space-y-1">
            <h1
                class="text-xl font-semibold tracking-tight text-foreground sm:text-2xl"
            >
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="max-w-3xl text-sm text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>

        <div
            v-if="$slots.actions"
            class="flex flex-wrap items-center gap-2 sm:justify-end"
        >
            <slot name="actions" />
        </div>
    </header>
</template>
