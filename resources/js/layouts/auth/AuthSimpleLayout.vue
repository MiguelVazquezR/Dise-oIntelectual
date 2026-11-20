<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted/40 p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex items-center justify-center rounded-lg focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                        aria-label="Ir al inicio"
                    >
                        <AppLogo size="lg" />
                    </Link>
                    <div class="space-y-1.5 text-center">
                        <h1
                            class="text-xl font-semibold tracking-tight text-foreground"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-center text-sm text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
