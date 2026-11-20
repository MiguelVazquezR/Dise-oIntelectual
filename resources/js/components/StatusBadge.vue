<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { StatusVariant } from '@/types';

type Props = {
    variant?: StatusVariant;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'neutral',
});

/**
 * Cada variante cumple AA en claro y oscuro:
 * el rojo de marca se usa como fondo y `destructive-strong` para texto pequeño.
 */
const variantClasses: Record<StatusVariant, string> = {
    neutral: 'border-border bg-muted text-foreground',
    success:
        'border-emerald-600/30 bg-emerald-50 text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-200',
    warning:
        'border-amber-600/40 bg-amber-50 text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-100',
    danger: 'border-destructive/40 bg-destructive/10 text-destructive-strong dark:bg-destructive/20 dark:text-destructive-strong',
    info: 'border-sky-700/30 bg-sky-50 text-sky-900 dark:border-sky-400/30 dark:bg-sky-500/15 dark:text-sky-100',
};

const classes = computed(() => cn(variantClasses[props.variant], props.class));
</script>

<template>
    <Badge variant="outline" :class="classes">
        <slot />
    </Badge>
</template>
