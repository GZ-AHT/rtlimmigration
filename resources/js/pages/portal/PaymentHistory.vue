<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { formatDate } from '@/lib/format';
import { history } from '@/routes/portal/payments';
import type { PaymentRow, TableColumn } from '@/types';

defineProps<{ payments: PaymentRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Payment history', href: history() }] },
});

const columns: TableColumn<PaymentRow>[] = [
    { key: 'type', label: 'Payment' },
    { key: 'amount_label', label: 'Amount', align: 'right' },
    { key: 'method', label: 'Method', hideBelow: 'md' },
    { key: 'status', label: 'Status' },
    {
        key: 'paid_on',
        label: 'Paid on',
        hideBelow: 'sm',
        format: (r) => formatDate(r.paid_on),
    },
];
</script>

<template>
    <Head title="Payment history" />

    <PageHeader
        eyebrow="Student portal"
        title="Payment history"
        description="Every payment you have submitted and its result."
    />

    <PanelCard flush>
        <DataTable
            :columns="columns"
            :rows="payments"
            caption="Payment history"
            empty-icon="payments"
            empty-title="No payments yet"
        >
            <template #cell-type="{ row }">
                <span class="block font-semibold text-foreground">{{
                    row.type
                }}</span>
                <span class="block text-[11px] text-muted-foreground">
                    {{ row.invoice_number }}
                    <template v-if="row.receipt_number">
                        · receipt {{ row.receipt_number }}</template
                    >
                </span>
                <span
                    v-if="row.rejection_reason"
                    class="block text-[11px] text-danger"
                    >{{ row.rejection_reason }}</span
                >
            </template>
            <template #cell-amount_label="{ row }">
                <b class="text-foreground">{{ row.amount_label }}</b>
            </template>
            <template #cell-method="{ row }">
                <span class="block">{{ row.method }}</span>
                <span
                    v-if="row.transaction_reference"
                    class="block text-[11px] text-muted-foreground"
                    >ref {{ row.transaction_reference }}</span
                >
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
            </template>
            <template #actions="{ row }">
                <a
                    v-if="row.evidence_url"
                    :href="row.evidence_url"
                    target="_blank"
                    rel="noopener"
                    class="tiny-action"
                    ><AppIcon name="attach_file" :size="14" /> Proof</a
                >
            </template>
        </DataTable>
    </PanelCard>
</template>
