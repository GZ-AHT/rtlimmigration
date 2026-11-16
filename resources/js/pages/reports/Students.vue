<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import type { Paginated, TableColumn } from '@/types';

type Row = {
    id: number;
    name: string;
    student_number: string;
    counselor: string | null;
    fees: string;
    paid: string;
    refunded: string;
    refunded_minor: number;
    due: string;
    due_minor: number;
    url: string;
};

const props = defineProps<{
    students: Paginated<Row>;
    filters: { search: string; due: string };
    exportUrl: string | null;
}>();

const page = usePage();
setLayoutProps({
    breadcrumbs: [{ title: 'Student-wise accounts', href: page.url }],
});

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['students', 'filters'] },
);

const columns: TableColumn<Row>[] = [
    { key: 'name', label: 'Student' },
    { key: 'fees', label: 'Total fees', align: 'right', hideBelow: 'md' },
    { key: 'paid', label: 'Paid', align: 'right' },
    { key: 'refunded', label: 'Refunded', align: 'right', hideBelow: 'lg' },
    { key: 'due', label: 'Due', align: 'right' },
];
</script>

<template>
    <Head title="Student-wise accounts" />

    <PageHeader
        eyebrow="Reports"
        title="Student-wise accounts"
        description="Every billed student's fees, payments and balance. Largest balance first."
    >
        <template v-if="exportUrl" #actions>
            <Button variant="outline" as-child>
                <a :href="exportUrl"
                    ><AppIcon name="download" :size="18" /> Export to Excel</a
                >
            </Button>
        </template>
    </PageHeader>

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Student name or number…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.due"
                    :options="[{ value: '1', label: 'With money due' }]"
                    placeholder="All billed students"
                    label="Filter by balance"
                    compact
                />
            </template>
            <template v-if="filters.search || filters.due" #actions>
                <Button variant="ghost" size="sm" @click="reset"
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="students.data"
            caption="Student accounts"
            empty-icon="school"
            empty-title="No billed students found"
        >
            <template #cell-name="{ row }">
                <Link
                    :href="row.url"
                    class="block font-semibold text-foreground hover:text-primary"
                    >{{ row.name }}</Link
                >
                <span class="block text-[11px] text-muted-foreground"
                    >{{ row.student_number
                    }}<template v-if="row.counselor">
                        · {{ row.counselor }}</template
                    ></span
                >
            </template>
            <template #cell-refunded="{ row }">{{
                row.refunded_minor ? row.refunded : '—'
            }}</template>
            <template #cell-due="{ row }">
                <b :class="row.due_minor ? 'text-danger' : 'text-success'">{{
                    row.due_minor ? row.due : 'Settled'
                }}</b>
            </template>
        </DataTable>
        <TablePagination :meta="students" />
    </PanelCard>
</template>
