<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
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
import { formatDate, relativeTime } from '@/lib/format';
import {
    create,
    edit,
    feature,
    index,
    restore,
} from '@/routes/admin/blog/posts';
import type { Option, Paginated, PublishState, TableColumn } from '@/types';

type PostRow = {
    id: number;
    title: string;
    slug: string;
    category: string | null;
    author: string | null;
    image: string | null;
    state: PublishState;
    is_featured: boolean;
    published_at: string | null;
    updated_at: string | null;
    pending_comments: number;
    views: number;
    trashed: boolean;
};

const props = defineProps<{
    posts: Paginated<PostRow>;
    filters: {
        search: string;
        state: string;
        category: string;
        author: string;
    };
    counts: Record<string, number>;
    options: { categories: Option[]; authors: Option[] };
    can: { manage: boolean; publish: boolean };
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Blog', href: index() }] } });

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['posts', 'filters', 'counts'] },
);
const tab = computed({
    get: () => filters.state || 'all',
    set: (v: string) => (filters.state = v === 'all' ? '' : v),
});
const tabs = computed(() =>
    [
        ['all', 'All'],
        ['published', 'Published'],
        ['scheduled', 'Scheduled'],
        ['draft', 'Drafts'],
        ['featured', 'Featured'],
        ['trash', 'Trash'],
    ].map(([key, label]) => ({ key, label, count: props.counts[key] ?? 0 })),
);

const columns: TableColumn<PostRow>[] = [
    { key: 'title', label: 'Post' },
    { key: 'state', label: 'Status' },
    { key: 'author', label: 'Author', hideBelow: 'lg' },
    {
        key: 'updated_at',
        label: 'Updated',
        hideBelow: 'md',
        format: (r) => relativeTime(r.updated_at),
    },
];

const onError = (e: Record<string, string>) =>
    toast.error(Object.values(e)[0] ?? 'Something went wrong.');
</script>

<template>
    <Head title="Blog posts" />

    <PageHeader
        eyebrow="Blog management"
        title="Blog posts"
        description="Articles for the website. Drafts stay private; scheduled posts go live on their date."
    >
        <template v-if="can.manage" #actions>
            <Button as-child
                ><Link :href="create()"
                    ><AppIcon name="add" :size="18" /> New post</Link
                ></Button
            >
        </template>
    </PageHeader>

    <PanelTabs v-model="tab" label="Post status" :tabs="tabs" />

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Search titles…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.category"
                    :options="options.categories"
                    placeholder="All categories"
                    label="Filter by category"
                    compact
                />
                <SelectInput
                    v-model="filters.author"
                    :options="options.authors"
                    placeholder="Any author"
                    label="Filter by author"
                    compact
                />
            </template>
            <template
                v-if="filters.search || filters.category || filters.author"
                #actions
            >
                <Button variant="ghost" size="sm" @click="reset()"
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="posts.data"
            caption="Blog posts"
            empty-title="No posts here"
            empty-icon="article"
        >
            <template #cell-title="{ row }">
                <div class="flex items-center gap-3">
                    <img
                        v-if="row.image"
                        :src="row.image"
                        alt=""
                        class="hidden size-11 shrink-0 rounded-md object-cover sm:block"
                    />
                    <div class="min-w-0">
                        <Link
                            v-if="!row.trashed && can.manage"
                            :href="edit(row.id)"
                            class="block truncate font-semibold text-foreground hover:underline"
                            >{{ row.title }}</Link
                        >
                        <span
                            v-else
                            class="block truncate font-semibold text-foreground"
                            >{{ row.title }}</span
                        >
                        <span
                            class="block truncate text-[11px] text-muted-foreground"
                        >
                            {{ row.category ?? 'Uncategorised' }}
                            <template v-if="row.pending_comments">
                                · {{ row.pending_comments }} comment{{
                                    row.pending_comments === 1 ? '' : 's'
                                }}
                                waiting</template
                            >
                        </span>
                    </div>
                </div>
            </template>
            <template #cell-state="{ row }">
                <div class="flex flex-wrap items-center gap-1">
                    <StatusBadge
                        :label="row.state.label"
                        :tone="row.state.tone"
                    />
                    <StatusBadge
                        v-if="row.is_featured"
                        label="Featured"
                        tone="warning"
                    />
                </div>
                <span
                    v-if="row.state.key === 'scheduled' && row.published_at"
                    class="block text-[11px] text-muted-foreground"
                    >{{ formatDate(row.published_at) }}</span
                >
            </template>
            <template #actions="{ row }">
                <template v-if="row.trashed">
                    <button
                        v-if="can.manage"
                        type="button"
                        class="tiny-action"
                        @click="
                            router.patch(
                                restore(row.id).url,
                                {},
                                { preserveScroll: true, onError },
                            )
                        "
                    >
                        <AppIcon name="restore" :size="13" />
                        <span>Restore</span>
                    </button>
                </template>
                <template v-else>
                    <Link
                        v-if="can.manage"
                        :href="edit(row.id)"
                        class="tiny-action"
                    >
                        <AppIcon name="edit" :size="13" />
                        <span>Edit</span>
                    </Link>
                    <button
                        v-if="can.publish"
                        type="button"
                        class="tiny-action"
                        @click="
                            router.patch(
                                feature(row.id).url,
                                {},
                                { preserveScroll: true, onError },
                            )
                        "
                    >
                        <AppIcon
                            :name="row.is_featured ? 'star_border' : 'star'"
                            :size="13"
                        />
                        <span>{{
                            row.is_featured ? 'Unfeature' : 'Feature'
                        }}</span>
                    </button>
                </template>
            </template>
        </DataTable>
        <TablePagination v-if="posts.last_page > 1" :meta="posts" />
    </PanelCard>
</template>
