<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import SearchInput from '@/components/panel/SearchInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { useFilters } from '@/composables/useFilters';
import { relativeTime } from '@/lib/format';
import { destroy, index, update } from '@/routes/admin/blog/comments';
import { edit } from '@/routes/admin/blog/posts';
import type { Option, Paginated, Tone } from '@/types';
import { Link } from '@inertiajs/vue3';

type CommentRow = {
    id: number;
    name: string;
    email: string;
    body: string;
    post: { id: number; title: string };
    status: string;
    status_label: string;
    status_tone: Tone;
    ip_address: string | null;
    created_at: string | null;
};

const props = defineProps<{
    comments: Paginated<CommentRow>;
    filters: { status: string; search: string };
    counts: Record<string, number>;
    statuses: Option[];
    canModerate: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Blog comments', href: index() }] },
});

const { filters } = useFilters(
    { ...props.filters },
    { only: ['comments', 'filters', 'counts'] },
);
const tab = computed({
    get: () => filters.status,
    set: (v: string) => (filters.status = v),
});

const onError = (e: Record<string, string>) =>
    toast.error(Object.values(e)[0] ?? 'Could not update.');
const setStatus = (c: CommentRow, status: string) =>
    router.patch(
        update(c.id).url,
        { status },
        { preserveScroll: true, onError },
    );
</script>

<template>
    <Head title="Blog comments" />

    <PageHeader
        eyebrow="Blog management"
        title="Comments"
        description="Readers' comments appear on the website only after approval."
    />

    <PanelTabs
        v-model="tab"
        label="Comment status"
        :tabs="
            statuses.map((s) => ({
                key: s.value,
                label: s.label,
                count: counts[s.value] ?? 0,
            }))
        "
    />

    <PanelCard>
        <div class="mb-3 max-w-sm">
            <SearchInput
                v-model="filters.search"
                placeholder="Search comments or emails…"
            />
        </div>
        <EmptyState
            v-if="comments.data.length === 0"
            icon="forum"
            title="No comments here"
        />
        <ul v-else class="flex flex-col divide-y divide-border">
            <li
                v-for="c in comments.data"
                :key="c.id"
                class="flex flex-col gap-2 py-3 first:pt-0"
            >
                <div
                    class="flex flex-wrap items-center gap-2 text-[12px] text-muted-foreground"
                >
                    <b class="text-[13px] text-foreground">{{ c.name }}</b>
                    <span>{{ c.email }}</span>
                    <span>· {{ relativeTime(c.created_at) }}</span>
                    <span v-if="c.ip_address">· {{ c.ip_address }}</span>
                    <StatusBadge
                        :label="c.status_label"
                        :tone="c.status_tone"
                    />
                </div>
                <p class="text-[14px] whitespace-pre-line text-foreground">
                    {{ c.body }}
                </p>
                <p class="text-[12px] text-muted-foreground">
                    On
                    <Link
                        :href="edit(c.post.id)"
                        class="font-semibold text-foreground hover:underline"
                        >{{ c.post.title }}</Link
                    >
                </p>
                <div v-if="canModerate" class="flex flex-wrap gap-1">
                    <button
                        v-if="c.status !== 'approved'"
                        type="button"
                        class="tiny-action text-success hover:border-success/30"
                        @click="setStatus(c, 'approved')"
                    >
                        <AppIcon name="check" :size="13" />
                        <span>Approve</span>
                    </button>
                    <button
                        v-if="c.status !== 'pending'"
                        type="button"
                        class="tiny-action"
                        @click="setStatus(c, 'pending')"
                    >
                        <AppIcon name="history" :size="13" />
                        <span>Back to pending</span>
                    </button>
                    <button
                        v-if="c.status !== 'spam'"
                        type="button"
                        class="tiny-action text-warning hover:border-warning/30"
                        @click="setStatus(c, 'spam')"
                    >
                        <AppIcon name="report" :size="13" />
                        <span>Spam</span>
                    </button>
                    <button
                        v-if="c.status !== 'rejected'"
                        type="button"
                        class="tiny-action text-danger hover:border-danger/30"
                        @click="setStatus(c, 'rejected')"
                    >
                        <AppIcon name="block" :size="13" />
                        <span>Reject</span>
                    </button>
                    <button
                        type="button"
                        class="tiny-action text-danger hover:border-danger/30"
                        @click="
                            router.delete(destroy(c.id).url, {
                                preserveScroll: true,
                                onError,
                            })
                        "
                    >
                        <AppIcon name="delete" :size="13" />
                        <span>Delete</span>
                    </button>
                </div>
            </li>
        </ul>
        <TablePagination v-if="comments.last_page > 1" :meta="comments" />
    </PanelCard>
</template>
