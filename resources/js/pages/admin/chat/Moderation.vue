<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { formatDateTime } from '@/lib/format';
import { moderation } from '@/routes/admin/chat';
import type { Option, Paginated, PortalMessage, Tone } from '@/types';

/**
 * REQ §11 C moderation queue. Message text is shown as plain text; nothing
 * is rendered as HTML. Approving or rejecting keeps the message and adds
 * to its history.
 */
type QueueItem = PortalMessage & {
    status: string;
    status_label: string;
    status_tone: Tone;
    reasons: string[];
    conversation: {
        id: number;
        kind_label: string;
        student_number: string | null;
        url: string;
    };
    reviewed_by: string | null;
    reviewed_at: string | null;
    history: {
        id: number;
        from: string | null;
        to: string;
        note: string | null;
        by: string;
        at: string | null;
    }[];
    decide_url: string;
};

const props = defineProps<{
    messages: Paginated<QueueItem>;
    filters: { status: string; search: string };
    counts: Record<string, number>;
    statuses: Option[];
    securityUrl: string | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Moderation', href: moderation() }] },
});

const { filters } = useFilters(
    { ...props.filters },
    { only: ['messages', 'filters', 'counts'] },
);

const tabs = computed(() => [
    { key: 'queue', label: 'Needs review', count: props.counts.queue ?? 0 },
    ...props.statuses.map((s) => ({
        key: s.value,
        label: s.label,
        count: props.counts[s.value] ?? 0,
    })),
]);

const notes = reactive<Record<number, string>>({});
const rejecting = ref<number | null>(null);
const busy = ref<number | null>(null);
const errors = reactive<Record<number, string>>({});

function decide(item: QueueItem, decision: 'approved' | 'rejected'): void {
    if (decision === 'rejected' && rejecting.value !== item.id) {
        rejecting.value = item.id;

        return;
    }

    busy.value = item.id;
    delete errors[item.id];

    router.post(
        item.decide_url,
        { decision, note: notes[item.id] ?? null },
        {
            preserveScroll: true,
            onError: (e) => (errors[item.id] = Object.values(e)[0] ?? ''),
            onSuccess: () => {
                rejecting.value = null;
                delete notes[item.id];
            },
            onFinish: () => (busy.value = null),
        },
    );
}
</script>

<template>
    <Head title="Chat moderation" />

    <PageHeader
        eyebrow="Live chat"
        title="Moderation"
        description="Messages the safety checks flagged or held. Flagged messages were delivered; pending and blocked ones wait for your decision. Nothing is deleted."
    >
        <template v-if="securityUrl" #actions>
            <Button variant="outline" as-child>
                <Link :href="securityUrl"
                    ><AppIcon name="shield" :size="18" /> Security log</Link
                >
            </Button>
        </template>
    </PageHeader>

    <PanelTabs v-model="filters.status" label="Status" :tabs="tabs" />

    <PanelCard flush class="mb-4">
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Search message text…"
        />
    </PanelCard>

    <EmptyState
        v-if="!messages.data.length"
        icon="verified_user"
        title="Nothing to review"
        description="New flagged or held messages will appear here."
    />

    <div class="flex flex-col gap-3">
        <PanelCard v-for="m in messages.data" :key="m.id">
            <div class="flex flex-wrap items-start gap-x-3 gap-y-1.5">
                <div class="min-w-0 flex-1">
                    <p class="text-[13px] font-semibold text-foreground">
                        {{ m.sender_name ?? 'Unknown sender' }}
                        <span class="font-normal text-muted-foreground"
                            >· {{ m.conversation.kind_label }}
                            <template v-if="m.conversation.student_number"
                                >· {{ m.conversation.student_number }}</template
                            >
                            · {{ formatDateTime(m.created_at) }}</span
                        >
                    </p>
                    <div
                        v-if="m.reasons.length"
                        class="mt-1 flex flex-wrap gap-1"
                    >
                        <span
                            v-for="r in m.reasons"
                            :key="r"
                            class="rounded bg-warning-soft px-1.5 py-0.5 text-[10px] font-semibold text-warning"
                            >{{ r }}</span
                        >
                    </div>
                </div>
                <StatusBadge :label="m.status_label" :tone="m.status_tone" />
                <Link :href="m.conversation.url" class="tiny-action"
                    >Open conversation</Link
                >
            </div>

            <p
                class="mt-3 rounded-lg bg-muted px-3.5 py-2.5 text-[13px] break-words whitespace-pre-line"
            >
                {{ m.body ?? '(no text)' }}
            </p>
            <ul v-if="m.attachments.length" class="mt-2 flex flex-wrap gap-2">
                <li v-for="a in m.attachments" :key="a.id">
                    <a
                        :href="`${a.url}?download=1`"
                        class="inline-flex items-center gap-1 text-xs font-semibold underline"
                        ><AppIcon name="attach_file" :size="14" />
                        {{ a.name }}</a
                    >
                </li>
            </ul>

            <details v-if="m.history.length" class="mt-3 text-xs">
                <summary class="cursor-pointer text-muted-foreground">
                    History ({{ m.history.length }})
                </summary>
                <ol class="mt-2 flex flex-col gap-1.5 border-l pl-3">
                    <li v-for="h in m.history" :key="h.id">
                        <b>{{ h.to }}</b>
                        <template v-if="h.from"> (was {{ h.from }})</template>
                        · {{ h.by }} · {{ formatDateTime(h.at) }}
                        <span
                            v-if="h.note"
                            class="block whitespace-pre-line text-muted-foreground"
                            >{{ h.note }}</span
                        >
                    </li>
                </ol>
            </details>

            <div class="mt-3 flex flex-col gap-2">
                <textarea
                    v-if="rejecting === m.id"
                    v-model="notes[m.id]"
                    rows="2"
                    maxlength="500"
                    :aria-label="`Reason for rejecting the message from ${m.sender_name}`"
                    placeholder="Reason for rejecting (kept in the history)"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-[13px] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <p v-if="errors[m.id]" class="text-xs text-danger" role="alert">
                    {{ errors[m.id] }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="m.status !== 'approved'"
                        size="sm"
                        :disabled="busy === m.id"
                        @click="decide(m, 'approved')"
                        ><AppIcon name="check" :size="16" />
                        {{
                            ['pending', 'blocked', 'rejected'].includes(
                                m.status,
                            )
                                ? 'Approve and deliver'
                                : 'Approve'
                        }}</Button
                    >
                    <Button
                        v-if="m.status !== 'rejected'"
                        size="sm"
                        variant="outline"
                        :disabled="busy === m.id"
                        @click="decide(m, 'rejected')"
                        ><AppIcon name="block" :size="16" />
                        {{
                            rejecting === m.id ? 'Confirm reject' : 'Reject'
                        }}</Button
                    >
                    <Button
                        v-if="rejecting === m.id"
                        size="sm"
                        variant="ghost"
                        @click="rejecting = null"
                        >Cancel</Button
                    >
                </div>
                <p
                    v-if="m.reviewed_by"
                    class="text-[11px] text-muted-foreground"
                >
                    Last reviewed by {{ m.reviewed_by }} ·
                    {{ formatDateTime(m.reviewed_at) }}
                </p>
            </div>
        </PanelCard>
    </div>

    <TablePagination
        v-if="messages.data.length"
        :meta="messages"
        class="mt-3"
    />
</template>
