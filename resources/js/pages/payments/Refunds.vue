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
import { relativeTime } from '@/lib/format';
import type { Option, Paginated, RefundRow, TableColumn } from '@/types';

const props = defineProps<{
    title: string;
    preset: string | null;
    refunds: Paginated<RefundRow>;
    filters: { search: string; status: string };
    statuses: Option[];
    totals: { key: string; label: string; count: number; total: string }[];
}>();

const page = usePage();
setLayoutProps({ breadcrumbs: [{ title: props.title, href: page.url }] });

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['refunds', 'filters', 'totals'] },
);

const columns: TableColumn<RefundRow>[] = [
    { key: 'student', label: 'Student' },
    { key: 'amount_label', label: 'Amount', align: 'right' },
    { key: 'reason', label: 'Reason', hideBelow: 'lg' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Requested', hideBelow: 'md' },
];
</script>

<template>
    <Head :title="title" />

    <PageHeader
        eyebrow="Refunds"
        :title="title"
        :description="
            preset === 'approved'
                ? 'Approved refunds waiting to be paid back. Longest-waiting first.'
                : 'Every refund is requested against one payment, approved, then paid out by Accounts.'
        "
    />

    <div
        v-if="!preset"
        class="mb-6 grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4"
    >
        <div
            v-for="t in totals"
            :key="t.key"
            class="rounded-xl border bg-card p-4"
        >
            <p class="text-xs text-muted-foreground">{{ t.label }}</p>
            <p class="mt-1 font-display text-xl font-extrabold">
                {{ t.total }}
            </p>
            <p class="text-[11px] text-muted-foreground">
                {{ t.count }} refund{{ t.count === 1 ? '' : 's' }}
            </p>
        </div>
    </div>

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Student, refund or invoice number…"
        >
            <template #filters>
                <SelectInput
                    v-if="!preset"
                    v-model="filters.status"
                    :options="statuses"
                    placeholder="Any status"
                    label="Filter by status"
                    compact
                />
            </template>
            <template
                v-if="filters.search || (!preset && filters.status)"
                #actions
            >
                <Button variant="ghost" size="sm" @click="reset"
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="refunds.data"
            :caption="title"
            empty-icon="currency_exchange"
            :empty-title="
                preset === 'approved'
                    ? 'No refunds waiting to be paid'
                    : 'No refunds found'
            "
            empty-description="A refund is requested from the page of the payment it relates to."
        >
            <template #cell-student="{ row }">
                <Link
                    :href="row.url!"
                    class="block font-semibold text-foreground hover:text-primary"
                    >{{ row.student }}</Link
                >
                <span class="block text-[11px] text-muted-foreground"
                    >{{ row.refund_number }} · {{ row.invoice_number }}</span
                >
            </template>
            <template #cell-amount_label="{ row }"
                ><b class="text-foreground">{{ row.amount_label }}</b></template
            >
            <template #cell-reason="{ row }">
                <span class="line-clamp-2 max-w-[320px]">{{ row.reason }}</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
            </template>
            <template #cell-created_at="{ row }">
                {{ relativeTime(row.created_at) }}
                <span
                    v-if="row.requested_by"
                    class="block text-[11px] text-muted-foreground"
                    >by {{ row.requested_by }}</span
                >
            </template>
            <template #actions="{ row }"
                ><Link :href="row.url!" class="tiny-action">{{
                    row.status === 'requested' || row.status === 'approved'
                        ? 'Review'
                        : 'Open'
                }}</Link></template
            >
        </DataTable>
        <TablePagination :meta="refunds" />
    </PanelCard>
</template>
