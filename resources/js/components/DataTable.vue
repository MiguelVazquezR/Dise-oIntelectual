<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type Props = {
    /** Mientras es `true` se muestran filas esqueleto. */
    loading?: boolean;
    /** Sin registros: se renderiza el slot `empty`. */
    empty?: boolean;
    /** Texto accesible de la tabla (se renderiza como `<caption>` oculto). */
    caption?: string;
    loadingRows?: number;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    empty: false,
    caption: undefined,
    loadingRows: 5,
});
</script>

<template>
    <div
        class="overflow-hidden rounded-lg border border-border bg-card"
        :class="cn(props.class)"
    >
        <div v-if="$slots.header" class="border-b border-border p-3">
            <slot name="header" />
        </div>

        <div
            v-if="props.loading"
            class="space-y-2 p-4"
            role="status"
            aria-live="polite"
        >
            <span class="sr-only">Cargando información…</span>
            <Skeleton
                v-for="row in props.loadingRows"
                :key="row"
                class="h-9 w-full"
            />
        </div>

        <div v-else-if="props.empty" class="p-4">
            <slot name="empty" />
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full caption-bottom text-sm">
                <caption v-if="props.caption" class="sr-only">
                    {{ props.caption }}
                </caption>
                <slot name="body" />
            </table>
        </div>

        <div v-if="$slots.default" class="border-t border-border p-3">
            <slot />
        </div>
    </div>
</template>
