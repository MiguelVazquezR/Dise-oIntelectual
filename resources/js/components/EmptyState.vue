<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import { Inbox } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    icon?: LucideIcon;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    icon: undefined,
});
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border bg-muted/30 px-6 py-10 text-center"
        :class="cn(props.class)"
    >
        <span
            class="flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary"
        >
            <component
                :is="props.icon ?? Inbox"
                class="size-5"
                aria-hidden="true"
            />
        </span>

        <div class="space-y-1">
            <p class="text-sm font-medium text-foreground">{{ title }}</p>
            <p
                v-if="description"
                class="mx-auto max-w-md text-sm text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>

        <div v-if="$slots.action" class="pt-1">
            <slot name="action" />
        </div>
    </div>
</template>
