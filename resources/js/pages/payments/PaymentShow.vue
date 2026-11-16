<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import RefundRequestDialog from '@/components/payments/RefundRequestDialog.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, formatDateTime, relativeTime } from '@/lib/format';
import type {
    HistoryEntry,
    PaymentRequestRow,
    PaymentRow,
    RefundRow,
} from '@/types';

const props = defineProps<{
    payment: PaymentRow & {
        request: PaymentRequestRow & { url: string };
        evidence_url: string | null;
        evidence_is_image: boolean;
    };
    history: HistoryEntry[];
    refunds: RefundRow[];
    refundable: { minor: number; label: string; decimal: string };
    can: {
        verify: boolean;
        confirm: boolean;
        reject: boolean;
        refund: boolean;
    };
    isRequester: boolean;
    urls: {
        index: string;
        verify: string | null;
        confirm: string | null;
        reject: string | null;
        receipt: string | null;
        refund: string | null;
    };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Payments', href: props.urls.index }],
});

const dialog = ref<null | 'confirm' | 'reject'>(null);
const reason = ref('');
const busy = ref(false);
const refunding = ref(false);

function act(url: string | null, data: Record<string, string> = {}): void {
    if (!url) {
        return;
    }

    router.patch(url, data, {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onError: (e) => toast.error(Object.values(e)[0] ?? 'Could not save.'),
        onFinish: () => {
            busy.value = false;
            dialog.value = null;
        },
    });
}
</script>

<template>
    <Head :title="`Payment · ${payment.request.invoice_number}`" />

    <PageHeader
        :eyebrow="`${payment.request.invoice_number} · ${payment.request.type}`"
        :title="payment.amount_label"
        :description="`${payment.student} · ${payment.student_number}`"
    >
        <template #actions>
            <StatusBadge
                :label="payment.status_label"
                :tone="payment.status_tone"
                class="self-center"
            />
            <Button v-if="urls.receipt" variant="outline" as-child
                ><a :href="urls.receipt"
                    ><AppIcon name="download" :size="18" /> Receipt</a
                ></Button
            >
            <Button
                v-if="can.refund"
                variant="outline"
                @click="refunding = true"
                ><AppIcon name="currency_exchange" :size="18" /> Request
                refund</Button
            >
            <Button
                v-if="can.verify"
                variant="outline"
                :disabled="busy"
                @click="act(urls.verify)"
                ><AppIcon name="fact_check" :size="18" /> Mark verified</Button
            >
            <Button
                v-if="can.reject"
                variant="outline"
                :disabled="busy"
                @click="dialog = 'reject'"
                ><AppIcon name="block" :size="18" /> Reject</Button
            >
            <Button
                v-if="can.confirm"
                :disabled="busy"
                @click="dialog = 'confirm'"
                ><AppIcon name="task_alt" :size="18" /> Confirm payment</Button
            >
        </template>
    </PageHeader>

    <div
        v-if="isRequester"
        class="mb-4 flex items-center gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-[13px] text-warning"
    >
        <AppIcon name="gpp_maybe" :size="18" />
        You raised this invoice, so another member of Accounts must confirm or
        reject this payment.
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(320px,1fr)]">
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard title="Payment">
                <dl class="grid gap-3 text-[13px] sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Amount</dt>
                        <dd class="font-display text-lg font-bold">
                            {{ payment.amount_label }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Method</dt>
                        <dd class="font-medium">{{ payment.method }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            Transaction reference
                        </dt>
                        <dd class="font-mono font-medium">
                            {{ payment.transaction_reference ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Paid on</dt>
                        <dd class="font-medium">
                            {{ formatDate(payment.paid_on) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Submitted</dt>
                        <dd class="font-medium">
                            {{ formatDateTime(payment.created_at) }} by
                            {{ payment.submitted_by ?? '—' }}
                        </dd>
                    </div>
                    <div v-if="payment.receipt_number">
                        <dt class="text-muted-foreground">Receipt</dt>
                        <dd class="font-medium text-success">
                            {{ payment.receipt_number }}
                        </dd>
                    </div>
                    <div v-if="payment.refunded">
                        <dt class="text-muted-foreground">Refunded</dt>
                        <dd class="font-medium">
                            {{ payment.refunded_label }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="payment.notes"
                    class="mt-4 rounded-lg bg-muted p-3 text-[13px] whitespace-pre-line"
                >
                    {{ payment.notes }}
                </p>
                <p
                    v-if="payment.rejection_reason"
                    class="mt-4 rounded-lg bg-danger-soft p-3 text-[13px] text-danger"
                >
                    <b>Rejected:</b> {{ payment.rejection_reason }}
                </p>
            </PanelCard>

            <PanelCard
                v-if="refunds.length"
                title="Refunds"
                description="Refunds requested against this payment."
            >
                <ul class="divide-y">
                    <li
                        v-for="r in refunds"
                        :key="r.id"
                        class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0 text-[13px]">
                            <p class="flex flex-wrap items-center gap-2">
                                <b>{{ r.amount_label }}</b>
                                <StatusBadge
                                    :label="r.status_label"
                                    :tone="r.status_tone"
                                />
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ r.refund_number }} · requested
                                {{ relativeTime(r.created_at)
                                }}<template v-if="r.requested_by">
                                    by {{ r.requested_by }}</template
                                >
                            </p>
                            <p class="mt-1 text-xs text-secondary-foreground">
                                {{ r.reason }}
                            </p>
                        </div>
                        <Link v-if="r.url" :href="r.url" class="tiny-action"
                            >Details</Link
                        >
                    </li>
                </ul>
            </PanelCard>

            <PanelCard
                v-if="payment.method_kind === 'gateway'"
                title="Proof of payment"
            >
                <p class="text-[13px] text-muted-foreground">
                    Paid online. The payment gateway confirmed this transaction
                    directly to our server, so no proof was uploaded.
                </p>
            </PanelCard>
            <PanelCard v-else title="Proof of payment">
                <template v-if="payment.evidence_url">
                    <img
                        v-if="payment.evidence_is_image"
                        :src="payment.evidence_url"
                        :alt="`Proof of payment: ${payment.evidence_name}`"
                        class="max-h-[520px] w-full rounded-lg border object-contain"
                    />
                    <a
                        :href="payment.evidence_url"
                        target="_blank"
                        rel="noopener"
                        class="mt-3 inline-flex items-center gap-2 text-[13px] font-semibold text-primary hover:underline"
                    >
                        <AppIcon name="open_in_new" :size="16" /> Open
                        {{ payment.evidence_name }}
                    </a>
                </template>
                <p v-else class="text-[13px] text-muted-foreground">
                    No proof was uploaded. Check the transaction reference
                    against the statement.
                </p>
            </PanelCard>
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard title="Invoice">
                <p class="text-[13px] font-semibold">
                    <Link
                        :href="payment.request.url"
                        class="hover:text-primary"
                        >{{ payment.request.invoice_number }}</Link
                    >
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ payment.request.type }}
                </p>
                <dl class="mt-3 grid gap-2 text-[13px]">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Invoice amount</dt>
                        <dd class="font-medium">
                            {{ payment.request.amount_label }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Already paid</dt>
                        <dd class="font-medium">
                            {{ payment.request.paid_label }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Outstanding</dt>
                        <dd class="font-bold">
                            {{ payment.request.outstanding_label }}
                        </dd>
                    </div>
                </dl>
            </PanelCard>
            <PanelCard title="Status history">
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

    <RefundRequestDialog
        v-model:open="refunding"
        :submit-url="urls.refund"
        :refundable="refundable.decimal"
        :refundable-label="refundable.label"
        :currency="payment.request.currency"
        :subject="payment.receipt_number ?? payment.request.invoice_number"
    />

    <ConfirmDialog
        :open="dialog === 'confirm'"
        title="Confirm this payment?"
        :description="`Records ${payment.amount_label} against ${payment.request.invoice_number}, issues a receipt and tells the student and counselor. This cannot be undone.`"
        confirm-label="Confirm payment"
        :loading="busy"
        @update:open="(v) => !v && (dialog = null)"
        @confirm="act(urls.confirm)"
    />
    <ConfirmDialog
        :open="dialog === 'reject'"
        title="Reject this payment?"
        description="The student and counselor see the reason and can submit again."
        confirm-label="Reject"
        destructive
        :loading="busy"
        @update:open="(v) => !v && (dialog = null)"
        @confirm="act(urls.reject, { reason })"
    >
        <Input
            v-model="reason"
            placeholder="e.g. Reference not found on the bank statement"
            aria-label="Reason for rejection"
        />
    </ConfirmDialog>
</template>
