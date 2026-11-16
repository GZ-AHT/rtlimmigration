<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatDateTime, relativeTime } from '@/lib/format';
import type { HistoryEntry, Option, RefundRow } from '@/types';

const props = defineProps<{
    refund: RefundRow & {
        counselor: string | null;
        payment: {
            amount_label: string;
            refunded_label: string;
            method: string;
            transaction_reference: string | null;
            paid_on: string;
            url: string | null;
        };
        invoice_url: string;
    };
    history: HistoryEntry[];
    methods: (Option & { requires_reference: boolean })[];
    can: { approve: boolean; reject: boolean; process: boolean };
    isRequester: boolean;
    urls: {
        index: string;
        approve: string;
        reject: string;
        process: string | null;
    };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Refund requests', href: props.urls.index }],
});

const dialog = ref<null | 'approve' | 'reject'>(null);
const note = ref('');
const busy = ref(false);

function decide(url: string, data: Record<string, string>): void {
    router.patch(url, data, {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onError: (e) => toast.error(Object.values(e)[0] ?? 'Could not save.'),
        onFinish: () => {
            busy.value = false;
            dialog.value = null;
            note.value = '';
        },
    });
}

const payout = useForm({
    payment_method_id: props.methods[0]?.value ?? '',
    transaction_reference: '',
    notes: '',
});
const payoutMethod = computed(() =>
    props.methods.find((m) => m.value === payout.payment_method_id),
);
// Business-rule refusals (e.g. already paid out) come back under "refund".
const payoutError = computed(
    () => (payout.errors as Record<string, string | undefined>).refund,
);

function process(): void {
    if (!props.urls.process) {
        return;
    }

    payout
        .transform((d) => ({
            payment_method_id: d.payment_method_id,
            transaction_reference: d.transaction_reference || null,
            notes: d.notes || null,
        }))
        .patch(props.urls.process, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Refund ${refund.refund_number}`" />

    <PageHeader
        :eyebrow="`${refund.refund_number} · ${refund.invoice_number}`"
        :title="refund.amount_label"
        :description="`${refund.student} · ${refund.student_number}`"
    >
        <template #actions>
            <StatusBadge
                :label="refund.status_label"
                :tone="refund.status_tone"
                class="self-center"
            />
            <Button
                v-if="can.reject"
                variant="outline"
                :disabled="busy"
                @click="dialog = 'reject'"
                ><AppIcon name="block" :size="18" /> Reject</Button
            >
            <Button
                v-if="can.approve"
                :disabled="busy"
                @click="dialog = 'approve'"
                ><AppIcon name="task_alt" :size="18" /> Approve refund</Button
            >
        </template>
    </PageHeader>

    <div
        v-if="isRequester"
        class="mb-4 flex items-center gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-[13px] text-warning"
    >
        <AppIcon name="gpp_maybe" :size="18" />
        You requested this refund, so someone else must approve it.
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(320px,1fr)]">
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard title="Refund">
                <dl class="grid gap-3 text-[13px] sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Amount</dt>
                        <dd class="font-display text-lg font-bold">
                            {{ refund.amount_label }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Requested</dt>
                        <dd class="font-medium">
                            {{ formatDateTime(refund.created_at) }} by
                            {{ refund.requested_by ?? '—' }}
                        </dd>
                    </div>
                    <div v-if="refund.approved_by">
                        <dt class="text-muted-foreground">Approved</dt>
                        <dd class="font-medium">
                            {{ formatDateTime(refund.approved_at) }} by
                            {{ refund.approved_by }}
                        </dd>
                    </div>
                    <div v-if="refund.processed_at">
                        <dt class="text-muted-foreground">Paid back</dt>
                        <dd class="font-medium text-success">
                            {{ formatDateTime(refund.processed_at) }} by
                            {{ refund.processed_by ?? '—' }}
                        </dd>
                    </div>
                    <div v-if="refund.method">
                        <dt class="text-muted-foreground">Paid by</dt>
                        <dd class="font-medium">
                            {{ refund.method
                            }}<template v-if="refund.transaction_reference">
                                · ref
                                {{ refund.transaction_reference }}</template
                            >
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Counselor</dt>
                        <dd class="font-medium">
                            {{ refund.counselor ?? '—' }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-muted-foreground">Reason</p>
                <p
                    class="mt-1 rounded-lg bg-muted p-3 text-[13px] whitespace-pre-line"
                >
                    {{ refund.reason }}
                </p>
                <p
                    v-if="refund.status === 'rejected' && refund.decision_note"
                    class="mt-3 rounded-lg bg-danger-soft p-3 text-[13px] text-danger"
                >
                    <b>Rejected:</b> {{ refund.decision_note }}
                </p>
                <p
                    v-else-if="refund.decision_note"
                    class="mt-3 rounded-lg bg-info-soft p-3 text-[13px] text-info"
                >
                    <b>Approver's note:</b> {{ refund.decision_note }}
                </p>
                <p
                    v-if="refund.notes"
                    class="mt-3 rounded-lg bg-muted p-3 text-[13px] whitespace-pre-line"
                >
                    {{ refund.notes }}
                </p>
            </PanelCard>

            <PanelCard
                v-if="can.process"
                title="Pay this refund"
                description="Return the money to the student first, then record how it was sent. This cannot be undone."
            >
                <form
                    class="flex flex-col gap-3.5"
                    novalidate
                    @submit.prevent="process"
                >
                    <div class="grid gap-3.5 sm:grid-cols-2">
                        <FormField
                            v-slot="{ id, invalid }"
                            label="Paid back by"
                            required
                            :error="payout.errors.payment_method_id"
                        >
                            <SelectInput
                                :id="id"
                                v-model="payout.payment_method_id"
                                :options="methods"
                                :invalid="invalid"
                            />
                        </FormField>
                        <FormField
                            v-slot="{ id, invalid }"
                            label="Transaction ID / reference"
                            :required="payoutMethod?.requires_reference"
                            :error="payout.errors.transaction_reference"
                        >
                            <Input
                                :id="id"
                                v-model="payout.transaction_reference"
                                :aria-invalid="invalid"
                            />
                        </FormField>
                    </div>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Note"
                        :error="payout.errors.notes"
                    >
                        <TextArea
                            :id="id"
                            v-model="payout.notes"
                            :rows="2"
                            :invalid="invalid"
                        />
                    </FormField>
                    <p
                        v-if="payoutError"
                        class="text-xs text-danger"
                        role="alert"
                    >
                        {{ payoutError }}
                    </p>
                    <div class="flex justify-end">
                        <Button type="submit" :disabled="payout.processing"
                            ><Spinner v-if="payout.processing" /> Record
                            {{ refund.amount_label }} as refunded</Button
                        >
                    </div>
                </form>
            </PanelCard>
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard title="Original payment">
                <p class="text-[13px] font-semibold">
                    <Link
                        v-if="refund.payment.url"
                        :href="refund.payment.url"
                        class="hover:text-primary"
                        >{{ refund.receipt_number ?? 'Payment' }}</Link
                    >
                    <template v-else>{{
                        refund.receipt_number ?? 'Payment'
                    }}</template>
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ refund.type }} ·
                    <Link
                        :href="refund.invoice_url"
                        class="hover:text-primary"
                        >{{ refund.invoice_number }}</Link
                    >
                </p>
                <dl class="mt-3 grid gap-2 text-[13px]">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Paid</dt>
                        <dd class="font-medium">
                            {{ refund.payment.amount_label }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Already refunded</dt>
                        <dd class="font-medium">
                            {{ refund.payment.refunded_label }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Method</dt>
                        <dd class="text-right font-medium">
                            {{ refund.payment.method }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Paid on</dt>
                        <dd class="font-medium">
                            {{ formatDate(refund.payment.paid_on) }}
                        </dd>
                    </div>
                </dl>
            </PanelCard>
            <PanelCard
                title="Status history"
                description="Every change is recorded."
            >
                <ol class="relative ml-1.5 border-l">
                    <li
                        v-for="h in history"
                        :key="h.id"
                        class="relative pb-3 pl-5 last:pb-0"
                    >
                        <span
                            class="absolute top-1 -left-[5px] size-2.5 rounded-full border-2 border-card bg-primary"
                        />
                        <p class="text-[13px]">{{ h.to }}</p>
                        <p
                            v-if="h.note"
                            class="text-xs text-secondary-foreground"
                        >
                            {{ h.note }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            {{ h.by ?? 'System' }} · {{ relativeTime(h.at) }}
                        </p>
                    </li>
                </ol>
            </PanelCard>
        </div>
    </div>

    <ConfirmDialog
        :open="dialog === 'approve'"
        title="Approve this refund?"
        :description="`Authorises Accounts to return ${refund.amount_label} to ${refund.student}.`"
        confirm-label="Approve refund"
        :loading="busy"
        @update:open="(v) => !v && (dialog = null)"
        @confirm="decide(urls.approve, note ? { note } : {})"
    >
        <Input
            v-model="note"
            placeholder="Note for Accounts (optional)"
            aria-label="Note for Accounts"
        />
    </ConfirmDialog>
    <ConfirmDialog
        :open="dialog === 'reject'"
        title="Reject this refund?"
        description="The student and counselor see the reason."
        confirm-label="Reject refund"
        destructive
        :loading="busy"
        @update:open="(v) => !v && (dialog = null)"
        @confirm="decide(urls.reject, { reason: note })"
    >
        <Input
            v-model="note"
            placeholder="Reason (required)"
            aria-label="Reason for rejecting"
        />
    </ConfirmDialog>
</template>
