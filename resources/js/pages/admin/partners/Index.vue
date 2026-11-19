<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { useFilters } from '@/composables/useFilters';
import { index, show } from '@/routes/admin/partners';
import type { Paginated, TableColumn } from '@/types';

type Row = {
    id: number;
    partner_number: string;
    organisation: string;
    contact_name: string;
    country: string | null;
    type: string;
    status: string;
    verified: boolean;
    commission_rate: string | null;
    owed: string;
};

const props = defineProps<{
    partners: Paginated<Row>;
    filters: { search: string; status: string };
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Partners', href: index() }] },
});

const { filters } = useFilters(
    { ...props.filters },
    { only: ['partners', 'filters'] },
);
const columns: TableColumn<Row>[] = [
    { key: 'organisation', label: 'Partner' },
    {
        key: 'commission_rate',
        label: 'Commission',
        hideBelow: 'md',
        format: (r) => (r.commission_rate ? `${r.commission_rate}%` : '—'),
    },
    { key: 'owed', label: 'Owed', align: 'right', hideBelow: 'sm' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <Head title="Partners" />
    <PageHeader
        eyebrow="Partner management"
        title="Partners"
        description="Approved agents and institutions, their commission and payments."
    />
    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Name, number or email…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.status"
                    :options="[
                        { value: 'active', label: 'Active' },
                        { value: 'suspended', label: 'Suspended' },
                    ]"
                    placeholder="Any status"
                    label="Filter by status"
                    compact
                />
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="partners.data"
            caption="Partners"
            empty-title="No partners yet"
            empty-icon="handshake"
        >
            <template #cell-organisation="{ row }">
                <Link
                    :href="show(row.id)"
                    class="block font-semibold text-foreground hover:underline"
                    >{{ row.organisation }}</Link
                >
                <span class="block text-[11px] text-muted-foreground"
                    >{{ row.partner_number }} · {{ row.contact_name }} ·
                    {{ row.type
                    }}<template v-if="row.country">
                        · {{ row.country }}</template
                    ></span
                >
            </template>
            <template #cell-status="{ row }">
                <div class="flex flex-wrap gap-1">
                    <StatusBadge
                        :label="
                            row.status === 'active' ? 'Active' : 'Suspended'
                        "
                        :tone="row.status === 'active' ? 'success' : 'warning'"
                    />
                    <StatusBadge
                        v-if="row.verified"
                        label="Verified"
                        tone="info"
                    />
                </div>
            </template>
            <template #actions="{ row }">
                <Link :href="show(row.id)" class="tiny-action">
                    <AppIcon name="visibility" :size="13" />
                    <span>Open</span>
                </Link>
            </template>
        </DataTable>
        <TablePagination v-if="partners.last_page > 1" :meta="partners" />
    </PanelCard>
</template>
