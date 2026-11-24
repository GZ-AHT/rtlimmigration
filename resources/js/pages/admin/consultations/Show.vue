<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';
import {
    approve,
    cancel,
    complete,
    index,
    meeting,
    reject,
    reschedule,
    show,
} from '@/routes/admin/consultations';
import { send } from '@/routes/admin/consultations/meeting';
import { store as storeNote } from '@/routes/admin/consultations/notes';
import type { Tone } from '@/types';

type Event = {
    id: number;
    type: string;
    note: string | null;
    data: Record<string, unknown> | null;
    by: string | null;
    at: string | null;
};
type Booking = {
    id: number;
    reference: string;
    name: string;
    email: string;
    phone: string;
    subject: string;
    message: string | null;
    status: string;
    status_label: string;
    status_tone: Tone;
    starts_at: string;
    duration_minutes: number;
    timezone: string;
    customer_time: string;
    meeting_url: string | null;
    meeting_provider: string | null;
    meeting_sent_at: string | null;
    has_user: boolean;
    has_lead: boolean;
    events: Event[];
};

const props = defineProps<{
    booking: Booking;
    suggested: string[];
    businessTimezone: string;
    providers: Record<string, string>;
    can: { manage: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Video consultations', href: index() }] },
});

const errors = ref<Record<string, string>>({});
const busy = ref(false);
const active = computed(() =>
    ['pending', 'confirmed'].includes(props.booking.status),
);
const confirmed = computed(() => props.booking.status === 'confirmed');

const providerOptions = Object.entries(props.providers).map(
    ([value, label]) => ({ value, label }),
);
const link = reactive({
    provider: props.booking.meeting_provider ?? 'google_meet',
    url: props.booking.meeting_url ?? '',
});
const linkExample = 'meet.google.com/abc-defg-hij';
const reason = ref('');
const note = ref('');
const move = reactive({ slot: '', custom: '', note: '' });

const slotOptions = computed(() =>
    props.suggested.map((s) => ({
        value: s,
        label: new Intl.DateTimeFormat('en', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date(s)),
    })),
);

function post(
    url: string,
    data: Record<string, unknown> = {},
    method: 'post' | 'put' = 'post',
    after?: () => void,
): void {
    router[method](url, data as Record<string, never>, {
        preserveScroll: true,
        onStart: () => {
            busy.value = true;
            errors.value = {};
        },
        onFinish: () => (busy.value = false),
        onSuccess: after,
        onError: (e) => (errors.value = e),
    });
}

const id = props.booking.id;
const approveNow = () =>
    post(
        approve(id).url,
        link.url
            ? { meeting_provider: link.provider, meeting_url: link.url }
            : {},
    );
const saveLink = (sendIt: boolean) =>
    post(
        meeting(id).url,
        {
            meeting_provider: link.provider,
            meeting_url: link.url,
            send: sendIt,
        },
        'put',
    );
const resend = () => post(send(id).url);
const rejectNow = () => post(reject(id).url, { reason: reason.value });
const cancelNow = () => {
    if (window.confirm('Cancel this appointment and tell the customer?')) {
        post(cancel(id).url, { reason: reason.value });
    }
};
const completeNow = () => post(complete(id).url);
const addNote = () =>
    post(
        storeNote(id).url,
        { note: note.value },
        'post',
        () => (note.value = ''),
    );
const moveNow = () => {
    const when = move.custom ? new Date(move.custom).toISOString() : move.slot;
    post(
        reschedule(id).url,
        { starts_at: when, note: move.note },
        'post',
        () => {
            move.slot = '';
            move.custom = '';
            move.note = '';
        },
    );
};

const labels: Record<string, string> = {
    created: 'Booking requested',
    approved: 'Approved',
    rejected: 'Rejected',
    cancelled: 'Cancelled',
    completed: 'Marked completed',
    rescheduled: 'Rescheduled',
    meeting_link: 'Meeting link saved',
    meeting_sent: 'Meeting link emailed',
    meeting_resent: 'Meeting invitation sent again',
    reminder_sent: 'Reminder emailed',
    reschedule_requested: 'Customer asked for another time',
    note: 'Internal note',
};
</script>

<template>
    <Head :title="`Consultation ${booking.reference}`" />

    <PageHeader
        eyebrow="Video consultation"
        :title="booking.reference"
        :description="`${booking.name} · ${booking.subject}`"
    >
        <template #actions>
            <StatusBadge
                :label="booking.status_label"
                :tone="booking.status_tone"
                class="self-center"
            />
            <Button as-child variant="outline"
                ><Link :href="index()">All bookings</Link></Button
            >
        </template>
    </PageHeader>

    <div class="grid gap-4 xl:grid-cols-[1fr_380px]">
        <div class="flex flex-col gap-4">
            <PanelCard title="Customer and appointment">
                <dl
                    class="grid grid-cols-[130px_1fr] gap-x-3 gap-y-2.5 text-[13px]"
                >
                    <dt class="text-muted-foreground">Name</dt>
                    <dd class="font-semibold">{{ booking.name }}</dd>
                    <dt class="text-muted-foreground">Email (verified)</dt>
                    <dd class="break-all">{{ booking.email }}</dd>
                    <dt class="text-muted-foreground">Phone</dt>
                    <dd>{{ booking.phone }}</dd>
                    <dt class="text-muted-foreground">Existing record</dt>
                    <dd>
                        {{
                            booking.has_user
                                ? 'Has an account'
                                : booking.has_lead
                                  ? 'Known lead'
                                  : 'New contact'
                        }}
                    </dd>
                    <dt class="text-muted-foreground">Subject</dt>
                    <dd>{{ booking.subject }}</dd>
                    <dt class="text-muted-foreground">Message</dt>
                    <dd class="whitespace-pre-line">
                        {{ booking.message ?? '—' }}
                    </dd>
                    <dt class="text-muted-foreground">When (your time)</dt>
                    <dd class="font-semibold">
                        {{ formatDateTime(booking.starts_at) }} ·
                        {{ booking.duration_minutes }} min
                    </dd>
                    <dt class="text-muted-foreground">Customer's time</dt>
                    <dd>{{ booking.customer_time }}</dd>
                    <dt class="text-muted-foreground">Type</dt>
                    <dd>Video Consultation</dd>
                </dl>
            </PanelCard>

            <PanelCard
                v-if="can.manage && active"
                title="Meeting link"
                description="Links are entered by hand. Nothing here creates a Google Meet or Zoom meeting for you."
            >
                <div class="flex flex-col gap-3">
                    <div class="grid gap-3 sm:grid-cols-[170px_1fr]">
                        <FormField
                            label="Service"
                            :error="errors.meeting_provider"
                        >
                            <template #default="{ id: fid }"
                                ><SelectInput
                                    :id="fid"
                                    v-model="link.provider"
                                    :options="providerOptions"
                            /></template>
                        </FormField>
                        <FormField
                            label="Meeting link (https)"
                            :error="errors.meeting_url"
                        >
                            <template #default="{ id: fid }"
                                ><Input
                                    :id="fid"
                                    v-model="link.url"
                                    type="url"
                                    :placeholder="linkExample"
                            /></template>
                        </FormField>
                    </div>
                    <p
                        v-if="errors.status"
                        class="text-xs text-danger"
                        role="alert"
                    >
                        {{ errors.status }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="confirmed"
                            :disabled="busy || !link.url"
                            @click="saveLink(true)"
                            >Save and email to customer</Button
                        >
                        <Button
                            variant="outline"
                            :disabled="busy || !link.url"
                            @click="saveLink(false)"
                            >Save only</Button
                        >
                        <Button
                            v-if="confirmed && booking.meeting_url"
                            variant="outline"
                            :disabled="busy"
                            @click="resend"
                        >
                            <AppIcon name="forward_to_inbox" :size="16" />
                            Resend invitation
                        </Button>
                        <span
                            v-if="booking.meeting_sent_at"
                            class="text-[12px] text-muted-foreground"
                            >Last emailed
                            {{ formatDateTime(booking.meeting_sent_at) }}</span
                        >
                    </div>
                    <p
                        v-if="!confirmed"
                        class="text-[12px] text-muted-foreground"
                    >
                        Approve the booking first to email the link.
                    </p>
                </div>
            </PanelCard>

            <PanelCard title="History">
                <ol class="flex flex-col gap-3">
                    <li
                        v-for="e in booking.events"
                        :key="e.id"
                        class="flex gap-3 text-[13px]"
                    >
                        <AppIcon
                            :name="
                                e.type === 'note' ? 'sticky_note_2' : 'history'
                            "
                            :size="18"
                            class="mt-0.5 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0">
                            <p class="font-semibold">
                                {{ labels[e.type] ?? e.type
                                }}<span
                                    v-if="e.by"
                                    class="font-normal text-muted-foreground"
                                >
                                    · {{ e.by }}</span
                                >
                            </p>
                            <p
                                v-if="e.note"
                                class="whitespace-pre-line text-muted-foreground"
                            >
                                {{ e.note }}
                            </p>
                            <p
                                v-if="e.type === 'rescheduled' && e.data"
                                class="text-muted-foreground"
                            >
                                {{ e.data.from }} → {{ e.data.to }}
                            </p>
                            <p
                                v-if="e.type === 'meeting_link' && e.data"
                                class="break-all text-muted-foreground"
                            >
                                {{ e.data.url }}
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                {{ formatDateTime(e.at) }}
                            </p>
                        </div>
                    </li>
                </ol>
            </PanelCard>
        </div>

        <div v-if="can.manage" class="flex flex-col gap-4">
            <PanelCard
                v-if="booking.status === 'pending'"
                title="Review request"
            >
                <div class="flex flex-col gap-3">
                    <p class="text-[12px] text-muted-foreground">
                        Add the meeting link now to approve and email it in one
                        step, or approve first.
                    </p>
                    <Button :disabled="busy" @click="approveNow"
                        ><AppIcon name="check_circle" :size="16" />
                        Approve</Button
                    >
                    <FormField
                        label="Reason (sent to the customer)"
                        :error="errors.reason"
                    >
                        <template #default="{ id: fid }"
                            ><TextArea :id="fid" v-model="reason" :rows="2"
                        /></template>
                    </FormField>
                    <Button
                        variant="outline"
                        :disabled="busy"
                        @click="rejectNow"
                        ><AppIcon name="cancel" :size="16" /> Reject
                        request</Button
                    >
                </div>
            </PanelCard>

            <PanelCard v-if="active" title="Reschedule">
                <div class="flex flex-col gap-3">
                    <FormField
                        label="Free times (your timezone)"
                        :error="errors.starts_at"
                    >
                        <template #default="{ id: fid }"
                            ><SelectInput
                                :id="fid"
                                v-model="move.slot"
                                :options="slotOptions"
                                placeholder="Choose a free time"
                        /></template>
                    </FormField>
                    <FormField
                        label="Or any date and time"
                        hint="Must not clash with another appointment."
                    >
                        <template #default="{ id: fid }"
                            ><Input
                                :id="fid"
                                v-model="move.custom"
                                type="datetime-local"
                        /></template>
                    </FormField>
                    <FormField label="Note for the history">
                        <template #default="{ id: fid }"
                            ><Input
                                :id="fid"
                                v-model="move.note"
                                maxlength="500"
                        /></template>
                    </FormField>
                    <Button
                        variant="outline"
                        :disabled="busy || (!move.slot && !move.custom)"
                        @click="moveNow"
                        >Reschedule and email customer</Button
                    >
                </div>
            </PanelCard>

            <PanelCard v-if="active" title="Close out">
                <div class="flex flex-col gap-3">
                    <Button
                        v-if="confirmed"
                        variant="outline"
                        :disabled="busy"
                        @click="completeNow"
                        ><AppIcon name="task_alt" :size="16" /> Mark as
                        completed</Button
                    >
                    <FormField
                        v-if="booking.status !== 'pending'"
                        label="Reason (sent to the customer)"
                    >
                        <template #default="{ id: fid }"
                            ><TextArea :id="fid" v-model="reason" :rows="2"
                        /></template>
                    </FormField>
                    <Button
                        v-if="booking.status !== 'pending'"
                        variant="destructive"
                        :disabled="busy"
                        @click="cancelNow"
                        >Cancel appointment</Button
                    >
                </div>
            </PanelCard>

            <PanelCard
                title="Internal notes"
                description="Only staff see these."
            >
                <form class="flex flex-col gap-3" @submit.prevent="addNote">
                    <FormField label="Add a note" :error="errors.note">
                        <template #default="{ id: fid }"
                            ><TextArea :id="fid" v-model="note" :rows="3"
                        /></template>
                    </FormField>
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="busy || !note.trim()"
                        >Add note</Button
                    >
                </form>
            </PanelCard>
        </div>
    </div>
</template>
