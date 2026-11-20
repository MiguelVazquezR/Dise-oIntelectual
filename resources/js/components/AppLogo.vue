<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

type Props = {
    /** `sm` para el sidebar, `lg` para pantallas de autenticación. */
    size?: 'sm' | 'lg';
};

withDefaults(defineProps<Props>(), {
    size: 'sm',
});
</script>

<template>
    <div class="flex min-w-0 items-center gap-2 overflow-hidden">
        <!-- Sidebar colapsado: isologo. -->
        <span
            class="hidden shrink-0 rounded-md bg-white p-1 ring-1 ring-black/10 group-data-[collapsible=icon]:flex dark:ring-white/15"
        >
            <AppLogoIcon class="size-6" />
        </span>

        <!-- Sidebar desplegado y encabezado: logo completo de la marca. -->
        <span
            class="flex min-w-0 items-center rounded-md bg-white ring-1 ring-black/10 group-data-[collapsible=icon]:hidden dark:ring-white/15"
            :class="size === 'lg' ? 'px-2 py-2' : 'px-1.5 py-1'"
        >
            <img
                src="/images/logo.jpeg"
                alt="Diseño Intelectual"
                class="w-auto object-contain"
                :class="size === 'lg' ? 'h-16 max-w-20' : 'h-10 max-w-[7.5rem]'"
                width="209"
                height="230"
                decoding="async"
            />
        </span>
    </div>
</template>
