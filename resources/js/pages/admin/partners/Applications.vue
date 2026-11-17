<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { useFilters } from '@/composables/useFilters';
import { relativeTime } from '@/lib/format';
import { index, show } from '@/routes/admin/partner-applications';
import type { Option, Paginated, TableColumn, Tone } from '@/types';

type Row = {
    id: number;
    organisation: string;
    contact_name: string;
    email: string;
    country: string | null;
    type: string;
    status: string;
    status_label: string;
    status_tone: Tone;
    created_at: string | null;
};

const props = defineProps<{
    applications: Paginated<Row>;
    filters: { status: string; search: string };
    counts: Record<string, number>;
    statuses: Option[];
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Partner applications', href: index() }] },
});

const { filters } = useFilters(
    { ...props.filters },
    { only: ['applications', 'filters', 'counts'] },
);
const tab = computed({
    get: () => filters.status || 'all',
    set: (v: string) => (filters.status = v === 'all' ? '' : v),
});

const columns: TableColumn<Row>[] = [
    { key: 'organisation', label: 'Applicant' },
    { key: 'type', label: 'Type', hideBelow: 'md' },
    { key: 'status', label: 'Status' },
    {
        key: 'created_at',
        label: 'Received',
        hideBelow: 'sm',
        format: (r) => relativeTime(r.created_at),
    },
];
</script>

<template>
    <Head title="Partner applications" />
    <PageHeader
        eyebrow="Partner management"
        title="Partner applications"
        description="Agents and institutions who applied on the website."
    />
    <PanelTabs
        v-model="tab"
        label="Application status"
        :tabs="[
            { key: 'all', label: 'All' },
            ...statuses.map((s) => ({
                key: s.value,
                label: s.label,
                count: counts[s.value] ?? 0,
            })),
        ]"
    />
    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Organisation, name or email…"
        />
        <DataTable
            :columns="columns"
            :rows="applications.data"
            caption="Partner applications"
            empty-title="No applications"
            empty-icon="badge"
        >
            <template #cell-organisation="{ row }">
                <Link
                    :href="show(row.id)"
                    class="block font-semibold text-foreground hover:underline"
                    >{{ row.organisation }}</Link
                >
                <span class="block text-[11px] text-muted-foreground"
                    >{{ row.contact_name }} · {{ row.email
                    }}<template v-if="row.country">
                        · {{ row.country }}</template
                    ></span
                >
            </template>
            <template #cell-status="{ row }"
                ><StatusBadge :label="row.status_label" :tone="row.status_tone"
            /></template>
            <template #actions="{ row }"
                ><Link :href="show(row.id)" class="tiny-action"
                    >Open</Link
                ></template
            >
        </DataTable>
        <TablePagination
            v-if="applications.last_page > 1"
            :meta="applications"
        />
    </PanelCard>
</template>
