<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Isologo de marca: contenedor blanco para que el JPEG se vea bien sobre azul marino u oscuro. -->
    <span
        class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-md bg-white p-0.5 ring-1 ring-black/10 dark:ring-white/15"
        :class="className"
    >
        <img
            src="/images/isologo.jpeg"
            alt="Isologo de Diseño Intelectual"
            class="size-full object-contain"
            width="173"
            height="153"
            decoding="async"
            v-bind="$attrs"
        />
    </span>
</template>
