<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import PaymentForm from '@/components/payments/PaymentForm.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, formatDateTime, relativeTime } from '@/lib/format';
import type {
    HistoryEntry,
    PaymentMethodOption,
    PaymentRequestRow,
    PaymentRow,
} from '@/types';

const props = defineProps<{
    request: PaymentRequestRow & {
        notes: string | null;
        cancel_reason: string | null;
        counselor: string | null;
        student_url: string | null;
    };
    payments: PaymentRow[];
    history: HistoryEntry[];
    methods: PaymentMethodOption[];
    can: { pay: boolean; record: boolean; cancel: boolean };
    outstandingDecimal: string;
    evidence: { max_mb: number; extensions: string[] };
    urls: {
        index: string;
        pay: string;
        record: string | null;
        cancel: string;
        invoice: string;
    };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Invoices', href: props.urls.index }],
});

const mode = ref<'record' | 'submit'>(props.can.record ? 'record' : 'submit');
const cancelling = ref(false);
const cancelReason = ref('');

function cancel(): void {
    router.patch(
        props.urls.cancel,
        { reason: cancelReason.value },
        {
            preserveScroll: true,
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Could not cancel.'),
            onSuccess: () => (cancelling.value = false),
        },
    );
}
</script>

<template>
    <Head :title="`Invoice ${request.invoice_number}`" />

    <PageHeader
        :eyebrow="request.type"
        :title="request.invoice_number"
        :description="`${request.student} · ${request.student_number}${request.file_number ? ` · ${request.file_number}` : ''}`"
    >
        <template #actions>
            <StatusBadge
                :label="request.status_label"
                :tone="request.status_tone"
                class="self-center"
            />
            <Button variant="outline" as-child
                ><a :href="urls.invoice"
                    ><AppIcon name="download" :size="18" /> Invoice PDF</a
                ></Button
            >
            <Button v-if="request.student_url" variant="outline" as-child
                ><Link :href="request.student_url"
                    ><AppIcon name="school" :size="18" /> Student</Link
                ></Button
            >
            <Button
                v-if="can.cancel"
                variant="outline"
                @click="cancelling = true"
                ><AppIcon name="cancel" :size="18" /> Cancel invoice</Button
            >
        </template>
    </PageHeader>

    <div class="mb-6 grid grid-cols-3 gap-2.5 sm:gap-4">
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Amount</p>
            <p class="mt-1 font-display text-xl font-extrabold sm:text-2xl">
                {{ request.amount_label }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Paid</p>
            <p
                class="mt-1 font-display text-xl font-extrabold text-success sm:text-2xl"
            >
                {{ request.paid_label }}
            </p>
            <p
                v-if="request.refunded"
                class="text-[11px] text-muted-foreground"
            >
                {{ request.refunded_label }} refunded
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Outstanding</p>
            <p
                class="mt-1 font-display text-xl font-extrabold sm:text-2xl"
                :class="request.outstanding ? 'text-danger' : ''"
            >
                {{ request.outstanding_label }}
            </p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(320px,1fr)]">
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard
                title="Payments"
                description="Each payment is verified and confirmed by Accounts."
            >
                <EmptyState
                    v-if="!payments.length"
                    icon="payments"
                    title="No payments yet"
                />
                <ul v-else class="divide-y">
                    <li
                        v-for="p in payments"
                        :key="p.id"
                        class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0 text-[13px]">
                            <p class="flex flex-wrap items-center gap-2">
                                <b>{{ p.amount_label }}</b> · {{ p.method }}
                                <StatusBadge
                                    :label="p.status_label"
                                    :tone="p.status_tone"
                                />
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Paid {{ formatDate(p.paid_on)
                                }}<template v-if="p.transaction_reference">
                                    · ref
                                    {{ p.transaction_reference }}</template
                                >
                                <template v-if="p.submitted_by">
                                    · submitted by
                                    {{ p.submitted_by }}</template
                                >
                            </p>
                            <p
                                v-if="p.receipt_number"
                                class="text-xs text-success"
                            >
                                Receipt {{ p.receipt_number }} · confirmed by
                                {{ p.confirmed_by }}
                            </p>
                            <p
                                v-if="p.rejection_reason"
                                class="mt-1 rounded-md bg-danger-soft px-2.5 py-1.5 text-xs text-danger"
                            >
                                <b>Rejected:</b> {{ p.rejection_reason }}
                            </p>
                        </div>
                        <div class="flex gap-1.5">
                            <a
                                v-if="p.evidence_url"
                                :href="p.evidence_url"
                                target="_blank"
                                rel="noopener"
                                class="tiny-action"
                                ><AppIcon name="attach_file" :size="14" />
                                Proof</a
                            >
                            <a
                                v-if="p.receipt_url"
                                :href="p.receipt_url"
                                class="tiny-action"
                                ><AppIcon name="download" :size="14" />
                                Receipt</a
                            >
                            <Link v-if="p.url" :href="p.url" class="tiny-action"
                                >Details</Link
                            >
                        </div>
                    </li>
                </ul>
            </PanelCard>

            <PanelCard v-if="can.pay || can.record" title="Add a payment">
                <PanelTabs
                    v-if="can.pay && can.record"
                    v-model="mode"
                    label="Payment entry type"
                    :tabs="[
                        { key: 'record', label: 'Record money received' },
                        { key: 'submit', label: 'Submit for verification' },
                    ]"
                />
                <p class="mb-3 text-xs text-muted-foreground">
                    {{
                        mode === 'record'
                            ? 'For cash, card or transfers Accounts has already checked. Confirmed immediately with a receipt.'
                            : 'For a slip or transaction the student handed in. Accounts will verify it.'
                    }}
                </p>
                <PaymentForm
                    v-if="mode === 'record' && can.record && urls.record"
                    :key="'record'"
                    :submit-url="urls.record"
                    :methods="methods"
                    :outstanding="outstandingDecimal"
                    :currency="request.currency"
                    :evidence="evidence"
                    :allow-evidence="false"
                    submit-label="Record and confirm"
                />
                <PaymentForm
                    v-else
                    :key="'submit'"
                    :submit-url="urls.pay"
                    :methods="methods"
                    :outstanding="outstandingDecimal"
                    :currency="request.currency"
                    :evidence="evidence"
                />
            </PanelCard>
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard title="Details">
                <dl class="grid gap-3 text-[13px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Fee type</dt>
                        <dd class="font-medium">{{ request.type }}</dd>
                    </div>
                    <div
                        v-if="request.description"
                        class="flex justify-between gap-3"
                    >
                        <dt class="text-muted-foreground">Description</dt>
                        <dd class="text-right font-medium">
                            {{ request.description }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Due</dt>
                        <dd
                            class="font-medium"
                            :class="{ 'text-danger': request.overdue }"
                        >
                            {{
                                request.due_date
                                    ? formatDate(request.due_date)
                                    : 'No due date'
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Raised by</dt>
                        <dd class="font-medium">
                            {{ request.requested_by ?? 'System' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Raised</dt>
                        <dd class="font-medium">
                            {{ formatDateTime(request.created_at) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Counselor</dt>
                        <dd class="font-medium">
                            {{ request.counselor ?? '—' }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="request.notes"
                    class="mt-4 rounded-lg bg-muted p-3 text-[13px] whitespace-pre-line"
                >
                    {{ request.notes }}
                </p>
                <p
                    v-if="request.cancel_reason"
                    class="mt-4 rounded-lg bg-neutral-soft p-3 text-[13px]"
                >
                    Cancelled: {{ request.cancel_reason }}
                </p>
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
                        <p class="text-[13px]">
                            {{ h.from ? `${h.from} → ${h.to}` : h.to }}
                        </p>
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
        :open="cancelling"
        title="Cancel this invoice?"
        description="Only invoices with no payments can be cancelled. The record is kept for audit."
        confirm-label="Cancel invoice"
        destructive
        @update:open="(v) => (cancelling = v)"
        @confirm="cancel"
    >
        <Input
            v-model="cancelReason"
            placeholder="Reason (required)"
            aria-label="Reason for cancelling"
        />
    </ConfirmDialog>
</template>
