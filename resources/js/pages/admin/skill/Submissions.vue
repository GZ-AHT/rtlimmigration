<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { useFilters } from '@/composables/useFilters';
import { relativeTime } from '@/lib/format';
import { index as assessmentsIndex } from '@/routes/admin/skill-assessments';
import { index, show } from '@/routes/admin/skill-assessments/submissions';
import type { Option, Paginated, TableColumn } from '@/types';

/** Visitors' skill migration assessments, by destination country and result. */
type Row = {
    id: number;
    name: string;
    email: string;
    country: string;
    title: string;
    status: string;
    preview: boolean;
    score: number | null;
    passed: boolean | null;
    created_at: string | null;
};

const props = defineProps<{
    submissions: Paginated<Row>;
    filters: { country: string; result: string; search: string };
    countries: Option[];
    counts: { passed: number; below: number; started: number };
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Skill migration', href: assessmentsIndex() },
            { title: 'Submissions', href: index() },
        ],
    },
});

const { filters } = useFilters(
    { ...props.filters },
    { only: ['submissions', 'filters', 'counts'] },
);
const tab = computed({
    get: () => filters.result || 'all',
    set: (v: string) => (filters.result = v === 'all' ? '' : v),
});

const columns: TableColumn<Row>[] = [
    { key: 'name', label: 'Visitor' },
    { key: 'country', label: 'Destination' },
    { key: 'score', label: 'Result' },
    {
        key: 'created_at',
        label: 'Started',
        hideBelow: 'sm',
        format: (r) => relativeTime(r.created_at),
    },
];
</script>

<template>
    <Head title="Assessment submissions" />
    <PageHeader
        eyebrow="Skill migration"
        title="Submissions"
        description="Every finished assessment also becomes a lead (source: Skill migration assessment)."
    />
    <PanelTabs
        v-model="tab"
        label="Result"
        :tabs="[
            { key: 'all', label: 'All' },
            { key: 'passed', label: 'Meets guide', count: counts.passed },
            { key: 'below', label: 'Below guide', count: counts.below },
            { key: 'started', label: 'Not finished', count: counts.started },
            { key: 'preview', label: 'Staff previews' },
        ]"
    />
    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Name, email or phone…"
            ><template #filters
                ><div class="w-52">
                    <SelectInput
                        v-model="filters.country"
                        :options="countries"
                        placeholder="All countries"
                        label="Destination country"
                    /></div></template
        ></TableToolbar>
        <DataTable
            :columns="columns"
            :rows="submissions.data"
            caption="Assessment submissions"
            empty-title="No submissions yet"
            empty-icon="quiz"
        >
            <template #cell-name="{ row }">
                <Link
                    :href="show(row.id)"
                    class="block font-semibold text-foreground hover:underline"
                    >{{ row.name }}</Link
                >
                <span class="block text-[11px] text-muted-foreground">{{
                    row.email
                }}</span>
            </template>
            <template #cell-score="{ row }">
                <template v-if="row.status === 'completed'">
                    <span class="mr-2 font-semibold"
                        >{{ Math.round(row.score ?? 0) }}%</span
                    >
                    <StatusBadge
                        :label="row.passed ? 'Meets guide' : 'Below guide'"
                        :tone="row.passed ? 'success' : 'warning'"
                    />
                </template>
                <StatusBadge v-else label="Not finished" tone="neutral" />
                <StatusBadge
                    v-if="row.preview"
                    label="Preview"
                    tone="info"
                    class="ml-1"
                />
            </template>
            <template #actions="{ row }"
                ><Link :href="show(row.id)" class="tiny-action"
                    >Open</Link
                ></template
            >
        </DataTable>
        <TablePagination v-if="submissions.last_page > 1" :meta="submissions" />
    </PanelCard>
</template>
