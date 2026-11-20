<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import type { PaginationLink, PaginationMeta } from '@/types';

type Props = {
    /** Enlaces del paginador de Laravel (`$paginator->links()`). */
    links: PaginationLink[];
    meta?: PaginationMeta | null;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    meta: null,
});

const LABELS: Record<string, string> = {
    '&laquo; Previous': 'Anterior',
    Previous: 'Anterior',
    '&raquo; Next': 'Siguiente',
    Next: 'Siguiente',
    '...': '…',
};

const linkBase =
    'inline-flex h-8 min-w-8 items-center justify-center gap-1 rounded-md border px-2 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none';

const summary = computed(() => {
    const meta = props.meta;

    if (!meta) {
        return null;
    }

    if (meta.total === 0 || meta.from === null || meta.to === null) {
        return 'Sin registros';
    }

    return `Mostrando ${meta.from}–${meta.to} de ${meta.total} registros`;
});

const showNavigation = computed(() => {
    if (props.meta) {
        return props.meta.last_page > 1;
    }

    return props.links.length > 3;
});

function labelOf(link: PaginationLink): string {
    return LABELS[link.label] ?? link.label;
}

function isPrevious(link: PaginationLink): boolean {
    return /previous|anterior/i.test(link.label);
}

function isNext(link: PaginationLink): boolean {
    return /next|siguiente/i.test(link.label);
}

function linkClasses(link: PaginationLink): string {
    return cn(
        linkBase,
        link.active
            ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground'
            : 'border-border bg-background text-foreground hover:bg-accent hover:text-accent-foreground',
    );
}

function disabledClasses(): string {
    return cn(
        linkBase,
        'cursor-not-allowed border-border bg-background text-muted-foreground opacity-60',
    );
}
</script>

<template>
    <div
        v-if="summary || showNavigation"
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        :class="cn(props.class)"
    >
        <p v-if="summary" class="text-sm text-muted-foreground">
            {{ summary }}
        </p>

        <nav
            v-if="showNavigation"
            aria-label="Paginación"
            class="flex flex-wrap items-center gap-1"
        >
            <template v-for="(link, index) in props.links" :key="index">
                <span
                    v-if="link.label === '...'"
                    class="px-2 text-sm text-muted-foreground"
                    aria-hidden="true"
                >
                    …
                </span>

                <Link
                    v-else-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    :class="linkClasses(link)"
                    :aria-current="link.active ? 'page' : undefined"
                    :aria-label="
                        isPrevious(link)
                            ? 'Página anterior'
                            : isNext(link)
                              ? 'Página siguiente'
                              : `Página ${labelOf(link)}`
                    "
                >
                    <ChevronLeft
                        v-if="isPrevious(link)"
                        class="size-4"
                        aria-hidden="true"
                    />
                    <span>{{ labelOf(link) }}</span>
                    <ChevronRight
                        v-if="isNext(link)"
                        class="size-4"
                        aria-hidden="true"
                    />
                </Link>

                <span
                    v-else
                    :class="disabledClasses()"
                    aria-disabled="true"
                >
                    <ChevronLeft
                        v-if="isPrevious(link)"
                        class="size-4"
                        aria-hidden="true"
                    />
                    <span>{{ labelOf(link) }}</span>
                    <ChevronRight
                        v-if="isNext(link)"
                        class="size-4"
                        aria-hidden="true"
                    />
                </span>
            </template>
        </nav>
    </div>
</template>
