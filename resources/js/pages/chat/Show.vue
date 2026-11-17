<script setup lang="ts">
import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import MessageThread from '@/components/chat/MessageThread.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';
import type {
    ChatComposerLimits,
    ChatDetail,
    Option,
    PortalThread,
} from '@/types';

const props = defineProps<{
    conversation: ChatDetail;
    thread: PortalThread | null;
    composer: ChatComposerLimits;
    topics: string[];
    assignees: Option[];
    can: {
        update: boolean;
        reply: boolean;
        assign: boolean;
        createLead: boolean;
    };
    maxTags: number;
    urls: {
        index: string;
        messages: string;
        send: string;
        update: string;
        lead: string;
        student: string | null;
        leadRecord: string | null;
        tawk: string;
        moderation: string | null;
    };
}>();

const c = computed(() => props.conversation);
const isOffline = computed(() => c.value.kind === 'offline');
const isPortal = computed(() => c.value.kind === 'portal');
const isGuest = computed(() => c.value.kind === 'guest');
/** Messages kept here and answered from this page. */
const isNative = computed(() => isPortal.value || isGuest.value);
const me = computed(() => usePage().props.auth.user?.name ?? 'You');

setLayoutProps({
    breadcrumbs: [
        {
            title: isPortal.value
                ? 'Student messages'
                : isGuest.value
                  ? 'Website chats'
                  : isOffline.value
                    ? 'Offline messages'
                    : 'Chat history',
            href: props.urls.index,
        },
        { title: props.conversation.visitor, href: '' },
    ],
});

const busy = ref(false);
const newTag = ref('');
const assignee = ref(c.value.assigned_to ? String(c.value.assigned_to) : '');

function patch(data: Record<string, string | string[] | null>): void {
    busy.value = true;
    router.patch(props.urls.update, data, {
        preserveScroll: true,
        onError: (e) => toast.error(Object.values(e)[0] ?? 'Could not save.'),
        onFinish: () => (busy.value = false),
    });
}

function addTag(): void {
    const tag = newTag.value.trim();

    if (
        !tag ||
        c.value.tags.some((t) => t.toLowerCase() === tag.toLowerCase())
    ) {
        newTag.value = '';

        return;
    }

    patch({ tags: [...c.value.tags, tag] });
    newTag.value = '';
}

function toggleTopic(topic: string): void {
    const has = c.value.tags.some(
        (t) => t.toLowerCase() === topic.toLowerCase(),
    );

    patch({
        tags: has
            ? c.value.tags.filter(
                  (t) => t.toLowerCase() !== topic.toLowerCase(),
              )
            : [...c.value.tags, topic],
    });
}

function removeTag(tag: string): void {
    patch({ tags: c.value.tags.filter((t) => t !== tag) });
}

const lead = useForm({
    name:
        c.value.visitor_email || c.value.visitor !== 'Visitor'
            ? c.value.visitor
            : '',
    email: c.value.visitor_email ?? '',
    phone: '',
});

function createLead(): void {
    lead.post(props.urls.lead, { preserveScroll: true });
}

const bubble: Record<string, string> = {
    visitor: 'bg-muted text-foreground self-start',
    agent: 'bg-accent text-accent-foreground self-end',
    system: 'self-center bg-transparent text-muted-foreground italic',
};
</script>

<template>
    <Head :title="`${c.kind_label} · ${c.visitor}`" />

    <PageHeader
        :eyebrow="c.kind_label + (c.reference ? ` #${c.reference}` : '')"
        :title="c.subject ?? c.visitor"
        :description="
            [
                c.subject ? c.visitor : null,
                c.visitor_email,
                c.location,
                formatDateTime(c.started_at),
            ]
                .filter(Boolean)
                .join(' · ')
        "
    >
        <template #actions>
            <StatusBadge
                :label="c.status_label"
                :tone="c.status_tone"
                class="self-center"
            />
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
                    ><AppIcon name="open_in_new" :size="18" /> Open in
                    Tawk.to</a
                >
            </Button>
        </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(300px,1fr)]">
        <PanelCard
            v-if="isNative && thread"
            title="Messages"
            :description="
                isGuest
                    ? 'A website visitor without an account. Replies appear on the visitor\'s chat page. Not sent to Tawk.to.'
                    : 'Kept in RTL Immigration and shown in the student\'s portal. Not sent to Tawk.to.'
            "
        >
            <MessageThread
                :thread="thread"
                :messages-url="urls.messages"
                :send-url="urls.send"
                perspective="staff"
                :sender-name="me"
                :composer="composer"
                :can-reply="can.reply"
                :empty-text="
                    isGuest
                        ? 'No messages yet.'
                        : 'No messages yet. Write to the student to start the conversation.'
                "
                :placeholder="
                    isGuest ? 'Reply to the visitor…' : 'Reply to the student…'
                "
            />
        </PanelCard>
        <PanelCard
            v-else
            :title="isOffline ? 'Message' : 'Transcript'"
            :description="
                isOffline
                    ? 'Left through the chat widget while no one was online.'
                    : c.messages.length
                      ? `${c.messages.length} messages`
                      : undefined
            "
        >
            <p
                v-if="isOffline"
                class="text-[13px] whitespace-pre-line text-secondary-foreground"
            >
                {{ c.first_message ?? 'No message text.' }}
            </p>
            <div v-else-if="c.messages.length" class="flex flex-col gap-2.5">
                <div
                    v-for="(m, i) in c.messages"
                    :key="i"
                    class="flex max-w-[85%] flex-col rounded-xl px-3.5 py-2.5 text-[13px]"
                    :class="bubble[m.sender]"
                >
                    <span
                        v-if="m.sender !== 'system'"
                        class="mb-0.5 text-[11px] font-semibold opacity-75"
                        >{{
                            m.sender === 'visitor'
                                ? c.visitor
                                : (m.name ?? 'Agent')
                        }}
                        · {{ formatDateTime(m.time) }}</span
                    >
                    <span class="whitespace-pre-line">{{ m.text }}</span>
                    <a
                        v-for="a in m.attachments"
                        :key="a.url"
                        :href="a.url"
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        class="mt-1 inline-flex items-center gap-1 text-[12px] font-semibold underline"
                        ><AppIcon name="attach_file" :size="14" />
                        {{ a.name }}</a
                    >
                </div>
            </div>
            <EmptyState
                v-else
                icon="hourglass_empty"
                :title="
                    c.status === 'open'
                        ? 'Chat in progress'
                        : 'No transcript received'
                "
                :description="
                    c.status === 'open'
                        ? 'Tawk.to sends the transcript once the chat ends. Reply in Tawk.to meanwhile.'
                        : 'Tawk.to has not sent a transcript for this chat. It is still available in Tawk.to.'
                "
            >
                <p v-if="c.first_message" class="text-[13px] italic">
                    “{{ c.first_message }}”
                </p>
            </EmptyState>
        </PanelCard>

        <div class="flex flex-col gap-4">
            <PanelCard title="Details">
                <dl class="grid gap-3 text-[13px]">
                    <div>
                        <dt class="text-[11px] text-muted-foreground">
                            Student
                        </dt>
                        <dd>
                            <Link
                                v-if="c.student && urls.student"
                                :href="urls.student"
                                class="font-semibold text-primary"
                                >{{ c.student.name }} ·
                                {{ c.student.number }}</Link
                            >
                            <span v-else-if="c.student"
                                >{{ c.student.name }} ·
                                {{ c.student.number }}</span
                            >
                            <span v-else class="text-muted-foreground"
                                >Not a known student</span
                            >
                        </dd>
                    </div>
                    <div v-if="c.lead">
                        <dt class="text-[11px] text-muted-foreground">Lead</dt>
                        <dd>
                            <Link
                                v-if="urls.leadRecord"
                                :href="urls.leadRecord"
                                class="font-semibold text-primary"
                                >{{ c.lead.number }}</Link
                            >
                            <span v-else>{{ c.lead.number }}</span>
                        </dd>
                    </div>
                    <div v-if="c.ended_at">
                        <dt class="text-[11px] text-muted-foreground">Ended</dt>
                        <dd>{{ formatDateTime(c.ended_at) }}</dd>
                    </div>
                    <div v-if="c.property_name">
                        <dt class="text-[11px] text-muted-foreground">
                            Tawk.to property
                        </dt>
                        <dd>{{ c.property_name }}</dd>
                    </div>
                </dl>

                <div v-if="can.assign" class="mt-4">
                    <FormField label="Assigned to">
                        <template #default="{ id }">
                            <SelectInput
                                :id="id"
                                v-model="assignee"
                                :options="assignees"
                                placeholder="Unassigned"
                                :disabled="busy"
                                @update:model-value="
                                    (v) => patch({ assigned_to: v || null })
                                "
                            />
                        </template>
                    </FormField>
                </div>
                <p v-else class="mt-4 text-[13px]">
                    <span class="text-[11px] text-muted-foreground"
                        >Assigned to</span
                    ><br />{{ c.assignee ?? 'Unassigned' }}
                </p>

                <Button
                    v-if="can.update && (isOffline || isNative)"
                    class="mt-4 w-full"
                    :variant="c.status === 'open' ? 'default' : 'outline'"
                    :disabled="busy"
                    @click="
                        patch({
                            status: c.status === 'open' ? 'closed' : 'open',
                        })
                    "
                    >{{
                        c.status === 'open'
                            ? isNative
                                ? 'Mark resolved'
                                : 'Mark handled'
                            : 'Reopen'
                    }}</Button
                >
            </PanelCard>

            <PanelCard
                title="Topics & tags"
                :description="`Up to ${maxTags}. Kept here, not sent to Tawk.to.`"
            >
                <div
                    v-if="can.update"
                    class="mb-3 flex flex-wrap gap-1.5"
                    role="group"
                    aria-label="Topics"
                >
                    <button
                        v-for="t in topics"
                        :key="t"
                        type="button"
                        class="rounded-full border px-2.5 py-1 text-xs font-semibold transition-colors"
                        :class="
                            c.tags.some(
                                (x) => x.toLowerCase() === t.toLowerCase(),
                            )
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:bg-muted'
                        "
                        :aria-pressed="
                            c.tags.some(
                                (x) => x.toLowerCase() === t.toLowerCase(),
                            )
                        "
                        :disabled="busy"
                        @click="toggleTopic(t)"
                    >
                        {{ t }}
                    </button>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <span
                        v-for="t in c.tags"
                        :key="t"
                        class="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-1 text-xs font-semibold"
                    >
                        {{ t }}
                        <button
                            v-if="can.update"
                            type="button"
                            :aria-label="`Remove tag ${t}`"
                            :disabled="busy"
                            @click="removeTag(t)"
                        >
                            <AppIcon name="close" :size="13" />
                        </button>
                    </span>
                    <span
                        v-if="!c.tags.length"
                        class="text-[13px] text-muted-foreground"
                        >No tags.</span
                    >
                </div>
                <form
                    v-if="can.update && c.tags.length < maxTags"
                    class="mt-3 flex gap-2"
                    @submit.prevent="addTag"
                >
                    <Input
                        v-model="newTag"
                        maxlength="40"
                        placeholder="e.g. scholarship"
                        aria-label="New tag"
                        class="h-9"
                    />
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        :disabled="busy || !newTag.trim()"
                        >Add</Button
                    >
                </form>
            </PanelCard>

            <PanelCard
                v-if="can.createLead"
                title="Turn into a lead"
                description="Start the usual lead follow-up for this visitor."
            >
                <form class="flex flex-col gap-3" @submit.prevent="createLead">
                    <FormField label="Name" :error="lead.errors.name" required>
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="lead.name"
                                :aria-invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <FormField label="Email" :error="lead.errors.email">
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="lead.email"
                                type="email"
                                :aria-invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <FormField
                        label="Phone"
                        :error="lead.errors.phone"
                        hint="Email or phone is needed."
                    >
                        <template #default="{ id, invalid }">
                            <Input
                                :id="id"
                                v-model="lead.phone"
                                type="tel"
                                :aria-invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <Button type="submit" :disabled="lead.processing"
                        >Create lead</Button
                    >
                </form>
            </PanelCard>
        </div>
    </div>
</template>
