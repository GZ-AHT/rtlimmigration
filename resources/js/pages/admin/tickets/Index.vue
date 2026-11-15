<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { relativeTime } from '@/lib/format';
import { create, index, show } from '@/routes/admin/tickets';
import type {
    Option,
    Paginated,
    SortDirection,
    TableColumn,
    TicketRow,
} from '@/types';

const props = defineProps<{
    tickets: Paginated<TicketRow>;
    filters: {
        search: string;
        status: string;
        priority: string;
        category: string;
        assignee: string;
        sort: string;
        direction: string;
    };
    statusCounts: Record<string, number>;
    options: {
        statuses: Option[];
        priorities: Option[];
        categories: Option[];
        assignees: Option[];
    };
    can: { create: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tickets', href: index() }] },
});

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['tickets', 'filters', 'statusCounts'] },
);

const tab = computed({
    get: () => filters.status || 'all',
    set: (v: string) => (filters.status = v === 'all' ? '' : v),
});

const assigneeOptions = computed<Option[]>(() => [
    { value: 'me', label: 'Assigned to me' },
    { value: 'unassigned', label: 'Unassigned' },
    ...props.options.assignees,
]);

const columns: TableColumn<TicketRow>[] = [
    { key: 'subject', label: 'Ticket' },
    { key: 'priority', label: 'Priority', sortable: true },
    { key: 'status', label: 'Status' },
    { key: 'assignee', label: 'Assignee', hideBelow: 'md' },
    {
        key: 'updated_at',
        label: 'Updated',
        sortable: true,
        hideBelow: 'lg',
        format: (r) => relativeTime(r.updated_at),
    },
];

function onSort(key: string, direction: SortDirection): void {
    filters.sort = key;
    filters.direction = direction;
}
</script>

<template>
    <Head title="Tickets" />

    <PageHeader
        eyebrow="Ticket & contact"
        title="Support tickets"
        description="Questions and issues from students and visitors."
    >
        <template v-if="can.create" #actions>
            <Button as-child
                ><Link :href="create()"
                    ><AppIcon name="add" :size="18" /> New ticket</Link
                ></Button
            >
        </template>
    </PageHeader>

    <PanelTabs
        v-model="tab"
        label="Ticket status"
        :tabs="[
            { key: 'all', label: 'All' },
            ...options.statuses.map((s) => ({
                key: s.value,
                label: s.label,
                count: statusCounts[s.value] ?? 0,
            })),
        ]"
    />

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Subject, ticket no. or requester…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.priority"
                    :options="options.priorities"
                    placeholder="Any priority"
                    label="Filter by priority"
                    compact
                />
                <SelectInput
                    v-model="filters.category"
                    :options="options.categories"
                    placeholder="All categories"
                    label="Filter by category"
                    compact
                />
                <SelectInput
                    v-model="filters.assignee"
                    :options="assigneeOptions"
                    placeholder="Anyone"
                    label="Filter by assignee"
                    compact
                />
            </template>
            <template
                v-if="
                    filters.search ||
                    filters.priority ||
                    filters.category ||
                    filters.assignee
                "
                #actions
            >
                <Button
                    variant="ghost"
                    size="sm"
                    @click="
                        reset();
                        filters.sort = 'updated_at';
                        filters.direction = 'desc';
                    "
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="tickets.data"
            :sort-key="filters.sort"
            :sort-direction="filters.direction as SortDirection"
            caption="Tickets"
            empty-icon="confirmation_number"
            empty-title="No tickets found"
            @sort="onSort"
        >
            <template #cell-subject="{ row }">
                <Link
                    :href="show(row.id)"
                    class="block min-w-0 hover:text-primary"
                >
                    <span
                        class="block truncate font-semibold text-foreground"
                        >{{ row.subject }}</span
                    >
                    <span
                        class="block truncate text-[11px] text-muted-foreground"
                        >{{ row.ticket_number }} · {{ row.requester
                        }}{{ row.category ? ` · ${row.category}` : '' }}</span
                    >
                </Link>
            </template>
            <template #cell-priority="{ row }"
                ><StatusBadge
                    :label="row.priority_label"
                    :tone="row.priority_tone"
            /></template>
            <template #cell-status="{ row }"
                ><StatusBadge :label="row.status_label" :tone="row.status_tone"
            /></template>
            <template #cell-assignee="{ row }">
                <span v-if="row.assignee">{{ row.assignee }}</span>
                <span v-else class="text-warning">Unassigned</span>
            </template>
            <template #actions="{ row }"
                ><Link :href="show(row.id)" class="tiny-action"
                    >Open</Link
                ></template
            >
        </DataTable>
        <TablePagination :meta="tickets" />
    </PanelCard>
</template>
