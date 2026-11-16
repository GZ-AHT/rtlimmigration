<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import FormField from '@/components/panel/FormField.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

/**
 * Ask for a refund of one confirmed payment (REQ §12.5 rule 8). The request
 * goes for approval; nothing is paid back until Accounts processes it.
 */
const props = defineProps<{
    submitUrl: string | null;
    /** What can still be refunded, e.g. "5000.00". */
    refundable: string;
    refundableLabel: string;
    currency: string;
    /** Shown in the description, e.g. the receipt number. */
    subject: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({ amount: props.refundable, reason: '' });

watch(open, (isOpen) => {
    if (isOpen) {
        form.clearErrors();
        form.amount = props.refundable;
        form.reason = '';
    }
});

function submit(): void {
    if (!props.submitUrl) {
        return;
    }

    form.post(props.submitUrl, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="rounded-[14px] sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">
                    Request a refund
                </DialogTitle>
                <DialogDescription class="text-xs">
                    {{ subject }} · up to {{ refundableLabel }} can be refunded.
                    The request is reviewed before any money is returned.
                </DialogDescription>
            </DialogHeader>
            <form
                class="flex flex-col gap-3.5"
                novalidate
                @submit.prevent="submit"
            >
                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    :label="`Amount (${currency})`"
                    required
                    :error="form.errors.amount"
                >
                    <Input
                        :id="id"
                        v-model="form.amount"
                        inputmode="decimal"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Reason"
                    required
                    :error="form.errors.reason"
                >
                    <TextArea
                        :id="id"
                        v-model="form.reason"
                        :rows="3"
                        :invalid="invalid"
                    />
                </FormField>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        ><Spinner v-if="form.processing" /> Send request</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
