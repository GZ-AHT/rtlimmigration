<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { getInitials } from '@/composables/useInitials';
import { formatDateTime, relativeTime } from '@/lib/format';
import {
    destroy,
    index,
    reply as replyRoute,
    update,
} from '@/routes/admin/tickets';
import type { ActivityEntry, Option, TicketRow } from '@/types';

type Reply = {
    id: number;
    body: string;
    internal: boolean;
    emailed: boolean;
    author: string | null;
    created_at: string | null;
};
type TicketDetail = TicketRow & {
    description: string;
    category_id: number | null;
    assigned_to: number | null;
    resolved_at: string | null;
    closed_at: string | null;
    replies: Reply[];
};

const props = defineProps<{
    ticket: TicketDetail;
    history: ActivityEntry[];
    options: {
        statuses: Option[];
        priorities: Option[];
        categories: Option[];
        assignees: Option[];
    };
    can: { update: boolean; assign: boolean; delete: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tickets', href: index() }] },
});

const reply = useForm({ body: '', internal: false, status: '' });
const details = ref({
    status: props.ticket.status,
    priority: props.ticket.priority,
    category_id: props.ticket.category_id
        ? String(props.ticket.category_id)
        : '',
    assigned_to: props.ticket.assigned_to
        ? String(props.ticket.assigned_to)
        : '',
});

function sendReply(): void {
    reply
        .transform((d) => ({ ...d, status: d.status || null }))
        .submit(replyRoute(props.ticket.id), {
            preserveScroll: true,
            onSuccess: () => reply.reset(),
        });
}

const deleting = ref(false);

function remove(): void {
    router.delete(destroy(props.ticket.id).url, {
        onError: (e) => toast.error(Object.values(e)[0] ?? 'Could not delete.'),
        onFinish: () => (deleting.value = false),
    });
}

function save(field: keyof typeof details.value): void {
    const value = details.value[field];
    router.patch(
        update(props.ticket.id).url,
        { [field]: value === '' ? null : value },
        {
            preserveScroll: true,
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Could not update ticket.'),
        },
    );
}
</script>

<template>
    <Head :title="`${ticket.ticket_number} · ${ticket.subject}`" />

    <PageHeader
        :eyebrow="ticket.ticket_number"
        :title="ticket.subject"
        :description="`From ${ticket.requester}${ticket.requester_email ? ` <${ticket.requester_email}>` : ''} · ${formatDateTime(ticket.created_at)}`"
    >
        <template #actions>
            <StatusBadge
                :label="ticket.priority_label"
                :tone="ticket.priority_tone"
                class="self-center"
            />
            <StatusBadge
                :label="ticket.status_label"
                :tone="ticket.status_tone"
                class="self-center"
            />
            <Button
                v-if="can.delete"
                variant="outline"
                aria-label="Delete ticket"
                @click="deleting = true"
            >
                <AppIcon name="delete" :size="18" />
            </Button>
        </template>
    </PageHeader>

    <ConfirmDialog
        :open="deleting"
        title="Delete this ticket?"
        description="The ticket and all its replies will be permanently removed."
        confirm-label="Delete ticket"
        destructive
        @update:open="(v) => !v && (deleting = false)"
        @confirm="remove"
    />

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(300px,1fr)]">
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard>
                <div class="flex gap-3">
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-full bg-muted text-xs font-bold"
                        >{{ getInitials(ticket.requester) }}</span
                    >
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px]">
                            <b>{{ ticket.requester }}</b>
                            <span class="text-[11px] text-muted-foreground"
                                >· {{ relativeTime(ticket.created_at) }}</span
                            >
                        </p>
                        <p
                            class="mt-2 text-[13px] whitespace-pre-line text-secondary-foreground"
                        >
                            {{ ticket.description }}
                        </p>
                    </div>
                </div>
            </PanelCard>

            <PanelCard
                v-for="r in ticket.replies"
                :key="r.id"
                :class="r.internal ? 'border-warning/40 bg-warning-soft' : ''"
            >
                <div class="flex gap-3">
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-full text-xs font-bold"
                        :class="
                            r.internal
                                ? 'bg-card text-warning'
                                : 'bg-accent text-accent-foreground'
                        "
                        >{{ getInitials(r.author ?? 'S') }}</span
                    >
                    <div class="min-w-0 flex-1">
                        <p
                            class="flex flex-wrap items-center gap-1.5 text-[13px]"
                        >
                            <b>{{ r.author ?? 'Staff' }}</b>
                            <StatusBadge
                                v-if="r.internal"
                                label="Internal note"
                                tone="warning"
                            />
                            <span
                                v-else-if="r.emailed"
                                class="inline-flex items-center gap-1 text-[11px] text-muted-foreground"
                                ><AppIcon name="mail" :size="13" />
                                emailed</span
                            >
                            <span class="text-[11px] text-muted-foreground"
                                >· {{ relativeTime(r.created_at) }}</span
                            >
                        </p>
                        <p
                            class="mt-2 text-[13px] whitespace-pre-line text-secondary-foreground"
                        >
                            {{ r.body }}
                        </p>
                    </div>
                </div>
            </PanelCard>

            <PanelCard
                v-if="can.update"
                :title="reply.internal ? 'Add internal note' : 'Reply'"
                :description="
                    reply.internal
                        ? 'Visible to staff only. Never emailed.'
                        : ticket.requester_email
                          ? `Emailed to ${ticket.requester_email}.`
                          : 'This requester has no email address.'
                "
            >
                <form class="flex flex-col gap-3" @submit.prevent="sendReply">
                    <div
                        class="inline-flex w-fit rounded-lg border p-0.5 text-[13px]"
                        role="radiogroup"
                        aria-label="Reply type"
                    >
                        <button
                            type="button"
                            role="radio"
                            :aria-checked="!reply.internal"
                            class="rounded-md px-3 py-1.5"
                            :class="
                                !reply.internal
                                    ? 'bg-primary font-semibold text-primary-foreground'
                                    : 'text-muted-foreground'
                            "
                            @click="reply.internal = false"
                        >
                            Reply
                        </button>
                        <button
                            type="button"
                            role="radio"
                            :aria-checked="reply.internal"
                            class="rounded-md px-3 py-1.5"
                            :class="
                                reply.internal
                                    ? 'bg-warning font-semibold text-white'
                                    : 'text-muted-foreground'
                            "
                            @click="reply.internal = true"
                        >
                            Internal note
                        </button>
                    </div>
                    <TextArea
                        v-model="reply.body"
                        :rows="5"
                        :invalid="Boolean(reply.errors.body)"
                        :placeholder="
                            reply.internal
                                ? 'Only staff will see this…'
                                : 'Write your reply…'
                        "
                        aria-label="Message"
                    />
                    <p
                        v-if="reply.errors.body"
                        class="text-xs text-danger"
                        role="alert"
                    >
                        {{ reply.errors.body }}
                    </p>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <SelectInput
                            v-model="reply.status"
                            :options="options.statuses"
                            placeholder="Keep status"
                            label="Set status"
                            compact
                        />
                        <Button
                            type="submit"
                            :disabled="reply.processing || !reply.body.trim()"
                        >
                            <Spinner v-if="reply.processing" />
                            {{ reply.internal ? 'Add note' : 'Send reply' }}
                        </Button>
                    </div>
                </form>
            </PanelCard>
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard v-if="can.update" title="Details">
                <div class="flex flex-col gap-3 text-[13px]">
                    <label class="flex flex-col gap-1.5"
                        ><span
                            class="text-xs font-bold text-secondary-foreground"
                            >Status</span
                        >
                        <SelectInput
                            v-model="details.status"
                            :options="options.statuses"
                            @update:model-value="save('status')"
                        />
                    </label>
                    <label class="flex flex-col gap-1.5"
                        ><span
                            class="text-xs font-bold text-secondary-foreground"
                            >Priority</span
                        >
                        <SelectInput
                            v-model="details.priority"
                            :options="options.priorities"
                            @update:model-value="save('priority')"
                        />
                    </label>
                    <label class="flex flex-col gap-1.5"
                        ><span
                            class="text-xs font-bold text-secondary-foreground"
                            >Category</span
                        >
                        <SelectInput
                            v-model="details.category_id"
                            :options="options.categories"
                            placeholder="Uncategorised"
                            @update:model-value="save('category_id')"
                        />
                    </label>
                    <label v-if="can.assign" class="flex flex-col gap-1.5"
                        ><span
                            class="text-xs font-bold text-secondary-foreground"
                            >Assignee</span
                        >
                        <SelectInput
                            v-model="details.assigned_to"
                            :options="options.assignees"
                            placeholder="Unassigned"
                            @update:model-value="save('assigned_to')"
                        />
                    </label>
                </div>
            </PanelCard>

            <PanelCard title="History">
                <EmptyState
                    v-if="!history.length"
                    icon="history"
                    title="No changes yet"
                />
                <ol v-else class="relative ml-1.5 border-l">
                    <li
                        v-for="h in history"
                        :key="h.id"
                        class="relative pb-3 pl-5 last:pb-0"
                    >
                        <span
                            class="absolute top-1 -left-[5px] size-2.5 rounded-full border-2 border-card bg-primary"
                        />
                        <p class="text-[13px]">{{ h.summary }}</p>
                        <p class="text-[11px] text-muted-foreground">
                            {{ h.causer ?? 'System' }} ·
                            {{ relativeTime(h.created_at) }}
                        </p>
                    </li>
                </ol>
            </PanelCard>
        </div>
    </div>
</template>
