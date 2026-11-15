<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatCard from '@/components/panel/StatCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { formatDate } from '@/lib/format';
import type {
    Option,
    Paginated,
    PaymentRequestRow,
    TableColumn,
} from '@/types';

const props = defineProps<{
    title: string;
    preset: string | null;
    requests: Paginated<PaymentRequestRow>;
    filters: { search: string; status: string; type: string };
    statuses: Option[];
    types: Option[];
    totals: { outstanding: string; open: number; overdue: number };
    urls: { create: string | null };
}>();

const page = usePage();
setLayoutProps({ breadcrumbs: [{ title: props.title, href: page.url }] });

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['requests', 'filters', 'totals'] },
);

const columns: TableColumn<PaymentRequestRow>[] = [
    { key: 'invoice_number', label: 'Invoice' },
    { key: 'student', label: 'Student' },
    { key: 'amount_label', label: 'Amount', align: 'right' },
    {
        key: 'outstanding_label',
        label: 'Outstanding',
        align: 'right',
        hideBelow: 'md',
    },
    { key: 'status', label: 'Status' },
    { key: 'due_date', label: 'Due', hideBelow: 'lg' },
];
</script>

<template>
    <Head :title="title" />

    <PageHeader
        eyebrow="Payments"
        :title="title"
        description="Payment requests raised against students. Each has a unique invoice number."
    >
        <template v-if="urls.create" #actions>
            <Button as-child
                ><Link :href="urls.create"
                    ><AppIcon name="add" :size="18" /> New payment request</Link
                ></Button
            >
        </template>
    </PageHeader>

    <div class="mb-6 grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-3">
        <StatCard
            label="Outstanding"
            :value="totals.outstanding"
            icon="account_balance_wallet"
        />
        <StatCard
            label="Open invoices"
            :value="totals.open"
            icon="receipt_long"
        />
        <StatCard
            label="Overdue"
            :value="totals.overdue"
            icon="schedule"
            :hint="totals.overdue ? 'Past their due date' : undefined"
            hint-tone="danger"
        />
    </div>

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Invoice no., student name or number…"
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
                <SelectInput
                    v-model="filters.type"
                    :options="types"
                    placeholder="All fee types"
                    label="Filter by type"
                    compact
                />
            </template>
            <template
                v-if="
                    filters.search ||
                    filters.type ||
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
            :rows="requests.data"
            caption="Invoices"
            empty-icon="receipt_long"
            empty-title="No invoices found"
        >
            <template #cell-invoice_number="{ row }">
                <Link
                    :href="row.url!"
                    class="font-semibold text-foreground hover:text-primary"
                    >{{ row.invoice_number }}</Link
                >
                <span class="block text-[11px] text-muted-foreground">{{
                    row.type
                }}</span>
            </template>
            <template #cell-student="{ row }">
                <span class="block font-medium text-foreground">{{
                    row.student
                }}</span>
                <span class="block text-[11px] text-muted-foreground">{{
                    row.student_number
                }}</span>
            </template>
            <template #cell-amount_label="{ row }"
                ><b class="text-foreground">{{ row.amount_label }}</b></template
            >
            <template #cell-status="{ row }"
                ><StatusBadge :label="row.status_label" :tone="row.status_tone"
            /></template>
            <template #cell-due_date="{ row }">
                <span :class="{ 'font-semibold text-danger': row.overdue }">{{
                    row.due_date ? formatDate(row.due_date) : '—'
                }}</span>
            </template>
            <template #actions="{ row }"
                ><Link :href="row.url!" class="tiny-action"
                    >Open</Link
                ></template
            >
        </DataTable>
        <TablePagination :meta="requests" />
    </PanelCard>
</template>
