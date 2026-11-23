<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useFilters } from '@/composables/useFilters';
import {
    bulkDestroy,
    create,
    destroy,
    edit,
    index,
} from '@/routes/admin/universities';
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
    ...(props.canManage ? [{ key: 'select', label: '', class: 'w-10' }] : []),
    { key: 'name', label: 'University' },
    { key: 'courses_count', label: 'Courses', align: 'right', hideBelow: 'sm' },
    { key: 'status', label: 'Status' },
];

// Row checkboxes for group delete; a single trash action reuses the same dialog.
const selected = ref<number[]>([]);
const pending = ref<number[] | null>(null);
const busy = ref(false);
const pageIds = computed(() => props.universities.data.map((u) => u.id));
const allSelected = computed(
    () =>
        pageIds.value.length > 0 &&
        pageIds.value.every((id) => selected.value.includes(id)),
);

function toggle(id: number, on: boolean): void {
    selected.value = on
        ? [...new Set([...selected.value, id])]
        : selected.value.filter((v) => v !== id);
}

function toggleAll(on: boolean): void {
    selected.value = on ? [...pageIds.value] : [];
}

function remove(): void {
    const ids = pending.value;

    if (!ids?.length) {
        return;
    }

    const options = {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onSuccess: () => {
            selected.value = selected.value.filter((id) => !ids.includes(id));
        },
        onError: (e: Record<string, string>) =>
            toast.error(Object.values(e)[0] ?? 'Could not delete.'),
        onFinish: () => {
            busy.value = false;
            pending.value = null;
        },
    };

    if (ids.length === 1) {
        router.delete(destroy(ids[0]).url, options);
    } else {
        router.delete(bulkDestroy().url, { ...options, data: { ids } });
    }
}
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
        <div
            v-if="canManage && universities.data.length"
            class="flex items-center gap-3 border-b border-border px-[18px] py-2.5 text-[13px]"
        >
            <label class="flex items-center gap-2">
                <Checkbox
                    :model-value="allSelected"
                    aria-label="Select all universities on this page"
                    @update:model-value="(v) => toggleAll(v === true)"
                />
                {{
                    selected.length
                        ? `${selected.length} selected`
                        : 'Select all'
                }}
            </label>
            <Button
                v-if="selected.length"
                variant="outline"
                size="sm"
                class="ml-auto"
                @click="pending = [...selected]"
            >
                <AppIcon name="delete" :size="16" /> Delete selected
            </Button>
        </div>
        <DataTable
            :columns="columns"
            :rows="universities.data"
            caption="Universities"
            empty-title="No universities yet"
            empty-icon="account_balance"
        >
            <template #cell-select="{ row }">
                <Checkbox
                    :model-value="selected.includes(row.id)"
                    :aria-label="`Select ${row.name}`"
                    @update:model-value="(v) => toggle(row.id, v === true)"
                />
            </template>
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
                <button
                    v-if="canManage"
                    type="button"
                    class="tiny-action !text-danger"
                    @click="pending = [row.id]"
                >
                    <AppIcon name="delete" :size="13" />
                    <span>Delete</span>
                </button>
            </template>
        </DataTable>
        <TablePagination
            v-if="universities.last_page > 1"
            :meta="universities"
        />
    </PanelCard>

    <ConfirmDialog
        :open="pending !== null"
        :title="
            pending && pending.length > 1
                ? `Delete ${pending.length} universities?`
                : 'Delete this university?'
        "
        description="The university and all its courses are permanently removed from the website and the pathway finder."
        :confirm-label="
            pending && pending.length > 1 ? 'Delete selected' : 'Delete'
        "
        destructive
        :loading="busy"
        @update:open="(v) => !v && (pending = null)"
        @confirm="remove"
    />
</template>
