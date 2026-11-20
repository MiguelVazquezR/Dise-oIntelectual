<script setup lang="ts">
import { useId } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    open: boolean;
    title: string;
    description?: string;
    notesLabel?: string;
    notesPlaceholder?: string;
    notesHint?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'default' | 'destructive';
    processing?: boolean;
    error?: string;
    /** Texto de notas controlado por el padre. */
    notes: string;
    required?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    description: 'Agrega una nota para dejar registro de la decisión.',
    notesLabel: 'Notas (opcional)',
    notesPlaceholder: 'Motivo o comentario de la decisión',
    notesHint: undefined,
    confirmLabel: 'Confirmar',
    cancelLabel: 'Cancelar',
    variant: 'default',
    processing: false,
    error: undefined,
    required: false,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    'update:notes': [value: string];
    confirm: [];
}>();

const notesId = `decision-notes-${useId()}`;
</script>

<template>
    <ConfirmDialog
        :open="props.open"
        :title="props.title"
        :description="props.description"
        :confirm-label="props.confirmLabel"
        :cancel-label="props.cancelLabel"
        :variant="props.variant"
        :processing="props.processing"
        :confirm-disabled="props.required && props.notes.trim() === ''"
        @update:open="emit('update:open', $event)"
        @confirm="emit('confirm')"
    >
        <div class="grid gap-1.5">
            <Label :for="notesId">
                {{ props.notesLabel }}
                <span
                    v-if="props.required"
                    class="text-destructive-strong"
                    aria-hidden="true"
                    >*</span
                >
            </Label>
            <Textarea
                :id="notesId"
                :model-value="props.notes"
                :placeholder="props.notesPlaceholder"
                :aria-invalid="props.error ? true : undefined"
                :aria-required="props.required || undefined"
                rows="3"
                @update:model-value="emit('update:notes', $event)"
            />
            <p v-if="props.notesHint" class="text-xs text-muted-foreground">
                {{ props.notesHint }}
            </p>
            <InputError :message="props.error" />
        </div>
    </ConfirmDialog>
</template>
