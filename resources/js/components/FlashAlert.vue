<script setup lang="ts">
import { AlertTriangle, Check, X } from '@lucide/vue';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type FlashMessages = {
    success?: string | null;
    error?: string | null;
};

/**
 * Aviso de `flash.success` / `flash.error` compartido por Inertia
 * (`HandleInertiaRequests`). Complementa el toast global y permite cerrarlo.
 */
const page = usePage<{ flash?: FlashMessages }>();

const dismissed = ref(false);

const message = computed<{ type: 'success' | 'error'; text: string } | null>(
    () => {
        const flash = page.props.flash;

        if (flash?.error) {
            return { type: 'error', text: flash.error };
        }

        if (flash?.success) {
            return { type: 'success', text: flash.success };
        }

        return null;
    },
);

watch(message, () => {
    dismissed.value = false;
});
</script>

<template>
    <div
        v-if="message && !dismissed"
        role="status"
        aria-live="polite"
        class="flex items-start gap-3 rounded-lg border px-3 py-2 text-sm"
        :class="
            message.type === 'success'
                ? 'border-emerald-600/30 bg-emerald-50 text-emerald-900 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-100'
                : 'border-destructive/40 bg-destructive/10 text-destructive-strong'
        "
    >
        <Check
            v-if="message.type === 'success'"
            class="mt-0.5 size-4 shrink-0"
            aria-hidden="true"
        />
        <AlertTriangle
            v-else
            class="mt-0.5 size-4 shrink-0"
            aria-hidden="true"
        />
        <p class="min-w-0 flex-1 text-pretty">{{ message.text }}</p>
        <button
            type="button"
            class="shrink-0 rounded-md p-1 transition-colors hover:bg-black/5 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
            aria-label="Cerrar aviso"
            @click="dismissed = true"
        >
            <X class="size-4" aria-hidden="true" />
        </button>
    </div>
</template>
