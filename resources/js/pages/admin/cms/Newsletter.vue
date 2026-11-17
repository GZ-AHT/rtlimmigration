<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { relativeTime } from '@/lib/format';
import { destroy, exportMethod, index } from '@/routes/admin/cms/newsletter';
import type { Paginated, TableColumn } from '@/types';

type Row = {
    id: number;
    email: string;
    status: string;
    source: string | null;
    created_at: string | null;
    unsubscribed_at: string | null;
};

const props = defineProps<{
    subscribers: Paginated<Row>;
    filters: { search: string; status: string };
    counts: Record<string, number>;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Newsletter', href: index() }] },
});

const { filters } = useFilters(
    { ...props.filters },
    { only: ['subscribers', 'filters', 'counts'] },
);
const tab = computed({
    get: () => filters.status || 'all',
    set: (v: string) => (filters.status = v === 'all' ? '' : v),
});
const exportUrl = computed(() =>
    exportMethod.url({
        query: Object.fromEntries(Object.entries(filters).filter(([, v]) => v)),
    }),
);

const columns: TableColumn<Row>[] = [
    { key: 'email', label: 'Email' },
    { key: 'status', label: 'Status' },
    { key: 'source', label: 'Signed up from', hideBelow: 'md' },
    {
        key: 'created_at',
        label: 'Joined',
        hideBelow: 'sm',
        format: (r) => relativeTime(r.created_at),
    },
];
</script>

<template>
    <Head title="Newsletter" />

    <PageHeader
        eyebrow="Website CMS"
        title="Newsletter subscribers"
        description="People who signed up on the website. Export them for your email service."
    >
        <template #actions>
            <Button variant="outline" as-child
                ><a :href="exportUrl"
                    ><AppIcon name="download" :size="18" /> Export CSV</a
                ></Button
            >
        </template>
    </PageHeader>

    <PanelTabs
        v-model="tab"
        label="Subscription status"
        :tabs="[
            { key: 'all', label: 'All' },
            {
                key: 'subscribed',
                label: 'Subscribed',
                count: counts.subscribed ?? 0,
            },
            {
                key: 'unsubscribed',
                label: 'Unsubscribed',
                count: counts.unsubscribed ?? 0,
            },
        ]"
    />

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Search emails…"
        />
        <DataTable
            :columns="columns"
            :rows="subscribers.data"
            caption="Newsletter subscribers"
            empty-title="No subscribers yet"
            empty-icon="mark_email_read"
        >
            <template #cell-status="{ row }">
                <StatusBadge
                    :label="
                        row.status === 'subscribed'
                            ? 'Subscribed'
                            : 'Unsubscribed'
                    "
                    :tone="row.status === 'subscribed' ? 'success' : 'neutral'"
                />
            </template>
            <template #actions="{ row }">
                <button
                    type="button"
                    class="tiny-action"
                    @click="
                        router.delete(destroy(row.id).url, {
                            preserveScroll: true,
                        })
                    "
                >
                    Remove
                </button>
            </template>
        </DataTable>
        <TablePagination v-if="subscribers.last_page > 1" :meta="subscribers" />
    </PanelCard>
</template>
