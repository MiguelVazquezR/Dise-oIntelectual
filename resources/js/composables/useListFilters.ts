import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, reactive, ref } from 'vue';
import type { Ref } from 'vue';

type FilterValues = Record<string, string>;

const SEARCH_DEBOUNCE_MS = 350;

type UseListFiltersReturn<T extends FilterValues> = {
    filters: T;
    isFiltering: Ref<boolean>;
    /** Aplica los filtros de inmediato (selects, fechas). */
    apply: () => void;
    /** Aplica los filtros tras la pausa de escritura (búsqueda). */
    applyDebounced: () => void;
    /** Limpia todos los filtros y vuelve a consultar. */
    reset: () => void;
};

/**
 * Filtros de listado con `router.get` + `preserveState`.
 *
 * El backend es la fuente de verdad: los valores se envían como query string
 * sin recortar ni transformar, y los vacíos se omiten para dejar el default.
 */
export function useListFilters<T extends FilterValues>(
    url: string,
    initial: T,
): UseListFiltersReturn<T> {
    const filters = reactive({ ...initial }) as T;
    const isFiltering = ref(false);
    let searchTimer: ReturnType<typeof setTimeout> | undefined;

    function currentQuery(): Record<string, string> {
        return Object.fromEntries(
            Object.entries(filters).filter(([, value]) => value !== ''),
        ) as Record<string, string>;
    }

    function apply(): void {
        window.clearTimeout(searchTimer);
        router.get(url, currentQuery(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                isFiltering.value = true;
            },
            onFinish: () => {
                isFiltering.value = false;
            },
        });
    }

    function applyDebounced(): void {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(apply, SEARCH_DEBOUNCE_MS);
    }

    function reset(): void {
        for (const key of Object.keys(filters)) {
            (filters as FilterValues)[key] = '';
        }

        apply();
    }

    onBeforeUnmount(() => window.clearTimeout(searchTimer));

    return { filters, isFiltering, apply, applyDebounced, reset };
}
