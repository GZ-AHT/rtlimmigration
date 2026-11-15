<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FileUpload from '@/components/panel/FileUpload.vue';
import FormField from '@/components/panel/FormField.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { PaymentMethodOption } from '@/types';

/**
 * Submit a payment against an invoice (REQ §12.1 step 4), or, for Accounts,
 * record money received in person. The server re-checks every rule.
 */
const props = withDefaults(
    defineProps<{
        submitUrl: string;
        methods: PaymentMethodOption[];
        outstanding: string;
        currency: string;
        evidence: { max_mb: number; extensions: string[] };
        submitLabel?: string;
        allowEvidence?: boolean;
    }>(),
    { submitLabel: 'Submit payment', allowEvidence: true },
);

const emit = defineEmits<{ done: [] }>();

const today = new Date().toISOString().slice(0, 10);
const form = useForm({
    payment_method_id: props.methods[0]?.value ?? '',
    amount: props.outstanding,
    transaction_reference: '',
    paid_on: today,
    evidence: [] as File[],
    notes: '',
});

const method = computed(() =>
    props.methods.find((m) => m.value === form.payment_method_id),
);
// Business-rule refusals (e.g. invoice already paid) come back under "request".
const requestError = computed(
    () => (form.errors as Record<string, string | undefined>).request,
);
const evidenceError = computed(
    () =>
        Object.entries(form.errors).find(
            ([k]) => k === 'evidence' || k.startsWith('evidence.'),
        )?.[1],
);

function submit(): void {
    form.transform((d) => ({
        payment_method_id: d.payment_method_id,
        amount: d.amount,
        transaction_reference: d.transaction_reference || null,
        paid_on: d.paid_on,
        notes: d.notes || null,
        ...(props.allowEvidence && d.evidence[0]
            ? { evidence: d.evidence[0] }
            : {}),
    })).post(props.submitUrl, {
        forceFormData: props.allowEvidence,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('done');
        },
    });
}
</script>

<template>
    <form class="flex flex-col gap-3.5" novalidate @submit.prevent="submit">
        <FormField
            v-slot="{ id, invalid }"
            label="Payment method"
            required
            :error="form.errors.payment_method_id"
        >
            <SelectInput
                :id="id"
                v-model="form.payment_method_id"
                :options="methods"
                :invalid="invalid"
            />
        </FormField>
        <div
            v-if="method?.instructions"
            class="rounded-lg border border-info/30 bg-info-soft p-3 text-[13px] whitespace-pre-line text-info"
        >
            {{ method.instructions }}
        </div>
        <div class="grid gap-3.5 sm:grid-cols-2">
            <FormField
                v-slot="{ id, invalid, describedBy }"
                :label="`Amount (${currency})`"
                required
                :hint="`Outstanding: ${outstanding}`"
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
                label="Paid on"
                required
                :error="form.errors.paid_on"
            >
                <Input
                    :id="id"
                    v-model="form.paid_on"
                    type="date"
                    :max="today"
                    :aria-invalid="invalid"
                />
            </FormField>
        </div>
        <FormField
            v-if="method?.requires_reference || form.transaction_reference"
            v-slot="{ id, invalid, describedBy }"
            label="Transaction ID / reference"
            :required="method?.requires_reference"
            hint="From the bank slip, bKash/Nagad SMS or card receipt."
            :error="form.errors.transaction_reference"
        >
            <Input
                :id="id"
                v-model="form.transaction_reference"
                :aria-invalid="invalid"
                :aria-describedby="describedBy"
            />
        </FormField>
        <FileUpload
            v-if="allowEvidence"
            v-model="form.evidence"
            :label="
                method?.requires_evidence
                    ? 'Proof of payment (required)'
                    : 'Proof of payment (optional)'
            "
            :accept="evidence.extensions.map((e) => `.${e}`).join(',')"
            :max-size-mb="evidence.max_mb"
            :error="evidenceError"
            :hint="`Deposit slip or screenshot · ${evidence.extensions.join(', ').toUpperCase()} · up to ${evidence.max_mb} MB`"
        />
        <FormField
            v-slot="{ id, invalid }"
            label="Note"
            :error="form.errors.notes"
        >
            <TextArea
                :id="id"
                v-model="form.notes"
                :rows="2"
                :invalid="invalid"
            />
        </FormField>
        <p v-if="requestError" class="text-xs text-danger" role="alert">
            {{ requestError }}
        </p>
        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing"
                ><Spinner v-if="form.processing" /> {{ submitLabel }}</Button
            >
        </div>
    </form>
</template>
