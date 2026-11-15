<script setup lang="ts">
import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { formatDateTime } from '@/lib/format';
import type { Paginated, TableColumn, Tone } from '@/types';

type Row = {
    id: number;
    due_at: string;
    overdue: boolean;
    note: string | null;
    owner: string | null;
    lead: { name: string; number: string; status: string; tone: Tone };
    lead_url: string;
    complete_url: string;
};

const props = defineProps<{
    followUps: Paginated<Row>;
    filter: string;
    canSeeAll: boolean;
}>();
const page = usePage();

setLayoutProps({ breadcrumbs: [{ title: 'Follow-ups', href: page.url }] });

const tab = ref(props.filter);
watch(tab, (value) =>
    router.get(
        new URL(page.url, 'http://x').pathname,
        value === 'all' ? {} : { when: value },
        { preserveState: true, replace: true },
    ),
);

const columns: TableColumn<Row>[] = [
    { key: 'due_at', label: 'Due' },
    { key: 'lead', label: 'Lead' },
    { key: 'note', label: 'Note', hideBelow: 'md' },
    ...(props.canSeeAll
        ? [{ key: 'owner', label: 'Counselor', hideBelow: 'lg' as const }]
        : []),
];

function complete(row: Row): void {
    router.patch(
        row.complete_url,
        {},
        {
            preserveScroll: true,
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Could not complete.'),
        },
    );
}
</script>

<template>
    <Head title="Follow-ups" />

    <PageHeader
        eyebrow="Lead management"
        title="Follow-ups"
        :description="
            canSeeAll
                ? 'Every open lead follow-up, soonest first.'
                : 'Your open follow-ups, soonest first.'
        "
    />

    <PanelTabs
        v-model="tab"
        label="When"
        :tabs="[
            { key: 'all', label: 'All open' },
            { key: 'overdue', label: 'Overdue' },
            { key: 'today', label: 'Today' },
            { key: 'upcoming', label: 'Upcoming' },
        ]"
    />

    <PanelCard flush>
        <DataTable
            :columns="columns"
            :rows="followUps.data"
            caption="Follow-ups"
            empty-icon="event_available"
            empty-title="No follow-ups here"
            empty-description="Schedule follow-ups from a lead's page."
        >
            <template #cell-due_at="{ row }">
                <span
                    class="font-semibold"
                    :class="row.overdue ? 'text-danger' : 'text-foreground'"
                >
                    <AppIcon
                        v-if="row.overdue"
                        name="schedule"
                        :size="14"
                        class="mr-1 align-[-2px]"
                    />{{ formatDateTime(row.due_at) }}
                </span>
            </template>
            <template #cell-lead="{ row }">
                <Link
                    :href="row.lead_url"
                    class="font-semibold text-foreground hover:text-primary"
                    >{{ row.lead.name }}</Link
                >
                <span
                    class="mt-0.5 flex items-center gap-1.5 text-[11px] text-muted-foreground"
                >
                    {{ row.lead.number }}
                    <StatusBadge
                        :label="row.lead.status"
                        :tone="row.lead.tone"
                    />
                </span>
            </template>
            <template #actions="{ row }">
                <Link :href="row.lead_url" class="tiny-action">Open lead</Link>
                <button
                    type="button"
                    class="tiny-action"
                    @click="complete(row)"
                >
                    Done
                </button>
            </template>
        </DataTable>
        <TablePagination :meta="followUps" />
    </PanelCard>
</template>
