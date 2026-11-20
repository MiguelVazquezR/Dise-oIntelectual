<script setup lang="ts">
/**
 * Layout de impresión para documentos (recibos de pre-nómina).
 *
 * - Fondo blanco y tipografía oscura, independientes del tema claro/oscuro.
 * - Sin sidebar ni encabezado de la aplicación (D-05: se imprime con el
 *   navegador, "Guardar como PDF").
 * - Los controles de pantalla se ocultan con `print:hidden` desde la página.
 */
defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <div class="min-h-svh bg-white text-neutral-900 print:bg-white">
        <div class="h-1 bg-destructive print:hidden" aria-hidden="true"></div>

        <main
            class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 print:max-w-none print:px-0 print:py-0"
        >
            <slot />
        </main>
    </div>
</template>
