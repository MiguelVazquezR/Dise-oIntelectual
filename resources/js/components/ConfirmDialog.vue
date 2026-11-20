<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    open: boolean;
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'destructive' | 'default';
    processing?: boolean;
    /** Bloquea la acción de confirmación (por ejemplo, notas obligatorias). */
    confirmDisabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    description: undefined,
    confirmLabel: 'Confirmar',
    cancelLabel: 'Cancelar',
    variant: 'destructive',
    processing: false,
    confirmDisabled: false,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [];
}>();

function close(): void {
    emit('update:open', false);
}

function confirm(): void {
    emit('confirm');
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader class="space-y-2">
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <slot />

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="props.processing"
                    @click="close"
                >
                    {{ props.cancelLabel }}
                </Button>
                <Button
                    type="button"
                    :variant="props.variant"
                    :disabled="props.processing || props.confirmDisabled"
                    @click="confirm"
                >
                    <Spinner v-if="props.processing" />
                    {{ props.confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
