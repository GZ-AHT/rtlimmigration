<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import RefundRequestDialog from '@/components/payments/RefundRequestDialog.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { history } from '@/routes/portal/payments';
import { index as refundsIndex } from '@/routes/portal/refunds';
import type { PaymentRow } from '@/types';

type HistoryRow = PaymentRow & {
    refundable_decimal: string;
    refundable_label: string;
    refund_url: string | null;
};

defineProps<{ payments: HistoryRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Payment history', href: history() }] },
});

const refunding = ref<HistoryRow | null>(null);
</script>

<template>
    <Head title="Payment history" />

    <PageHeader
        eyebrow="Student portal"
        title="Payment history"
        description="Every payment you have submitted and its result. Confirmed payments have a receipt to download."
    >
        <template #actions>
            <Button variant="outline" as-child>
                <Link :href="refundsIndex()">
                    <AppIcon name="currency_exchange" :size="18" /> My refunds
                </Link>
            </Button>
        </template>
    </PageHeader>

    <PanelCard title="My payments">
        <EmptyState
            v-if="!payments.length"
            icon="payments"
            title="No payments yet"
            description="Payments you make appear here with their status."
        />
        <ul v-else class="divide-y">
            <li
                v-for="p in payments"
                :key="p.id"
                class="py-4 first:pt-0 last:pb-0"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p
                            class="flex flex-wrap items-center gap-2 text-[13px] font-semibold"
                        >
                            {{ p.type }}
                            <StatusBadge
                                :label="p.status_label"
                                :tone="p.status_tone"
                            />
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ p.invoice_number }} · {{ p.method }} · paid
                            {{ formatDate(p.paid_on) }}
                        </p>
                        <p
                            v-if="p.transaction_reference || p.receipt_number"
                            class="text-xs text-muted-foreground"
                        >
                            <template v-if="p.receipt_number"
                                >Receipt {{ p.receipt_number }}</template
                            ><template
                                v-if="
                                    p.receipt_number && p.transaction_reference
                                "
                            >
                                · </template
                            ><template v-if="p.transaction_reference"
                                >ref {{ p.transaction_reference }}</template
                            >
                        </p>
                        <p v-if="p.refunded" class="text-xs text-warning">
                            {{ p.refunded_label }} refunded
                        </p>
                    </div>
                    <p class="ml-auto font-display text-lg font-extrabold">
                        {{ p.amount_label }}
                    </p>
                </div>
                <p
                    v-if="p.rejection_reason"
                    class="mt-2 rounded-lg bg-danger-soft px-3 py-2 text-xs text-danger"
                >
                    <b>Not accepted:</b> {{ p.rejection_reason }}
                </p>
                <div
                    v-if="p.receipt_url || p.refund_url || p.evidence_url"
                    class="mt-2.5 flex flex-wrap gap-1.5"
                >
                    <a
                        v-if="p.receipt_url"
                        :href="p.receipt_url"
                        class="tiny-action"
                        ><AppIcon name="download" :size="14" /> Receipt</a
                    >
                    <a
                        v-if="p.evidence_url"
                        :href="p.evidence_url"
                        target="_blank"
                        rel="noopener"
                        class="tiny-action"
                        ><AppIcon name="attach_file" :size="14" /> Proof</a
                    >
                    <button
                        v-if="p.refund_url"
                        type="button"
                        class="tiny-action"
                        @click="refunding = p"
                    >
                        <AppIcon name="currency_exchange" :size="14" /> Request
                        refund
                    </button>
                </div>
            </li>
        </ul>
    </PanelCard>

    <RefundRequestDialog
        v-if="refunding"
        :key="refunding.id"
        :open="true"
        :submit-url="refunding.refund_url"
        :refundable="refunding.refundable_decimal"
        :refundable-label="refunding.refundable_label"
        currency="BDT"
        :subject="refunding.receipt_number ?? refunding.invoice_number ?? ''"
        @update:open="(v) => !v && (refunding = null)"
    />
</template>
