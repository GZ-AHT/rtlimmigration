<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
import { formatDate, formatDateTime } from '@/lib/format';
import type {
    CounselorOption,
    LeadRow,
    Option,
    Paginated,
    SortDirection,
    TableColumn,
} from '@/types';

type Filters = {
    search: string;
    tab: string;
    status: string;
    country: string;
    service: string;
    counselor: string;
    source: string;
    follow_up: string;
    duplicates: string;
    sort: string;
    direction: string;
};

const props = defineProps<{
    title: string;
    leads: Paginated<LeadRow>;
    filters: Filters;
    tabCounts: Record<'all' | 'new' | 'pending' | 'closed', number>;
    options: {
        statuses: Option[];
        countries: Option[];
        services: Option[];
        sources: Option[];
        counselors: CounselorOption[];
    };
    canSeeAll: boolean;
    urls: { create: string | null };
}>();

const page = usePage();

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['leads', 'filters', 'tabCounts'] },
);
const showMore = ref(
    Boolean(
        props.filters.source ||
        props.filters.follow_up ||
        props.filters.duplicates ||
        props.filters.status,
    ),
);

// Same page serves "Leads" and "Contact inquiries", so set the crumb here.
setLayoutProps({ breadcrumbs: [{ title: props.title, href: page.url }] });
const isInquiries = computed(() => props.title !== 'Leads');
const hasFilters = computed(() =>
    [
        'search',
        'status',
        'country',
        'service',
        'counselor',
        'source',
        'follow_up',
        'duplicates',
    ].some((k) => filters[k as keyof Filters]),
);

const counselorOptions = computed<Option[]>(() => [
    { value: 'unassigned', label: 'Unassigned' },
    ...props.options.counselors.map((c) => ({
        value: c.value,
        label: c.label,
    })),
]);

const columns = computed<TableColumn<LeadRow>[]>(() => [
    { key: 'name', label: 'Lead', sortable: true },
    { key: 'service', label: 'Interest', hideBelow: 'lg' },
    { key: 'status', label: 'Status', sortable: true },
    ...(props.canSeeAll
        ? [{ key: 'counselor', label: 'Counselor', hideBelow: 'md' as const }]
        : []),
    {
        key: 'next_follow_up_at',
        label: 'Next follow-up',
        sortable: true,
        hideBelow: 'md',
    },
    {
        key: 'created_at',
        label: 'Received',
        sortable: true,
        hideBelow: 'lg',
        format: (r) => formatDate(r.created_at),
    },
]);

function onSort(key: string, direction: SortDirection): void {
    filters.sort = key;
    filters.direction = direction;
}
</script>

<template>
    <Head :title="title" />

    <PageHeader
        :eyebrow="isInquiries ? 'Ticket & contact' : 'Lead management'"
        :title="title"
        :description="
            isInquiries
                ? 'Enquiries submitted through the website contact form.'
                : canSeeAll
                  ? 'Every enquiry, who is working it and what happens next.'
                  : 'Leads assigned to you.'
        "
    >
        <template v-if="urls.create" #actions>
            <Button as-child>
                <Link :href="urls.create"
                    ><AppIcon name="add" :size="18" /> New lead</Link
                >
            </Button>
        </template>
    </PageHeader>

    <PanelTabs
        v-model="filters.tab"
        label="Lead stage"
        :tabs="[
            { key: 'all', label: 'All', count: tabCounts.all },
            { key: 'new', label: 'New', count: tabCounts.new },
            { key: 'pending', label: 'Pending', count: tabCounts.pending },
            { key: 'closed', label: 'Closed', count: tabCounts.closed },
        ]"
    />

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Name, email, phone or lead no.…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.country"
                    :options="options.countries"
                    placeholder="All destinations"
                    label="Filter by destination"
                    compact
                />
                <SelectInput
                    v-model="filters.service"
                    :options="options.services"
                    placeholder="All services"
                    label="Filter by service"
                    compact
                />
                <SelectInput
                    v-if="canSeeAll"
                    v-model="filters.counselor"
                    :options="counselorOptions"
                    placeholder="All counselors"
                    label="Filter by counselor"
                    compact
                />
                <Button
                    variant="ghost"
                    size="sm"
                    :aria-expanded="showMore"
                    @click="showMore = !showMore"
                >
                    <AppIcon name="tune" :size="16" /> More
                </Button>
            </template>
            <template v-if="hasFilters" #actions>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="
                        reset();
                        filters.tab = 'all';
                        filters.sort = 'created_at';
                        filters.direction = 'desc';
                    "
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <div
            v-if="showMore"
            class="flex flex-wrap items-center gap-2.5 border-b bg-muted/50 px-4 py-3 sm:px-5"
        >
            <SelectInput
                v-model="filters.status"
                :options="options.statuses"
                placeholder="Any status"
                label="Filter by status"
                compact
            />
            <SelectInput
                v-if="options.sources.length"
                v-model="filters.source"
                :options="options.sources"
                placeholder="Any source"
                label="Filter by source"
                compact
            />
            <SelectInput
                v-model="filters.follow_up"
                :options="[
                    { value: 'due', label: 'Follow-up due today' },
                    { value: 'overdue', label: 'Follow-up overdue' },
                ]"
                placeholder="Any follow-up"
                label="Filter by follow-up"
                compact
            />
            <label class="flex items-center gap-2 text-[13px]">
                <input
                    type="checkbox"
                    class="accent-primary"
                    :checked="filters.duplicates === '1'"
                    @change="
                        filters.duplicates = ($event.target as HTMLInputElement)
                            .checked
                            ? '1'
                            : ''
                    "
                />
                Possible duplicates only
            </label>
        </div>

        <DataTable
            :columns="columns"
            :rows="leads.data"
            :sort-key="filters.sort"
            :sort-direction="filters.direction as SortDirection"
            :caption="title"
            empty-icon="person_search"
            empty-title="No leads found"
            :empty-description="
                hasFilters
                    ? 'Try different filters.'
                    : 'New enquiries will appear here.'
            "
            @sort="onSort"
        >
            <template #cell-name="{ row }">
                <Link :href="row.url!" class="block min-w-0 hover:text-primary">
                    <span
                        class="flex items-center gap-1.5 font-semibold text-foreground"
                    >
                        <span class="truncate">{{ row.name }}</span>
                        <AppIcon
                            v-if="row.is_duplicate"
                            name="content_copy"
                            :size="14"
                            class="text-warning"
                            title="Possible duplicate"
                        />
                    </span>
                    <span
                        class="block truncate text-[11px] text-muted-foreground"
                    >
                        {{ row.lead_number }} · {{ row.email ?? row.phone }}
                    </span>
                </Link>
            </template>
            <template #cell-service="{ row }">
                <span class="block">{{ row.service ?? '—' }}</span>
                <span
                    v-if="row.country"
                    class="block text-[11px] text-muted-foreground"
                    >{{ row.country }}</span
                >
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
            </template>
            <template #cell-counselor="{ row }">
                <span v-if="row.counselor">{{ row.counselor }}</span>
                <span v-else class="text-warning">Unassigned</span>
            </template>
            <template #cell-next_follow_up_at="{ row }">
                <span
                    v-if="row.next_follow_up_at"
                    :class="{
                        'font-semibold text-danger': row.follow_up_overdue,
                    }"
                >
                    {{ formatDateTime(row.next_follow_up_at) }}
                </span>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #actions="{ row }">
                <Link :href="row.url!" class="tiny-action">Open</Link>
            </template>
        </DataTable>

        <TablePagination :meta="leads" />
    </PanelCard>
</template>
