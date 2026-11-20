<script setup lang="ts">
import { Check, Search, X } from '@lucide/vue';
import { computed, ref, useId, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { SelectOption } from '@/types';

type Props = {
    /** Id del colaborador seleccionado (`''` = sin selección). */
    modelValue: string;
    options: SelectOption[];
    inputId?: string;
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    allowClear?: boolean;
    /** Filas que se muestran en el desplegable. */
    limit?: number;
};

const props = withDefaults(defineProps<Props>(), {
    inputId: undefined,
    placeholder: 'Buscar por nombre o número',
    disabled: false,
    invalid: false,
    allowClear: true,
    limit: 8,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const listboxId = `employee-select-${useId()}`;
const query = ref('');
const isOpen = ref(false);
const activeIndex = ref(-1);

const selected = computed<SelectOption | null>(
    () =>
        props.options.find((option) => option.value === props.modelValue) ??
        null,
);

const selectedLabel = computed(() => selected.value?.label ?? '');

const matches = computed<SelectOption[]>(() => {
    const term = query.value.trim().toLowerCase();
    const isSelectedLabel =
        selectedLabel.value !== '' &&
        term === selectedLabel.value.trim().toLowerCase();

    if (term === '' || isSelectedLabel) {
        return props.options.slice(0, props.limit);
    }

    return props.options
        .filter((option) => {
            return (
                option.label.toLowerCase().includes(term) ||
                option.value.toLowerCase().includes(term)
            );
        })
        .slice(0, props.limit);
});

const activeOptionId = computed(() =>
    activeIndex.value >= 0 && matches.value[activeIndex.value]
        ? `${listboxId}-${activeIndex.value}`
        : undefined,
);

watch(
    () => [props.modelValue, props.options] as const,
    () => {
        query.value = selectedLabel.value;
    },
    { immediate: true, deep: true },
);

function openList(): void {
    if (props.disabled) {
        return;
    }

    isOpen.value = true;
    activeIndex.value = -1;
}

function closeList(): void {
    isOpen.value = false;
    activeIndex.value = -1;
}

function closeSoon(): void {
    window.setTimeout(() => {
        closeList();
        // Sin selección nueva, el campo vuelve a reflejar el valor elegido.
        query.value = selectedLabel.value;
    }, 120);
}

function selectOption(option: SelectOption): void {
    emit('update:modelValue', option.value);
    query.value = option.label;
    closeList();
}

function clearSelection(): void {
    emit('update:modelValue', '');
    query.value = '';
    activeIndex.value = -1;
}

function onInput(): void {
    isOpen.value = true;
    activeIndex.value = -1;
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        openList();
        activeIndex.value = Math.min(
            activeIndex.value + 1,
            matches.value.length - 1,
        );

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);

        return;
    }

    if (event.key === 'Enter') {
        const option =
            matches.value[activeIndex.value] ?? matches.value[0] ?? null;

        if (option) {
            event.preventDefault();
            selectOption(option);
        }

        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        closeList();
    }
}
</script>

<template>
    <div class="relative">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
        />
        <Input
            :id="props.inputId"
            v-model="query"
            type="text"
            role="combobox"
            autocomplete="off"
            :placeholder="props.placeholder"
            :disabled="props.disabled"
            :aria-invalid="props.invalid || undefined"
            :aria-expanded="isOpen"
            :aria-controls="listboxId"
            :aria-autocomplete="'list'"
            :aria-activedescendant="activeOptionId"
            class="pl-9"
            :class="props.allowClear && props.modelValue ? 'pr-9' : undefined"
            @focus="openList"
            @input="onInput"
            @keydown="onKeydown"
            @blur="closeSoon"
        />

        <button
            v-if="props.allowClear && props.modelValue"
            type="button"
            class="absolute top-1/2 right-1 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
            aria-label="Quitar colaborador seleccionado"
            @click="clearSelection"
        >
            <X class="size-4" aria-hidden="true" />
        </button>

        <ul
            v-if="isOpen && matches.length > 0"
            :id="listboxId"
            role="listbox"
            aria-label="Colaboradores disponibles"
            class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md"
        >
            <li
                v-for="(option, index) in matches"
                :id="`${listboxId}-${index}`"
                :key="option.value"
                role="option"
                :aria-selected="option.value === props.modelValue"
                :class="
                    cn(
                        'flex cursor-pointer items-center justify-between gap-2 rounded-sm px-2 py-2 text-sm',
                        index === activeIndex
                            ? 'bg-accent text-accent-foreground'
                            : 'text-popover-foreground',
                    )
                "
                @mousedown.prevent="selectOption(option)"
                @mouseenter="activeIndex = index"
            >
                <span class="min-w-0 truncate">{{ option.label }}</span>
                <Check
                    v-if="option.value === props.modelValue"
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </li>
        </ul>

        <p
            v-else-if="isOpen && matches.length === 0"
            class="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover p-3 text-sm text-muted-foreground shadow-md"
        >
            Sin coincidencias. Ajusta la búsqueda.
        </p>
    </div>
</template>
