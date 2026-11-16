<script setup lang="ts">
import { Head, Link, usePage, setLayoutProps } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { formatDateTime } from '@/lib/format';
import type { Option, Paginated, TableColumn, Tone } from '@/types';

type GatewayRow = {
    id: number;
    gateway: string;
    tran_id: string;
    invoice_number: string;
    student: string;
    student_number: string;
    amount_label: string;
    status: string;
    status_label: string;
    status_tone: Tone;
    bank_tran_id: string | null;
    card_type: string | null;
    failure_reason: string | null;
    receipt_number: string | null;
    created_at: string | null;
    settled_at: string | null;
    url: string;
};

const props = defineProps<{
    transactions: Paginated<GatewayRow>;
    filters: { search: string; status: string };
    statuses: Option[];
    gateway: { label: string; sandbox: boolean } | null;
}>();

const page = usePage();
setLayoutProps({
    breadcrumbs: [{ title: 'Gateway transactions', href: page.url }],
});

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['transactions', 'filters'] },
);

const columns: TableColumn<GatewayRow>[] = [
    { key: 'student', label: 'Student' },
    { key: 'tran_id', label: 'Transaction', hideBelow: 'md' },
    { key: 'amount_label', label: 'Amount', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Started', hideBelow: 'lg' },
];
</script>

<template>
    <Head title="Gateway transactions" />

    <PageHeader
        eyebrow="Transactions"
        title="Gateway transactions"
        description="Every attempt to pay online, including those that failed or were abandoned. Only an attempt the gateway confirms to our server becomes a payment."
    />

    <div
        v-if="!gateway"
        class="mb-4 flex items-start gap-3 rounded-xl border border-info/30 bg-info-soft p-4 text-[13px] text-info"
    >
        <AppIcon name="info" :size="18" />
        Online payment is switched off, so students only see the manual payment
        methods. It turns on once the gateway store ID and password are set on
        the server.
    </div>
    <div
        v-else-if="gateway.sandbox"
        class="mb-4 flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-[13px] text-warning"
    >
        <AppIcon name="science" :size="18" />
        {{ gateway.label }} is in sandbox (test) mode. No real money moves.
    </div>

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Student, invoice or transaction ID…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.status"
                    :options="statuses"
                    placeholder="Any status"
                    label="Filter by status"
                    compact
                />
            </template>
            <template v-if="filters.search || filters.status" #actions>
                <Button variant="ghost" size="sm" @click="reset"
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="transactions.data"
            caption="Gateway transactions"
            empty-icon="swap_horiz"
            empty-title="No online payments yet"
        >
            <template #cell-student="{ row }">
                <Link
                    :href="row.url"
                    class="block font-semibold text-foreground hover:text-primary"
                    >{{ row.student }}</Link
                >
                <span class="block text-[11px] text-muted-foreground"
                    >{{ row.student_number }} · {{ row.invoice_number }}</span
                >
            </template>
            <template #cell-tran_id="{ row }">
                <span class="block font-mono text-xs">{{ row.tran_id }}</span>
                <span
                    v-if="row.card_type || row.bank_tran_id"
                    class="block text-[11px] text-muted-foreground"
                    >{{
                        [row.card_type, row.bank_tran_id]
                            .filter(Boolean)
                            .join(' · ')
                    }}</span
                >
            </template>
            <template #cell-amount_label="{ row }"
                ><b class="text-foreground">{{ row.amount_label }}</b></template
            >
            <template #cell-status="{ row }">
                <StatusBadge
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
                <span
                    v-if="row.receipt_number"
                    class="mt-0.5 block text-[11px] text-muted-foreground"
                    >{{ row.receipt_number }}</span
                >
                <span
                    v-else-if="row.failure_reason"
                    class="mt-0.5 block max-w-[260px] text-[11px] text-danger"
                    >{{ row.failure_reason }}</span
                >
            </template>
            <template #cell-created_at="{ row }">{{
                formatDateTime(row.created_at)
            }}</template>
            <template #actions="{ row }"
                ><Link :href="row.url" class="tiny-action">{{
                    row.receipt_number ? 'Payment' : 'Invoice'
                }}</Link></template
            >
        </DataTable>
        <TablePagination :meta="transactions" />
    </PanelCard>
</template>
