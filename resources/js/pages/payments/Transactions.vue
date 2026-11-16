<script setup lang="ts">
import { Head, Link, usePage, setLayoutProps } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import DateRangeFilter from '@/components/panel/DateRangeFilter.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { formatDateTime } from '@/lib/format';
import type { Paginated, TableColumn } from '@/types';

type Transaction = {
    key: string;
    direction: 'in' | 'out';
    number: string | null;
    student: string;
    student_number: string;
    invoice_number: string;
    method: string | null;
    transaction_reference: string | null;
    amount_label: string;
    at: string;
    url: string;
};

const props = defineProps<{
    transactions: Paginated<Transaction>;
    filters: { search: string; direction: string; from: string; to: string };
    totals: { in: string; out: string; net: string; net_negative: boolean };
    exportUrl: string | null;
}>();

const page = usePage();
setLayoutProps({
    breadcrumbs: [{ title: 'Transaction history', href: page.url }],
});

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['transactions', 'filters', 'totals', 'exportUrl'] },
);

const columns: TableColumn<Transaction>[] = [
    { key: 'student', label: 'Student' },
    { key: 'number', label: 'Document', hideBelow: 'md' },
    { key: 'method', label: 'Method', hideBelow: 'lg' },
    { key: 'amount_label', label: 'Amount', align: 'right' },
    { key: 'at', label: 'Date', hideBelow: 'sm' },
];
</script>

<template>
    <Head title="Transaction history" />

    <PageHeader
        eyebrow="Transactions"
        title="Transaction history"
        description="Money in (confirmed payments) and money out (refunds paid), newest first."
    >
        <template v-if="exportUrl" #actions>
            <Button variant="outline" as-child>
                <a :href="exportUrl"
                    ><AppIcon name="download" :size="18" /> Export to Excel</a
                >
            </Button>
        </template>
    </PageHeader>

    <div class="mb-6 grid grid-cols-3 gap-2.5 sm:gap-4">
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Received</p>
            <p
                class="mt-1 font-display text-lg font-extrabold text-success sm:text-2xl"
            >
                {{ totals.in }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Refunded</p>
            <p class="mt-1 font-display text-lg font-extrabold sm:text-2xl">
                {{ totals.out }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Net</p>
            <p
                class="mt-1 font-display text-lg font-extrabold sm:text-2xl"
                :class="{ 'text-danger': totals.net_negative }"
            >
                {{ totals.net_negative ? '−' : '' }}{{ totals.net }}
            </p>
        </div>
    </div>

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Student, invoice, receipt or reference…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.direction"
                    :options="[
                        { value: 'in', label: 'Payments received' },
                        { value: 'out', label: 'Refunds paid' },
                    ]"
                    placeholder="In and out"
                    label="Filter by direction"
                    compact
                />
                <DateRangeFilter
                    v-model:from="filters.from"
                    v-model:to="filters.to"
                />
            </template>
            <template
                v-if="
                    filters.search ||
                    filters.direction ||
                    filters.from ||
                    filters.to
                "
                #actions
            >
                <Button variant="ghost" size="sm" @click="reset"
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="transactions.data"
            row-key="key"
            caption="Transaction history"
            empty-icon="history"
            empty-title="No transactions in this period"
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
            <template #cell-number="{ row }">
                <span class="block">{{ row.number ?? '—' }}</span>
                <span class="block text-[11px] text-muted-foreground">{{
                    row.direction === 'in' ? 'Receipt' : 'Refund'
                }}</span>
            </template>
            <template #cell-method="{ row }">
                <span class="block">{{ row.method ?? '—' }}</span>
                <span
                    v-if="row.transaction_reference"
                    class="block text-[11px] text-muted-foreground"
                    >ref {{ row.transaction_reference }}</span
                >
            </template>
            <template #cell-amount_label="{ row }">
                <b
                    :class="
                        row.direction === 'in' ? 'text-success' : 'text-danger'
                    "
                    >{{ row.direction === 'in' ? '+' : '−'
                    }}{{ row.amount_label }}</b
                >
            </template>
            <template #cell-at="{ row }">{{ formatDateTime(row.at) }}</template>
        </DataTable>
        <TablePagination :meta="transactions" />
    </PanelCard>
</template>
