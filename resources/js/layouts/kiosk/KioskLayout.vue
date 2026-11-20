<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';

/**
 * Layout del checador: pantalla completa sin sidebar.
 * La página recibe props de Inertia; el layout no declara props propias.
 */
defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <div class="flex min-h-svh flex-col bg-sidebar text-sidebar-foreground">
        <div class="h-1 bg-destructive" aria-hidden="true"></div>

        <header
            class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6"
        >
            <AppLogo size="lg" />
            <slot name="actions" />
        </header>

        <main class="flex-1 px-3 pb-4 sm:px-6">
            <slot />
        </main>
    </div>
</template>
