<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
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
import type {
    ApplicationRow,
    Option,
    Paginated,
    TableColumn,
    Tone,
} from '@/types';

const props = defineProps<{
    title: string;
    kind: string | null;
    activeOnly: boolean;
    applications: Paginated<ApplicationRow>;
    filters: { search: string; type: string; country: string; status: string };
    types: Option[];
    countries: Option[];
    statuses: Option[];
    totals: { key: string; label: string; tone: Tone; count: number }[];
    urls: { create: string | null };
}>();

const page = usePage();
setLayoutProps({ breadcrumbs: [{ title: props.title, href: page.url }] });

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['applications', 'filters', 'totals'] },
);

const descriptions: Record<string, string> = {
    work: 'Working visa applications. Each follows the working visa workflow, checklist and fee.',
    exam: 'Examinations and work assessments, tracked like any other application.',
};

const columns: TableColumn<ApplicationRow>[] = [
    { key: 'student', label: 'Student' },
    { key: 'visa_type', label: 'Type', hideBelow: 'md' },
    { key: 'stage', label: 'Stage' },
    { key: 'status', label: 'Status', hideBelow: 'sm' },
    { key: 'updated_at', label: 'Updated', hideBelow: 'lg' },
];
</script>

<template>
    <Head :title="title" />

    <PageHeader
        eyebrow="Visa processing"
        :title="title"
        :description="
            activeOnly
                ? 'Applications in progress, longest-untouched first. Open one to update its stage.'
                : (descriptions[kind ?? ''] ??
                  'Every application, whatever the visa type. Each follows its own workflow.')
        "
    >
        <template v-if="urls.create" #actions>
            <Button as-child
                ><Link :href="urls.create"
                    ><AppIcon name="add" :size="18" /> New application</Link
                ></Button
            >
        </template>
    </PageHeader>

    <div
        v-if="!activeOnly"
        class="mb-6 grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5"
    >
        <div
            v-for="t in totals"
            :key="t.key"
            class="rounded-xl border bg-card p-4"
        >
            <p class="text-xs text-muted-foreground">{{ t.label }}</p>
            <p class="mt-1 font-display text-2xl font-extrabold">
                {{ t.count }}
            </p>
        </div>
    </div>

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Student, application number, institution…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.type"
                    :options="types"
                    placeholder="All visa types"
                    label="Filter by visa type"
                    compact
                />
                <SelectInput
                    v-model="filters.country"
                    :options="countries"
                    placeholder="All countries"
                    label="Filter by country"
                    compact
                />
                <SelectInput
                    v-if="!activeOnly"
                    v-model="filters.status"
                    :options="statuses"
                    placeholder="Any status"
                    label="Filter by status"
                    compact
                />
            </template>
            <template
                v-if="
                    filters.search ||
                    filters.type ||
                    filters.country ||
                    filters.status
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
            :rows="applications.data"
            :caption="title"
            empty-icon="approval"
            empty-title="No applications found"
            empty-description="Start an application from here or from a student's page."
        >
            <template #cell-student="{ row }">
                <Link
                    :href="row.url!"
                    class="block font-semibold text-foreground hover:text-primary"
                    >{{ row.student }}</Link
                >
                <span class="block text-[11px] text-muted-foreground"
                    >{{ row.application_number }} ·
                    {{ row.student_number }}</span
                >
            </template>
            <template #cell-visa_type="{ row }">
                <span class="block">{{ row.visa_type }}</span>
                <span class="block text-[11px] text-muted-foreground">{{
                    [row.country, row.organisation].filter(Boolean).join(' · ')
                }}</span>
            </template>
            <template #cell-stage="{ row }">
                <span class="block font-medium text-foreground">{{
                    row.stage ?? '—'
                }}</span>
                <StatusBadge
                    class="mt-1 sm:hidden"
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
            </template>
            <template #cell-updated_at="{ row }">{{
                relativeTime(row.updated_at)
            }}</template>
            <template #actions="{ row }"
                ><Link :href="row.url!" class="tiny-action">{{
                    row.status === 'in_progress' ? 'Update' : 'Open'
                }}</Link></template
            >
        </DataTable>
        <TablePagination :meta="applications" />
    </PanelCard>
</template>
