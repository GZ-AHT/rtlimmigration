<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SearchInput from '@/components/panel/SearchInput.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { Button } from '@/components/ui/button';
import { clear, destroy, index } from '@/routes/admin/seo/not-found';
import type { Paginated, TableColumn } from '@/types';

/** REQ §22.3 "404 log": addresses visitors asked for that do not exist. */
type Row = {
    id: number;
    path: string;
    hits: number;
    referrer: string | null;
    last_seen: string;
    redirect_url: string;
};

const props = defineProps<{
    logs: Paginated<Row>;
    filters: { search: string };
    total: number;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: '404 log', href: index() }] },
});

const search = ref(props.filters.search);
watch(search, (value) =>
    router.get(
        index().url,
        { search: value || undefined },
        { preserveState: true, replace: true },
    ),
);

const confirmClear = ref(false);

const columns: TableColumn<Row>[] = [
    { key: 'path', label: 'Address' },
    { key: 'hits', label: 'Visits', align: 'right' },
    { key: 'referrer', label: 'Came from', hideBelow: 'lg' },
    { key: 'last_seen', label: 'Last seen', hideBelow: 'md' },
    { key: 'actions', label: 'Actions', align: 'right' },
];
</script>

<template>
    <Head title="404 log" />

    <PageHeader
        eyebrow="SEO management"
        title="404 log"
        description="Public addresses that visitors or search engines asked for but that do not exist, most visited first. Send the busy ones to a real page with a redirect."
    >
        <template v-if="total > 0" #actions>
            <Button variant="outline" @click="confirmClear = true"
                ><AppIcon name="delete_sweep" :size="18" /> Clear log</Button
            >
        </template>
    </PageHeader>

    <PanelCard flush>
        <div class="border-b p-3">
            <SearchInput
                v-model="search"
                placeholder="Search addresses"
                label="Search the 404 log"
            />
        </div>
        <DataTable
            :columns="columns"
            :rows="logs.data"
            caption="Addresses that returned 404"
            empty-icon="link_off"
            empty-title="No missing pages recorded"
        >
            <template #cell-path="{ row }">
                <code class="block max-w-[26rem] truncate text-[13px]">{{
                    row.path
                }}</code>
            </template>
            <template #cell-referrer="{ row }">
                <span
                    class="block max-w-[18rem] truncate text-xs text-muted-foreground"
                    :title="row.referrer ?? undefined"
                    >{{ row.referrer ?? '—' }}</span
                >
            </template>
            <template #cell-actions="{ row }">
                <div class="flex flex-nowrap justify-end gap-1.5">
                    <Link
                        :href="row.redirect_url"
                        class="tiny-action"
                        :aria-label="`Create a redirect for ${row.path}`"
                        ><AppIcon name="alt_route" :size="14" />
                        <span class="max-sm:sr-only">Redirect</span></Link
                    >
                    <button
                        type="button"
                        class="tiny-action"
                        :aria-label="`Remove ${row.path} from the log`"
                        @click="
                            router.delete(destroy(row.id).url, {
                                preserveScroll: true,
                            })
                        "
                    >
                        <AppIcon name="close" :size="14" />
                        <span class="max-sm:sr-only">Remove</span>
                    </button>
                </div>
            </template>
        </DataTable>
        <TablePagination v-if="logs.last_page > 1" :meta="logs" />
    </PanelCard>

    <ConfirmDialog
        v-model:open="confirmClear"
        title="Clear the 404 log?"
        description="Every recorded address is removed. Addresses that are still missing will be recorded again the next time someone visits them."
        confirm-label="Clear log"
        destructive
        @confirm="
            router.delete(clear().url, {
                preserveScroll: true,
                onFinish: () => (confirmClear = false),
            })
        "
    />
</template>
