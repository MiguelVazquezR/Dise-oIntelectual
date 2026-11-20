<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import { Check, Monitor, Moon, Sun } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/types';

const { appearance, updateAppearance } = useAppearance();

const options: { value: Appearance; label: string; icon: LucideIcon }[] = [
    { value: 'light', label: 'Claro', icon: Sun },
    { value: 'dark', label: 'Oscuro', icon: Moon },
    { value: 'system', label: 'Sistema', icon: Monitor },
];
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-8 text-muted-foreground hover:text-foreground"
                aria-label="Cambiar apariencia"
            >
                <Sun v-if="appearance === 'light'" class="size-4" />
                <Moon v-else-if="appearance === 'dark'" class="size-4" />
                <Monitor v-else class="size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuLabel>Apariencia</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-for="option in options"
                :key="option.value"
                class="cursor-pointer"
                @select="updateAppearance(option.value)"
            >
                <component :is="option.icon" class="size-4" />
                <span>{{ option.label }}</span>
                <Check
                    v-if="appearance === option.value"
                    class="ml-auto size-4 text-primary"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
