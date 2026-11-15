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

/**
 * Confirmation step for destructive or irreversible actions (delete,
 * reject a payment, record a visa decision). Keeps itself open while
 * `loading` so a slow request can't be double-submitted.
 */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        confirmLabel?: string;
        cancelLabel?: string;
        destructive?: boolean;
        loading?: boolean;
    }>(),
    {
        description: undefined,
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
        destructive: false,
        loading: false,
    },
);

const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ confirm: [] }>();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="rounded-[14px] sm:max-w-[460px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">
                    {{ title }}
                </DialogTitle>
                <DialogDescription v-if="description" class="text-xs">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>
            <slot />
            <DialogFooter class="gap-2">
                <Button
                    variant="outline"
                    :disabled="loading"
                    @click="open = false"
                >
                    {{ cancelLabel }}
                </Button>
                <Button
                    :variant="destructive ? 'destructive' : 'default'"
                    :disabled="loading"
                    @click="emit('confirm')"
                >
                    <Spinner v-if="loading" />
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
