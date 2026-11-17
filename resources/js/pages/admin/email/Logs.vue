<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { headers as jsonHeaders } from '@/composables/useMessageThread';
import { useFilters } from '@/composables/useFilters';
import { formatDateTime, relativeTime } from '@/lib/format';
import { index, retry, show } from '@/routes/admin/email/logs';
import type { EmailMessageRow, Option, Paginated, TableColumn } from '@/types';

const props = defineProps<{
    messages: Paginated<EmailMessageRow>;
    filters: {
        search: string;
        status: string;
        account: string;
        campaign: string;
    };
    statusCounts: Record<string, number>;
    options: { statuses: Option[]; accounts: Option[]; campaigns: Option[] };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Email delivery logs', href: index() }] },
});

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['messages', 'filters', 'statusCounts'] },
);

const tab = computed({
    get: () => filters.status || 'all',
    set: (v: string) => (filters.status = v === 'all' ? '' : v),
});

const columns: TableColumn<EmailMessageRow>[] = [
    { key: 'subject', label: 'Email' },
    { key: 'status', label: 'Status' },
    { key: 'tracking', label: 'Opens / clicks', hideBelow: 'lg' },
    { key: 'sender', label: 'Sent by', hideBelow: 'md' },
    {
        key: 'created_at',
        label: 'Queued',
        hideBelow: 'sm',
        format: (r) => relativeTime(r.created_at),
    },
];

const previewing = ref<(EmailMessageRow & { body: string }) | null>(null);

async function openPreview(row: EmailMessageRow): Promise<void> {
    try {
        const res = await fetch(show(row.id).url, { headers: jsonHeaders() });
        if (!res.ok) throw new Error();
        previewing.value = await res.json();
    } catch {
        toast.error('Could not load this email.');
    }
}

function resend(row: EmailMessageRow): void {
    router.post(
        retry(row.id).url,
        {},
        {
            preserveScroll: true,
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Could not queue it again.'),
        },
    );
}
</script>

<template>
    <Head title="Email delivery logs" />

    <PageHeader
        eyebrow="Email / Roundcube"
        title="Delivery logs"
        description="Every email sent from the system: compose, bulk email and test sends. Mail sent from webmail stays in that mailbox's Sent folder."
    />

    <PanelTabs
        v-model="tab"
        label="Delivery status"
        :tabs="[
            { key: 'all', label: 'All' },
            ...options.statuses.map((s) => ({
                key: s.value,
                label: s.label,
                count: statusCounts[s.value] ?? 0,
            })),
        ]"
    />

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Recipient or subject…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.account"
                    :options="options.accounts"
                    placeholder="Any mailbox"
                    label="Filter by mailbox"
                    compact
                />
                <SelectInput
                    v-if="options.campaigns.length"
                    v-model="filters.campaign"
                    :options="options.campaigns"
                    placeholder="Any campaign"
                    label="Filter by campaign"
                    compact
                />
            </template>
            <template
                v-if="filters.search || filters.account || filters.campaign"
                #actions
            >
                <Button variant="ghost" size="sm" @click="reset()"
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="messages.data"
            caption="Email delivery log"
            empty-title="No emails yet"
            empty-icon="outgoing_mail"
        >
            <template #cell-subject="{ row }">
                <button
                    type="button"
                    class="block max-w-full truncate text-left font-semibold text-foreground hover:underline"
                    @click="openPreview(row)"
                >
                    {{ row.subject }}
                </button>
                <span class="block truncate text-[11px] text-muted-foreground">
                    {{ row.from_email }} →
                    {{
                        row.to_name
                            ? `${row.to_name} <${row.to_email}>`
                            : row.to_email
                    }}
                    <template v-if="row.recipient">
                        · {{ row.recipient }}</template
                    >
                    <template v-if="row.campaign">
                        · {{ row.campaign }}</template
                    >
                </span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
                <span
                    v-if="row.error && row.status === 'failed'"
                    class="mt-0.5 block max-w-56 truncate text-[11px] text-danger"
                    :title="row.error"
                    >{{ row.error }}</span
                >
            </template>
            <template #cell-tracking="{ row }">
                <span class="text-[13px]"
                    >{{ row.open_count }} / {{ row.click_count }}</span
                >
            </template>
            <template #cell-sender="{ row }">
                {{ row.sender ?? 'System' }}
            </template>
            <template #actions="{ row }">
                <button
                    type="button"
                    class="tiny-action"
                    @click="openPreview(row)"
                >
                    View
                </button>
                <button
                    v-if="row.status === 'failed'"
                    type="button"
                    class="tiny-action"
                    @click="resend(row)"
                >
                    Retry
                </button>
            </template>
        </DataTable>
        <TablePagination v-if="messages.last_page > 1" :meta="messages" />
    </PanelCard>

    <Dialog
        :open="previewing !== null"
        @update:open="(v) => !v && (previewing = null)"
    >
        <DialogContent class="rounded-[14px] sm:max-w-[680px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">{{
                    previewing?.subject
                }}</DialogTitle>
                <DialogDescription class="text-xs">
                    From {{ previewing?.from_email }} to
                    {{ previewing?.to_email }} ·
                    {{
                        formatDateTime(
                            previewing?.sent_at ?? previewing?.created_at,
                        )
                    }}
                </DialogDescription>
            </DialogHeader>
            <dl
                v-if="previewing"
                class="grid grid-cols-2 gap-2 text-[12px] sm:grid-cols-4"
            >
                <div>
                    <dt class="text-muted-foreground">Status</dt>
                    <dd>
                        <StatusBadge
                            :label="previewing.status_label"
                            :tone="previewing.status_tone"
                        />
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">First opened</dt>
                    <dd>
                        {{
                            previewing.opened_at
                                ? formatDateTime(previewing.opened_at)
                                : '—'
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Opens / clicks</dt>
                    <dd>
                        {{ previewing.open_count }} /
                        {{ previewing.click_count }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Attachments</dt>
                    <dd>{{ previewing.attachments.join(', ') || '—' }}</dd>
                </div>
            </dl>
            <p
                v-if="previewing?.error"
                class="rounded-md bg-danger-soft px-3 py-2 text-[12px] text-danger"
            >
                {{ previewing.error }}
            </p>
            <!-- Sandboxed: no scripts, no same-origin access, links open in a new tab. -->
            <iframe
                v-if="previewing"
                :srcdoc="`<base target=&quot;_blank&quot;>${previewing.body}`"
                sandbox="allow-popups allow-popups-to-escape-sandbox"
                class="h-[50vh] w-full rounded-md border border-border bg-white"
                title="Email content"
            />
        </DialogContent>
    </Dialog>
</template>
