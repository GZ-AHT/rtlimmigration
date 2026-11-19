<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
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
import { create, edit, index, restore } from '@/routes/admin/pages';
import type { Paginated, PublishState, TableColumn } from '@/types';

type Row = {
    id: number;
    title: string;
    path: string;
    system: boolean;
    category: string | null;
    state: PublishState;
    visibility: string;
    sections: number;
    updated_at: string | null;
    trashed: boolean;
};

const props = defineProps<{
    pages: Paginated<Row>;
    filters: { search: string; category: string; trash: boolean };
    categories: string[];
    can: { manage: boolean };
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Pages', href: index() }] } });

const { filters, reset } = useFilters(
    {
        search: props.filters.search,
        category: props.filters.category,
        trash: props.filters.trash ? '1' : '',
    },
    { only: ['pages', 'filters'] },
);

const columns: TableColumn<Row>[] = [
    { key: 'title', label: 'Page' },
    { key: 'state', label: 'Status' },
    { key: 'visibility', label: 'Visible to', hideBelow: 'lg' },
    {
        key: 'updated_at',
        label: 'Updated',
        hideBelow: 'md',
        format: (r) => relativeTime(r.updated_at),
    },
];
</script>

<template>
    <Head title="Pages" />

    <PageHeader
        eyebrow="Page management"
        title="Pages"
        description="Website pages built from sections. Built-in pages (home, about, contact…) can be edited but not deleted."
    >
        <template v-if="can.manage" #actions>
            <Button as-child
                ><Link :href="create()"
                    ><AppIcon name="add" :size="18" /> New page</Link
                ></Button
            >
        </template>
    </PageHeader>

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Search pages…"
        >
            <template #filters>
                <SelectInput
                    v-if="categories.length"
                    v-model="filters.category"
                    :options="categories.map((c) => ({ value: c, label: c }))"
                    placeholder="All categories"
                    label="Filter by category"
                    compact
                />
                <SelectInput
                    v-model="filters.trash"
                    :options="[{ value: '1', label: 'Trash' }]"
                    placeholder="Active pages"
                    label="Show"
                    compact
                />
            </template>
            <template
                v-if="filters.search || filters.category || filters.trash"
                #actions
            >
                <Button variant="ghost" size="sm" @click="reset()"
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="pages.data"
            caption="Pages"
            empty-title="No pages"
            empty-icon="web"
        >
            <template #cell-title="{ row }">
                <Link
                    v-if="!row.trashed"
                    :href="edit(row.id)"
                    class="block font-semibold text-foreground hover:underline"
                    >{{ row.title }}</Link
                >
                <span v-else class="block font-semibold">{{ row.title }}</span>
                <span class="block text-[11px] text-muted-foreground">
                    {{ row.path }} · {{ row.sections }} sections
                    <template v-if="row.system"> · built-in</template>
                    <template v-if="row.category">
                        · {{ row.category }}</template
                    >
                </span>
            </template>
            <template #cell-state="{ row }">
                <StatusBadge :label="row.state.label" :tone="row.state.tone" />
            </template>
            <template #actions="{ row }">
                <button
                    v-if="row.trashed && can.manage"
                    type="button"
                    class="tiny-action"
                    @click="
                        router.patch(
                            restore(row.id).url,
                            {},
                            {
                                preserveScroll: true,
                                onError: (e) =>
                                    toast.error(Object.values(e)[0]),
                            },
                        )
                    "
                >
                    <AppIcon name="restore" :size="13" />
                    <span>Restore</span>
                </button>
                <template v-else>
                    <Link :href="edit(row.id)" class="tiny-action">
                        <AppIcon
                            :name="can.manage ? 'edit' : 'visibility'"
                            :size="13"
                        />
                        <span>{{ can.manage ? 'Edit' : 'View' }}</span>
                    </Link>
                    <a
                        v-if="row.state.key === 'published'"
                        :href="row.path"
                        target="_blank"
                        rel="noopener"
                        class="tiny-action"
                    >
                        <AppIcon name="open_in_new" :size="13" />
                        <span>Open</span>
                    </a>
                </template>
            </template>
        </DataTable>
        <TablePagination v-if="pages.last_page > 1" :meta="pages" />
    </PanelCard>
</template>
