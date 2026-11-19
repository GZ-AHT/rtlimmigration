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
import { formatDate } from '@/lib/format';
import type { FileRow, Option, Paginated, TableColumn } from '@/types';

const props = defineProps<{
    files: Paginated<FileRow>;
    filters: {
        search: string;
        status: string;
        service: string;
        country: string;
    };
    options: { statuses: Option[]; services: Option[]; countries: Option[] };
}>();

const page = usePage();
setLayoutProps({ breadcrumbs: [{ title: 'Student files', href: page.url }] });

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['files', 'filters'] },
);

const columns: TableColumn<FileRow>[] = [
    { key: 'file_number', label: 'File' },
    { key: 'student', label: 'Student' },
    { key: 'service', label: 'Service / destination', hideBelow: 'md' },
    { key: 'status', label: 'Status' },
    {
        key: 'opened_at',
        label: 'Opened',
        hideBelow: 'lg',
        format: (r) => formatDate(r.opened_at),
    },
];
</script>

<template>
    <Head title="Student files" />

    <PageHeader
        eyebrow="Student file management"
        title="Student files"
        description="Every opened file: who it belongs to, what it's for and where it stands. Open new files from a student's page."
    />

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="File no., student name or number…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.status"
                    :options="options.statuses"
                    placeholder="Any status"
                    label="Filter by status"
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
                    v-model="filters.country"
                    :options="options.countries"
                    placeholder="All destinations"
                    label="Filter by destination"
                    compact
                />
            </template>
            <template
                v-if="
                    filters.search ||
                    filters.status ||
                    filters.service ||
                    filters.country
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
            :rows="files.data"
            caption="Student files"
            empty-icon="folder_shared"
            empty-title="No files found"
        >
            <template #cell-file_number="{ row }">
                <Link
                    :href="row.url!"
                    class="font-semibold text-foreground hover:text-primary"
                    >{{ row.file_number }}</Link
                >
                <span
                    v-if="row.intake"
                    class="block text-[11px] text-muted-foreground"
                    >Intake {{ row.intake }}</span
                >
            </template>
            <template #cell-student="{ row }">
                <span class="block font-medium text-foreground">{{
                    row.student
                }}</span>
                <span class="block text-[11px] text-muted-foreground">{{
                    row.student_number
                }}</span>
            </template>
            <template #cell-service="{ row }">{{
                [row.service, row.country].filter(Boolean).join(' · ') || '—'
            }}</template>
            <template #cell-status="{ row }"
                ><StatusBadge :label="row.status_label" :tone="row.status_tone"
            /></template>
            <template #actions="{ row }">
                <Link :href="row.url!" class="tiny-action">
                    <AppIcon name="visibility" :size="13" />
                    <span>Open</span>
                </Link>
            </template>
        </DataTable>
        <TablePagination :meta="files" />
    </PanelCard>
</template>
