<script setup lang="ts">
import AppearanceToggle from '@/components/AppearanceToggle.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14 md:px-6"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <Separator
                orientation="vertical"
                class="mr-1 hidden h-4! sm:block"
            />
            <div class="min-w-0">
                <Breadcrumbs
                    v-if="breadcrumbs && breadcrumbs.length > 0"
                    :breadcrumbs="breadcrumbs"
                />
            </div>
        </div>

        <AppearanceToggle />
    </header>
</template>
