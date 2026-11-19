<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
import { create, edit, index } from '@/routes/admin/universities';
import type { Option, Paginated, TableColumn } from '@/types';

type Row = {
    id: number;
    name: string;
    country: string;
    city: string | null;
    logo: string | null;
    courses_count: number;
    is_featured: boolean;
    is_active: boolean;
};

const props = defineProps<{
    universities: Paginated<Row>;
    filters: { search: string; country: string; status: string };
    countries: Option[];
    canManage: boolean;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Universities', href: index() }] },
});

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['universities', 'filters'] },
);

const columns: TableColumn<Row>[] = [
    { key: 'name', label: 'University' },
    { key: 'courses_count', label: 'Courses', align: 'right', hideBelow: 'sm' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <Head title="Universities" />

    <PageHeader
        eyebrow="University management"
        title="Universities"
        description="Partner universities and their courses. Active ones appear on the website and in the pathway finder."
    >
        <template v-if="canManage" #actions>
            <Button as-child
                ><Link :href="create()"
                    ><AppIcon name="add" :size="18" /> Add university</Link
                ></Button
            >
        </template>
    </PageHeader>

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Name or city…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.country"
                    :options="countries"
                    placeholder="All countries"
                    label="Filter by country"
                    compact
                />
                <SelectInput
                    v-model="filters.status"
                    :options="[
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' },
                        { value: 'featured', label: 'Featured' },
                    ]"
                    placeholder="Any status"
                    label="Filter by status"
                    compact
                />
            </template>
            <template
                v-if="filters.search || filters.country || filters.status"
                #actions
            >
                <Button variant="ghost" size="sm" @click="reset()"
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="universities.data"
            caption="Universities"
            empty-title="No universities yet"
            empty-icon="account_balance"
        >
            <template #cell-name="{ row }">
                <div class="flex items-center gap-3">
                    <img
                        v-if="row.logo"
                        :src="row.logo"
                        alt=""
                        class="size-10 shrink-0 rounded-md border border-border bg-white object-contain p-0.5"
                    />
                    <span
                        v-else
                        class="grid size-10 shrink-0 place-items-center rounded-md bg-muted"
                        ><AppIcon name="account_balance" :size="18"
                    /></span>
                    <div class="min-w-0">
                        <Link
                            :href="edit(row.id)"
                            class="block truncate font-semibold text-foreground hover:underline"
                            >{{ row.name }}</Link
                        >
                        <span class="block text-[11px] text-muted-foreground">{{
                            [row.city, row.country].filter(Boolean).join(', ')
                        }}</span>
                    </div>
                </div>
            </template>
            <template #cell-status="{ row }">
                <div class="flex flex-wrap gap-1">
                    <StatusBadge
                        :label="row.is_active ? 'Active' : 'Inactive'"
                        :tone="row.is_active ? 'success' : 'neutral'"
                    />
                    <StatusBadge
                        v-if="row.is_featured"
                        label="Featured"
                        tone="warning"
                    />
                </div>
            </template>
            <template #actions="{ row }">
                <Link :href="edit(row.id)" class="tiny-action">
                    <AppIcon
                        :name="canManage ? 'edit' : 'visibility'"
                        :size="13"
                    />
                    <span>{{ canManage ? 'Edit' : 'View' }}</span>
                </Link>
            </template>
        </DataTable>
        <TablePagination
            v-if="universities.last_page > 1"
            :meta="universities"
        />
    </PanelCard>
</template>
