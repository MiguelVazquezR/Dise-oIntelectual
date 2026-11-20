<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import { TrendingDown, TrendingUp } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

type Trend = 'up' | 'down' | 'neutral';

type Props = {
    label: string;
    value: string | number;
    /** Variación ya formateada, por ejemplo "+3.2 %" o "-12". */
    variation?: string | number | null;
    variationLabel?: string;
    trend?: Trend;
    icon?: LucideIcon;
    hint?: string;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    variation: null,
    trend: 'neutral',
    icon: undefined,
    hint: undefined,
    variationLabel: undefined,
});

const trendClasses: Record<Trend, string> = {
    neutral: 'text-muted-foreground',
    up: 'text-emerald-700 dark:text-emerald-300',
    down: 'text-destructive-strong',
};

const variationClasses = computed(() => trendClasses[props.trend]);
</script>

<template>
    <div
        class="rounded-lg border border-border bg-card p-4"
        :class="cn(props.class)"
    >
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-medium text-muted-foreground">
                {{ label }}
            </p>
            <span
                v-if="props.icon"
                class="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary"
            >
                <component
                    :is="props.icon"
                    class="size-4"
                    aria-hidden="true"
                />
            </span>
        </div>

        <p class="mt-2 text-2xl font-semibold tracking-tight text-foreground">
            {{ value }}
        </p>

        <p
            v-if="props.variation !== null && props.variation !== undefined"
            class="mt-1 flex flex-wrap items-center gap-1 text-xs"
            :class="variationClasses"
        >
            <TrendingUp
                v-if="props.trend === 'up'"
                class="size-3.5"
                aria-hidden="true"
            />
            <TrendingDown
                v-else-if="props.trend === 'down'"
                class="size-3.5"
                aria-hidden="true"
            />
            <span class="font-medium">{{ props.variation }}</span>
            <span v-if="props.variationLabel" class="text-muted-foreground">
                {{ props.variationLabel }}
            </span>
        </p>

        <p v-if="props.hint" class="mt-1 text-xs text-muted-foreground">
            {{ props.hint }}
        </p>
    </div>
</template>
