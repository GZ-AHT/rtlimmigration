<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, relativeTime } from '@/lib/format';
import type { CounselorOption, Option, Tone } from '@/types';

type LeadDetail = {
    id: number;
    lead_number: string;
    name: string;
    email: string | null;
    phone: string | null;
    country: string | null;
    service: string | null;
    source: string;
    status: string;
    status_label: string;
    status_tone: Tone;
    counselor: string | null;
    counselor_id: number | null;
    contact_preference_label: string | null;
    message: string | null;
    assigned_at: string | null;
    last_contacted_at: string | null;
    consent_at: string | null;
    converted_at: string | null;
    created_at: string | null;
    creator: string | null;
    student: { id: number; number: string } | null;
    duplicate_of: {
        id: number;
        number: string;
        name: string;
        visible: boolean;
    } | null;
    can: {
        update: boolean;
        assign: boolean;
        transfer: boolean;
        convert: boolean;
        delete: boolean;
    };
};

type TimelineItem = {
    id: string;
    type: string;
    icon: string;
    title: string;
    body: string | null;
    by: string | null;
    at: string | null;
};
type FollowUp = {
    id: number;
    due_at: string;
    overdue: boolean;
    note: string | null;
    owner: string | null;
    complete_url: string;
};

const props = defineProps<{
    lead: LeadDetail;
    timeline: TimelineItem[];
    followUps: FollowUp[];
    counselors: CounselorOption[];
    statuses: Option[];
    channels: Option[];
    urls: Record<string, string | null>;
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Leads', href: props.urls.index ?? '' }],
});

const busy = ref(false);
const confirm = ref<null | 'delete' | 'convert'>(null);
const sendInvitation = ref(true);

function onError(errors: Record<string, string>): void {
    const first = Object.values(errors)[0];

    if (first) {
        toast.error(first);
    }
}

function visit(
    url: string | null,
    method: 'post' | 'patch' | 'delete',
    data: Record<string, unknown> = {},
    after?: () => void,
): void {
    if (!url) {
        return;
    }

    router.visit(url, {
        method,
        data: data as Record<string, string>,
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onError,
        onSuccess: () => after?.(),
        onFinish: () => {
            busy.value = false;
            confirm.value = null;
        },
    });
}

/* Composer: note / contact / follow-up */
const composerTab = ref<'note' | 'contact' | 'followup'>('note');
const note = useForm({ body: '' });
const contact = useForm({ channel: 'phone', summary: '', contacted_at: '' });
const followUp = useForm({ due_at: '', note: '' });

function submitNote(): void {
    note.post(props.urls.notes!, {
        preserveScroll: true,
        onSuccess: () => note.reset(),
    });
}

function submitContact(): void {
    contact
        .transform((d) => ({ ...d, contacted_at: d.contacted_at || null }))
        .post(props.urls.contacts!, {
            preserveScroll: true,
            onSuccess: () => contact.reset(),
        });
}

function submitFollowUp(): void {
    followUp.post(props.urls.followUps!, {
        preserveScroll: true,
        onSuccess: () => followUp.reset(),
    });
}

/* Status */
const status = ref(props.lead.status);
const statusChoices = computed(() => props.statuses);

/* Assignment */
const counselorId = ref(
    props.lead.counselor_id ? String(props.lead.counselor_id) : '',
);
const assignNote = ref('');
const canChangeAssignment = computed(
    () =>
        (props.lead.counselor_id
            ? props.lead.can.transfer
            : props.lead.can.assign) && props.urls.assign,
);
const counselorOptions = computed<Option[]>(() =>
    props.counselors.map((c) => ({
        value: c.value,
        label: `${c.label} · ${c.open_leads} open${c.capacity ? ` / ${c.capacity}` : ''}${c.availability !== 'available' ? ` · ${c.availability_label}` : ''}`,
    })),
);
const selectedCounselor = computed(() =>
    props.counselors.find((c) => c.value === counselorId.value),
);

/* Follow-up completion */
const completing = ref<FollowUp | null>(null);
const outcome = ref('');

const whatsappUrl = computed(() => {
    const digits = (props.lead.phone ?? '').replace(/\D+/g, '');

    return digits.length >= 7 ? `https://wa.me/${digits}` : null;
});

const minDue = computed(() =>
    new Date(Date.now() + 5 * 60_000).toISOString().slice(0, 16),
);
const maxContacted = computed(() => new Date().toISOString().slice(0, 16));
</script>

<template>
    <Head :title="`${lead.name} · ${lead.lead_number}`" />

    <PageHeader
        :eyebrow="lead.lead_number"
        :title="lead.name"
        :description="`${lead.source} · received ${formatDateTime(lead.created_at)}`"
    >
        <template #actions>
            <StatusBadge
                :label="lead.status_label"
                :tone="lead.status_tone"
                class="self-center"
            />
            <Button
                v-if="lead.can.update && urls.edit"
                variant="outline"
                as-child
            >
                <Link :href="urls.edit"
                    ><AppIcon name="edit" :size="18" /> Edit</Link
                >
            </Button>
            <Button v-if="lead.can.convert" @click="confirm = 'convert'">
                <AppIcon name="person_add" :size="18" /> Convert to student
            </Button>
            <Button
                v-if="lead.can.delete && urls.destroy"
                variant="outline"
                aria-label="Delete lead"
                @click="confirm = 'delete'"
            >
                <AppIcon name="delete" :size="18" />
            </Button>
        </template>
    </PageHeader>

    <div
        v-if="lead.duplicate_of"
        class="mb-4 flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-[13px] text-warning"
    >
        <AppIcon name="content_copy" :size="18" />
        <p>
            This looks like a duplicate of
            <Link
                v-if="lead.duplicate_of.visible && urls.duplicate"
                :href="urls.duplicate"
                class="font-bold underline"
                >{{ lead.duplicate_of.number }} ·
                {{ lead.duplicate_of.name }}</Link
            >
            <b v-else>{{ lead.duplicate_of.number }}</b>
            (same email or phone). Check before contacting them again.
        </p>
    </div>
    <div
        v-if="lead.student"
        class="mb-4 flex items-start gap-3 rounded-xl border border-success/30 bg-success-soft p-4 text-[13px] text-success"
    >
        <AppIcon name="task_alt" :size="18" />
        <p>
            Converted {{ relativeTime(lead.converted_at) }} to student
            <Link
                v-if="urls.student"
                :href="urls.student"
                class="font-bold underline"
                >{{ lead.student.number }}</Link
            >
            <b v-else>{{ lead.student.number }}</b
            >.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.7fr)_minmax(300px,1fr)]">
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard v-if="lead.can.update">
                <PanelTabs
                    v-model="composerTab"
                    label="Add to lead"
                    :tabs="[
                        { key: 'note', label: 'Add note' },
                        { key: 'contact', label: 'Log contact' },
                        { key: 'followup', label: 'Schedule follow-up' },
                    ]"
                />
                <form
                    v-if="composerTab === 'note'"
                    class="flex flex-col gap-3"
                    @submit.prevent="submitNote"
                >
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Note"
                        :error="note.errors.body"
                    >
                        <TextArea
                            :id="id"
                            v-model="note.body"
                            :rows="3"
                            placeholder="Anything the team should know about this lead…"
                            :invalid="invalid"
                        />
                    </FormField>
                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="note.processing || !note.body.trim()"
                            ><Spinner v-if="note.processing" /> Save
                            note</Button
                        >
                    </div>
                </form>
                <form
                    v-else-if="composerTab === 'contact'"
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="submitContact"
                >
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Channel"
                        required
                        :error="contact.errors.channel"
                    >
                        <SelectInput
                            :id="id"
                            v-model="contact.channel"
                            :options="channels"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid, describedBy }"
                        label="When"
                        hint="Leave empty for now."
                        :error="contact.errors.contacted_at"
                    >
                        <Input
                            :id="id"
                            v-model="contact.contacted_at"
                            type="datetime-local"
                            :max="maxContacted"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </FormField>
                    <div class="sm:col-span-2">
                        <FormField
                            v-slot="{ id, invalid }"
                            label="What was discussed"
                            required
                            :error="contact.errors.summary"
                        >
                            <TextArea
                                :id="id"
                                v-model="contact.summary"
                                :rows="3"
                                placeholder="Explained the student visa process, they will send transcripts…"
                                :invalid="invalid"
                            />
                        </FormField>
                    </div>
                    <div class="flex justify-end sm:col-span-2">
                        <Button
                            type="submit"
                            :disabled="
                                contact.processing || !contact.summary.trim()
                            "
                            ><Spinner v-if="contact.processing" /> Record
                            contact</Button
                        >
                    </div>
                </form>
                <form
                    v-else
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="submitFollowUp"
                >
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Follow up on"
                        required
                        :error="followUp.errors.due_at"
                    >
                        <Input
                            :id="id"
                            v-model="followUp.due_at"
                            type="datetime-local"
                            :min="minDue"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Reminder note"
                        :error="followUp.errors.note"
                    >
                        <Input
                            :id="id"
                            v-model="followUp.note"
                            placeholder="Call back about IELTS score"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <p class="text-[11px] text-muted-foreground sm:col-span-2">
                        {{
                            lead.counselor
                                ? `${lead.counselor} gets a reminder when it's due.`
                                : "You get a reminder when it's due."
                        }}
                    </p>
                    <div class="flex justify-end sm:col-span-2">
                        <Button
                            type="submit"
                            :disabled="followUp.processing || !followUp.due_at"
                            ><Spinner v-if="followUp.processing" />
                            Schedule</Button
                        >
                    </div>
                </form>
            </PanelCard>

            <PanelCard
                title="Timeline"
                description="Notes, contact history, assignments and status changes."
            >
                <EmptyState
                    v-if="!timeline.length"
                    icon="history"
                    title="Nothing yet"
                />
                <ol v-else class="relative ml-2 border-l">
                    <li
                        v-for="item in timeline"
                        :key="item.id"
                        class="relative pb-5 pl-6 last:pb-0"
                    >
                        <span
                            class="absolute top-0 -left-[13px] grid size-[26px] place-items-center rounded-full border bg-card text-primary"
                        >
                            <AppIcon :name="item.icon" :size="14" />
                        </span>
                        <p class="text-[13px] font-semibold">
                            {{ item.title }}
                        </p>
                        <p
                            v-if="item.body"
                            class="mt-1 text-[13px] whitespace-pre-line text-secondary-foreground"
                        >
                            {{ item.body }}
                        </p>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            {{ item.by ?? 'System' }} ·
                            <time
                                :datetime="item.at ?? undefined"
                                :title="formatDateTime(item.at)"
                                >{{ relativeTime(item.at) }}</time
                            >
                        </p>
                    </li>
                </ol>
            </PanelCard>
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard title="Contact">
                <dl class="grid gap-3 text-[13px]">
                    <div
                        v-if="lead.email"
                        class="flex items-center justify-between gap-3"
                    >
                        <dt class="text-muted-foreground">Email</dt>
                        <dd class="truncate">
                            <a
                                :href="`mailto:${lead.email}`"
                                class="font-medium hover:text-primary"
                                >{{ lead.email }}</a
                            >
                        </dd>
                    </div>
                    <div
                        v-if="lead.phone"
                        class="flex items-center justify-between gap-3"
                    >
                        <dt class="text-muted-foreground">Phone</dt>
                        <dd class="flex items-center gap-2">
                            <a
                                :href="`tel:${lead.phone}`"
                                class="font-medium hover:text-primary"
                                >{{ lead.phone }}</a
                            >
                            <a
                                v-if="whatsappUrl"
                                :href="whatsappUrl"
                                target="_blank"
                                rel="noopener"
                                class="tiny-action"
                                aria-label="Open in WhatsApp"
                                >WhatsApp</a
                            >
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Interested in</dt>
                        <dd class="font-medium">{{ lead.service ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Destination</dt>
                        <dd class="font-medium">
                            {{ lead.country ?? 'Not decided' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Prefers</dt>
                        <dd class="font-medium">
                            {{
                                lead.contact_preference_label ?? 'No preference'
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Last contacted</dt>
                        <dd class="font-medium">
                            {{
                                lead.last_contacted_at
                                    ? relativeTime(lead.last_contacted_at)
                                    : 'Not yet'
                            }}
                        </dd>
                    </div>
                    <div
                        v-if="lead.consent_at"
                        class="flex justify-between gap-3"
                    >
                        <dt class="text-muted-foreground">Consent</dt>
                        <dd class="font-medium">
                            Given {{ formatDateTime(lead.consent_at) }}
                        </dd>
                    </div>
                    <div v-if="lead.creator" class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Added by</dt>
                        <dd class="font-medium">{{ lead.creator }}</dd>
                    </div>
                </dl>
                <div
                    v-if="lead.message"
                    class="mt-4 rounded-lg bg-muted p-3 text-[13px] whitespace-pre-line"
                >
                    {{ lead.message }}
                </div>
            </PanelCard>

            <PanelCard v-if="lead.can.update" title="Status">
                <form
                    class="flex gap-2"
                    @submit.prevent="visit(urls.status, 'patch', { status })"
                >
                    <SelectInput
                        v-model="status"
                        :options="statusChoices"
                        label="Lead status"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="busy || status === lead.status"
                        >Save</Button
                    >
                </form>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    "Assigned" and "Converted" are set automatically.
                </p>
            </PanelCard>

            <PanelCard title="Counselor">
                <p class="flex items-center gap-2 text-[13px]">
                    <AppIcon
                        name="support_agent"
                        :size="18"
                        class="text-muted-foreground"
                    />
                    <b v-if="lead.counselor">{{ lead.counselor }}</b>
                    <span v-else class="text-warning">Not assigned yet</span>
                    <span
                        v-if="lead.assigned_at"
                        class="text-[11px] text-muted-foreground"
                        >· {{ relativeTime(lead.assigned_at) }}</span
                    >
                </p>
                <form
                    v-if="canChangeAssignment"
                    class="mt-3 flex flex-col gap-2"
                    @submit.prevent="
                        visit(
                            urls.assign,
                            'post',
                            {
                                counselor_id: counselorId || null,
                                note: assignNote || null,
                            },
                            () => (assignNote = ''),
                        )
                    "
                >
                    <SelectInput
                        v-model="counselorId"
                        :options="counselorOptions"
                        placeholder="Unassigned"
                        label="Counselor"
                    />
                    <p
                        v-if="selectedCounselor?.at_capacity"
                        class="text-[11px] text-warning"
                    >
                        {{ selectedCounselor.label }} is at their lead limit.
                    </p>
                    <p
                        v-else-if="
                            selectedCounselor &&
                            selectedCounselor.availability !== 'available'
                        "
                        class="text-[11px] text-warning"
                    >
                        {{ selectedCounselor.label }} is
                        {{
                            selectedCounselor.availability_label.toLowerCase()
                        }}.
                    </p>
                    <Input
                        v-model="assignNote"
                        placeholder="Note for the counselor (optional)"
                        aria-label="Assignment note"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="
                            busy ||
                            counselorId ===
                                (lead.counselor_id
                                    ? String(lead.counselor_id)
                                    : '')
                        "
                    >
                        {{ lead.counselor_id ? 'Transfer' : 'Assign' }}
                    </Button>
                </form>
            </PanelCard>

            <PanelCard title="Open follow-ups">
                <EmptyState
                    v-if="!followUps.length"
                    icon="event_available"
                    title="No follow-ups scheduled"
                />
                <ul v-else class="flex flex-col gap-2">
                    <li
                        v-for="f in followUps"
                        :key="f.id"
                        class="flex items-start gap-2.5 rounded-lg border p-3"
                        :class="{
                            'border-danger/40 bg-danger-soft': f.overdue,
                        }"
                    >
                        <AppIcon
                            name="event"
                            :size="18"
                            :class="
                                f.overdue
                                    ? 'text-danger'
                                    : 'text-muted-foreground'
                            "
                        />
                        <div class="min-w-0 flex-1 text-[13px]">
                            <p
                                class="font-semibold"
                                :class="{ 'text-danger': f.overdue }"
                            >
                                {{ formatDateTime(f.due_at)
                                }}{{ f.overdue ? ' · overdue' : '' }}
                            </p>
                            <p
                                v-if="f.note"
                                class="text-xs text-secondary-foreground"
                            >
                                {{ f.note }}
                            </p>
                            <p
                                v-if="f.owner"
                                class="text-[11px] text-muted-foreground"
                            >
                                {{ f.owner }}
                            </p>
                        </div>
                        <button
                            v-if="lead.can.update"
                            type="button"
                            class="tiny-action"
                            @click="
                                completing = f;
                                outcome = '';
                            "
                        >
                            Done
                        </button>
                    </li>
                </ul>
            </PanelCard>
        </div>
    </div>

    <ConfirmDialog
        :open="confirm === 'convert'"
        title="Convert to student?"
        :description="
            lead.email
                ? `Creates a student account for ${lead.email}${lead.counselor ? `, assigned to ${lead.counselor}` : ''}, and marks this lead as converted.`
                : 'This lead needs an email address first.'
        "
        confirm-label="Convert"
        :loading="busy"
        @update:open="(v) => !v && (confirm = null)"
        @confirm="
            visit(urls.convert, 'post', { send_invitation: sendInvitation })
        "
    >
        <label
            class="flex items-start gap-2.5 rounded-lg border bg-muted p-3 text-[13px]"
        >
            <Checkbox
                :model-value="sendInvitation"
                class="mt-0.5"
                @update:model-value="(v) => (sendInvitation = v === true)"
            />
            <span>Email them an invitation to set a password and sign in</span>
        </label>
    </ConfirmDialog>
    <ConfirmDialog
        :open="confirm === 'delete'"
        title="Delete this lead?"
        description="It will be removed from lead lists. Its history is kept for auditing."
        confirm-label="Delete lead"
        destructive
        :loading="busy"
        @update:open="(v) => !v && (confirm = null)"
        @confirm="visit(urls.destroy, 'delete')"
    />
    <ConfirmDialog
        :open="completing !== null"
        title="Complete follow-up"
        description="Optionally note how it went."
        confirm-label="Mark done"
        :loading="busy"
        @update:open="(v) => !v && (completing = null)"
        @confirm="
            visit(
                completing?.complete_url ?? null,
                'patch',
                { outcome: outcome || null },
                () => (completing = null),
            )
        "
    >
        <Input
            v-model="outcome"
            placeholder="Outcome (optional)"
            aria-label="Outcome"
        />
    </ConfirmDialog>
</template>
