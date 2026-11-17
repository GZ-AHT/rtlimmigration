<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
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
import { formatDateTime } from '@/lib/format';
import type { ChatRow, Option, Paginated, TableColumn } from '@/types';

/** REQ §18 chat history and offline messages (admin and counselor areas). */
const props = defineProps<{
    kind: 'chat' | 'offline' | 'portal' | 'guest';
    conversations: Paginated<ChatRow>;
    filters: { search: string; status: string; assignee: string; tag: string };
    statusCounts: Record<string, number>;
    statuses: Option[];
    assignees: Option[];
    connected: boolean;
    receiving: boolean;
    topics: string[];
    urls: {
        index: string;
        offline: string;
        portal: string;
        guest: string;
        moderation: string | null;
        settings: string | null;
        tawk: string;
    };
}>();

const isOffline = computed(() => props.kind === 'offline');
const isPortal = computed(() => props.kind === 'portal');
const isGuest = computed(() => props.kind === 'guest');
const isNative = computed(() => isPortal.value || isGuest.value);
const title = computed(() =>
    isPortal.value
        ? 'Student messages'
        : isGuest.value
          ? 'Website chats'
          : isOffline.value
            ? 'Offline messages'
            : 'Chat history',
);

setLayoutProps({
    breadcrumbs: [
        { title: 'Live chat', href: props.urls.index },
        ...(isOffline.value
            ? [{ title: 'Offline messages', href: props.urls.offline }]
            : []),
        ...(isPortal.value
            ? [{ title: 'Student messages', href: props.urls.portal }]
            : []),
        ...(isGuest.value
            ? [{ title: 'Website chats', href: props.urls.guest }]
            : []),
    ],
});

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['conversations', 'filters', 'statusCounts'] },
);

const tab = computed({
    get: () => filters.status || 'all',
    set: (v: string) => (filters.status = v === 'all' ? '' : v),
});

const assigneeOptions = computed<Option[]>(() => [
    { value: 'me', label: 'Assigned to me' },
    { value: 'unassigned', label: 'Unassigned' },
    ...props.assignees,
]);

const columns = computed<TableColumn<ChatRow>[]>(() => [
    { key: 'visitor', label: isPortal.value ? 'Student' : 'Visitor' },
    { key: 'status', label: 'Status' },
    { key: 'assignee', label: 'Assigned to', hideBelow: 'md' },
    isNative.value
        ? {
              key: 'last_message_at',
              label: 'Last message',
              hideBelow: 'lg',
              format: (r) => formatDateTime(r.last_message_at),
          }
        : {
              key: 'started_at',
              label: 'Received',
              hideBelow: 'lg',
              format: (r) => formatDateTime(r.started_at),
          },
]);
</script>

<template>
    <Head :title="title" />

    <PageHeader
        eyebrow="Live chat"
        :title="title"
        :description="
            isPortal
                ? 'Each student\'s message thread from the portal. Reply here; students see replies in their portal.'
                : isGuest
                  ? 'Chats from website visitors without an account. Reply here; visitors see replies on the chat page.'
                  : isOffline
                    ? 'Messages visitors left while no one was online. Reply by email or phone, then mark them handled.'
                    : 'A record of Tawk.to chats from the website and the student portal. Answer live chats in Tawk.to.'
        "
    >
        <template #actions>
            <Button
                v-if="urls.moderation && isNative"
                variant="outline"
                as-child
            >
                <Link :href="urls.moderation"
                    ><AppIcon name="shield" :size="18" /> Moderation</Link
                >
            </Button>
            <Button v-if="!isNative" variant="outline" as-child>
                <a :href="urls.tawk" target="_blank" rel="noopener noreferrer"
                    ><AppIcon name="open_in_new" :size="18" /> Open Tawk.to</a
                >
            </Button>
            <Button v-if="urls.settings" variant="outline" as-child>
                <Link :href="urls.settings"
                    ><AppIcon name="settings" :size="18" /> Settings</Link
                >
            </Button>
        </template>
    </PageHeader>

    <div
        v-if="!isNative && (!connected || !receiving)"
        class="mb-4 flex items-start gap-2.5 rounded-xl border border-warning/40 bg-warning-soft p-3.5 text-[13px] text-warning"
        role="status"
    >
        <AppIcon name="info" :size="18" class="mt-px shrink-0" />
        <span>
            <template v-if="!connected"
                >The chat widget is switched off, so visitors can't start
                chats.</template
            >
            <template v-if="!receiving">
                No webhook secret is set, so new chats won't appear
                here.</template
            >
            <Link
                v-if="urls.settings"
                :href="urls.settings"
                class="ml-1 font-semibold underline"
                >Set up live chat</Link
            >
            <template v-else> Ask an administrator to set it up.</template>
        </span>
    </div>

    <PanelTabs
        v-model="tab"
        label="Status"
        :tabs="[
            { key: 'all', label: 'All' },
            ...statuses.map((s) => ({
                key: s.value,
                label: s.label,
                count: statusCounts[s.value] ?? 0,
            })),
        ]"
    />

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Name, email or message…"
        >
            <template v-if="assignees.length" #filters>
                <SelectInput
                    v-model="filters.assignee"
                    :options="assigneeOptions"
                    placeholder="Anyone"
                    label="Filter by assignee"
                    compact
                />
            </template>
            <template
                v-if="filters.search || filters.assignee || filters.tag"
                #actions
            >
                <Button variant="ghost" size="sm" @click="reset()"
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <div
            class="flex flex-wrap items-center gap-1.5 px-5 pb-3"
            role="group"
            aria-label="Filter by topic"
        >
            <button
                v-for="t in topics"
                :key="t"
                type="button"
                class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold transition-colors"
                :class="
                    filters.tag === t
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-muted'
                "
                :aria-pressed="filters.tag === t"
                @click="filters.tag = filters.tag === t ? '' : t"
            >
                {{ t }}
            </button>
        </div>
        <p
            v-if="filters.tag && !topics.includes(filters.tag)"
            class="flex items-center gap-2 px-5 pb-3 text-xs text-muted-foreground"
        >
            Tagged <StatusBadge :label="filters.tag" tone="info" />
            <button type="button" class="tiny-action" @click="filters.tag = ''">
                Remove
            </button>
        </p>
        <DataTable
            :columns="columns"
            :rows="conversations.data"
            :caption="title"
            :empty-icon="
                isPortal
                    ? 'chat'
                    : isGuest
                      ? 'travel_explore'
                      : isOffline
                        ? 'mark_email_unread'
                        : 'forum'
            "
            :empty-title="
                isPortal
                    ? 'No student messages yet'
                    : isGuest
                      ? 'No website chats yet'
                      : isOffline
                        ? 'No offline messages'
                        : 'No chats recorded yet'
            "
        >
            <template #cell-visitor="{ row }">
                <Link :href="row.url!" class="block min-w-0 hover:text-primary">
                    <span
                        class="flex items-center gap-1.5 truncate font-semibold text-foreground"
                    >
                        {{ row.visitor }}
                        <span
                            v-if="row.unread_count"
                            class="grid h-5 min-w-5 place-items-center rounded-full bg-primary px-1.5 text-[10px] font-bold text-primary-foreground"
                            :aria-label="`${row.unread_count} unread`"
                            >{{ row.unread_count }}</span
                        >
                        <StatusBadge
                            v-if="row.student"
                            :label="row.student.number"
                            tone="success"
                        />
                    </span>
                    <span
                        class="block max-w-[460px] truncate text-[11px] text-muted-foreground"
                        >{{
                            row.subject ??
                            row.preview ??
                            row.visitor_email ??
                            '—'
                        }}</span
                    >
                </Link>
                <span v-if="row.tags.length" class="mt-1 flex flex-wrap gap-1">
                    <button
                        v-for="t in row.tags"
                        :key="t"
                        type="button"
                        class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold hover:bg-accent"
                        @click="filters.tag = t"
                    >
                        {{ t }}
                    </button>
                </span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
            </template>
            <template #cell-assignee="{ row }">
                <span v-if="row.assignee">{{ row.assignee }}</span>
                <span v-else class="text-muted-foreground">Unassigned</span>
            </template>
            <template #actions="{ row }">
                <Link :href="row.url!" class="tiny-action">Open</Link>
            </template>
        </DataTable>
        <TablePagination :meta="conversations" />
    </PanelCard>
</template>
