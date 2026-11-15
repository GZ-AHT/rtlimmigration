<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { formatDate, relativeTime } from '@/lib/format';
import type { Option, Paginated, PaymentRow, TableColumn } from '@/types';

const props = defineProps<{
    title: string;
    preset: string | null;
    payments: Paginated<PaymentRow>;
    filters: { search: string; status: string; method: string };
    counts: Record<string, number>;
    statuses: Option[];
    methods: Option[];
}>();

const page = usePage();
setLayoutProps({ breadcrumbs: [{ title: props.title, href: page.url }] });

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['payments', 'filters', 'counts'] },
);

const descriptions: Record<string, string> = {
    pending:
        'Submitted payments to check against the bank or mobile-banking statement. Oldest first.',
    verified: 'Verified payments waiting for final confirmation.',
    rejected: 'Payments that were not accepted, with the reason given.',
};

const columns: TableColumn<PaymentRow>[] = [
    { key: 'student', label: 'Student' },
    { key: 'amount_label', label: 'Amount', align: 'right' },
    { key: 'method', label: 'Method', hideBelow: 'md' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Submitted', hideBelow: 'lg' },
];
</script>

<template>
    <Head :title="title" />

    <PageHeader
        eyebrow="Payments"
        :title="title"
        :description="
            preset
                ? descriptions[preset]
                : 'Every submitted payment and its outcome.'
        "
    />

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Student, invoice, reference or receipt…"
        >
            <template #filters>
                <SelectInput
                    v-if="!preset"
                    v-model="filters.status"
                    :options="
                        statuses.map((s) => ({
                            ...s,
                            label: `${s.label} (${counts[s.value] ?? 0})`,
                        }))
                    "
                    placeholder="Any status"
                    label="Filter by status"
                    compact
                />
                <SelectInput
                    v-model="filters.method"
                    :options="methods"
                    placeholder="All methods"
                    label="Filter by method"
                    compact
                />
            </template>
            <template
                v-if="
                    filters.search ||
                    filters.method ||
                    (!preset && filters.status)
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
            :rows="payments.data"
            :caption="title"
            empty-icon="payments"
            :empty-title="
                preset === 'pending'
                    ? 'Nothing waiting for verification'
                    : 'No payments found'
            "
        >
            <template #cell-student="{ row }">
                <Link
                    :href="row.url!"
                    class="block font-semibold text-foreground hover:text-primary"
                    >{{ row.student }}</Link
                >
                <span class="block text-[11px] text-muted-foreground"
                    >{{ row.invoice_number }} · {{ row.type }}</span
                >
            </template>
            <template #cell-amount_label="{ row }"
                ><b class="text-foreground">{{ row.amount_label }}</b></template
            >
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
                <span
                    v-if="row.receipt_number"
                    class="mt-0.5 block text-[11px] text-muted-foreground"
                    >{{ row.receipt_number }}</span
                >
            </template>
            <template #cell-created_at="{ row }">
                {{ relativeTime(row.created_at) }}
                <span class="block text-[11px] text-muted-foreground"
                    >paid {{ formatDate(row.paid_on) }}</span
                >
            </template>
            <template #actions="{ row }"
                ><Link :href="row.url!" class="tiny-action">{{
                    row.status === 'pending' || row.status === 'verified'
                        ? 'Review'
                        : 'Open'
                }}</Link></template
            >
        </DataTable>
        <TablePagination :meta="payments" />
    </PanelCard>
</template>
