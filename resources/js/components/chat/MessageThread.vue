<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ChatAvatar from '@/components/chat/ChatAvatar.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import { Button } from '@/components/ui/button';
import { useMessageThread } from '@/composables/useMessageThread';
import type { ThreadMessage } from '@/composables/useMessageThread';
import { formatDateTime } from '@/lib/format';
import type { ChatComposerLimits, PortalThread } from '@/types';

/**
 * A message thread kept in this system: a student's portal thread or a
 * website visitor's chat. Shared by the dashboard (staff), the student
 * portal and the public chat page. Messages are not sent to Tawk.to.
 *
 * Every message is rendered as plain text ({{ }} escapes it); nothing a
 * user wrote is ever rendered as HTML.
 */
const props = defineProps<{
    thread: PortalThread;
    messagesUrl: string;
    sendUrl: string;
    perspective: 'staff' | 'student' | 'visitor';
    senderName: string;
    composer: ChatComposerLimits;
    canReply: boolean;
    emptyText: string;
    placeholder?: string;
    onCaptchaRequired?: () => Promise<string | null>;
    /** Staff only: where to report that the agent is typing. */
    typingUrl?: string;
    /** Shorter message area, for the website chat popup. */
    compact?: boolean;
}>();

const scroller = ref<HTMLElement | null>(null);
const nearBottom = ref(true);

const thread = useMessageThread({
    initial: props.thread,
    messagesUrl: props.messagesUrl,
    sendUrl: props.sendUrl,
    perspective: props.perspective,
    senderName: props.senderName,
    onCaptchaRequired: props.onCaptchaRequired,
    typingUrl: props.typingUrl,
});

const { visible, typing, agentTyping, hasMore, loadingEarlier, offline } =
    thread;

// Messages already on screen when the thread opened do not animate in.
const initialKeys = new Set(visible.value.map((m) => m.key));
const isNew = (m: ThreadMessage) => !initialKeys.has(m.key);

function scrollToBottom(smooth = false): void {
    void nextTick(() => {
        scroller.value?.scrollTo({
            top: scroller.value.scrollHeight,
            behavior:
                smooth &&
                !window.matchMedia('(prefers-reduced-motion: reduce)').matches
                    ? 'smooth'
                    : 'auto',
        });
    });
}

function onScroll(): void {
    const el = scroller.value;

    if (el) {
        nearBottom.value =
            el.scrollHeight - el.scrollTop - el.clientHeight < 120;
    }
}

// Follow new messages only when the reader is already at the bottom.
watch(
    () => [visible.value.length, typing.value, !!agentTyping.value],
    () => {
        if (nearBottom.value) {
            scrollToBottom(true);
        }
    },
);

onMounted(scrollToBottom);

async function loadEarlier(): Promise<void> {
    const el = scroller.value;
    const before = el ? el.scrollHeight - el.scrollTop : 0;
    await thread.loadEarlier();
    await nextTick();

    if (el) {
        el.scrollTop = el.scrollHeight - before;
    }
}

/* Composer */
const body = ref('');
const files = ref<File[]>([]);
const fileInput = ref<HTMLInputElement | null>(null);
const allowedExt = computed(() =>
    props.composer.accept
        .split(',')
        .map((e) => e.trim().toLowerCase())
        .filter(Boolean),
);

const canSend = computed(
    () =>
        (body.value.trim() !== '' || files.value.length > 0) &&
        body.value.length <= props.composer.max_body,
);

function pickFiles(event: Event): void {
    const input = event.target as HTMLInputElement;

    for (const file of Array.from(input.files ?? [])) {
        const ext = '.' + (file.name.split('.').pop() ?? '').toLowerCase();

        if (files.value.length >= props.composer.max_files) {
            toast.error(
                `Attach up to ${props.composer.max_files} files at a time.`,
            );
            break;
        }

        if (!allowedExt.value.includes(ext)) {
            toast.error(
                `${file.name}: this type of file can't be sent in chat.`,
            );
            continue;
        }

        if (file.size > props.composer.max_file_kb * 1024) {
            toast.error(
                `${file.name} is larger than ${Math.round(props.composer.max_file_kb / 1024)} MB.`,
            );
            continue;
        }

        files.value.push(file);
    }

    input.value = '';
}

function removeFile(i: number): void {
    files.value.splice(i, 1);
}

function submit(): void {
    if (!canSend.value) {
        return;
    }

    thread.send(body.value, [...files.value]);
    body.value = '';
    files.value = [];
    nearBottom.value = true;
    scrollToBottom(true);
}

function onKeydown(event: KeyboardEvent): void {
    // Enter sends; Shift+Enter adds a line; never mid-composition (IME).
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        submit();
    }
}

/* Display */
const isMine = (m: ThreadMessage) => m.sender_type === props.perspective;

/** Held messages the viewer may not read arrive without body or files. */
const isRedacted = (m: ThreadMessage) =>
    m.moderation.state === 'held' && !m.body && !m.attachments.length;

/** A badge for staff: flagged, or (for moderators) the actual status. */
const staffBadge = (m: ThreadMessage) =>
    !isMine(m) &&
    ['flagged', 'pending', 'blocked', 'rejected', 'approved'].includes(
        m.moderation.state,
    ) &&
    m.moderation.label
        ? m.moderation.label
        : null;

function size(bytes: number): string {
    return bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
</script>

<template>
    <div class="flex min-h-0 flex-col" :class="compact ? 'flex-1' : ''">
        <div
            ref="scroller"
            class="flex flex-col gap-2.5 overflow-y-auto px-1 py-2"
            :class="
                compact
                    ? 'min-h-[180px] flex-1'
                    : 'h-[min(60vh,560px)] min-h-[320px]'
            "
            role="log"
            aria-live="polite"
            aria-label="Messages"
            @scroll.passive="onScroll"
        >
            <div v-if="hasMore" class="flex justify-center">
                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="loadingEarlier"
                    @click="loadEarlier"
                    >{{
                        loadingEarlier ? 'Loading…' : 'Load earlier messages'
                    }}</Button
                >
            </div>

            <p
                v-if="!visible.length && !typing"
                class="m-auto max-w-xs text-center text-[13px] text-muted-foreground"
            >
                {{ emptyText }}
            </p>

            <template v-for="m in visible" :key="m.key">
                <p
                    v-if="m.sender_type === 'system'"
                    class="self-center px-3 text-center text-[11px] text-muted-foreground italic"
                >
                    {{ m.body }} · {{ formatDateTime(m.created_at) }}
                </p>
                <div
                    v-else
                    class="flex max-w-[92%] items-end gap-2 sm:max-w-[80%]"
                    :class="[
                        isMine(m) ? 'self-end' : 'self-start',
                        isNew(m) ? 'chat-msg-in' : '',
                    ]"
                >
                    <ChatAvatar
                        v-if="!isMine(m) && (m.human || m.automated)"
                        :kind="m.human ? 'human' : 'bot'"
                        :src="m.avatar"
                        :name="m.sender_name"
                        class="mb-5"
                    />
                    <div
                        class="flex min-w-0 flex-col"
                        :class="isMine(m) ? 'items-end' : ''"
                    >
                        <span
                            class="mb-0.5 px-1 text-[11px] text-muted-foreground"
                        >
                            <b class="font-semibold text-foreground">{{
                                isMine(m) ? 'You' : m.sender_name
                            }}</b>
                            · {{ formatDateTime(m.created_at) }}
                            <span
                                v-if="m.automated"
                                class="ml-1 inline-flex items-center gap-0.5 rounded bg-muted px-1.5 py-px text-[10px] font-semibold text-muted-foreground"
                                title="This is an automatic reply, not a person."
                                ><AppIcon
                                    name="smart_toy"
                                    :size="11"
                                />Automated</span
                            >
                            <span
                                v-if="m.human && !isMine(m)"
                                class="ml-1 inline-flex items-center gap-0.5 rounded bg-success-soft px-1.5 py-px text-[10px] font-semibold text-success"
                                title="You are chatting with a person on our support team."
                                ><AppIcon
                                    name="support_agent"
                                    :size="11"
                                />Support team</span
                            >
                            <span
                                v-if="staffBadge(m)"
                                class="ml-1 inline-flex items-center gap-0.5 rounded bg-warning-soft px-1.5 py-px text-[10px] font-semibold text-warning"
                                :title="
                                    m.moderation.reasons.join(', ') || undefined
                                "
                                ><AppIcon name="flag" :size="11" />{{
                                    staffBadge(m)
                                }}</span
                            >
                        </span>
                        <p
                            v-if="isRedacted(m)"
                            class="rounded-2xl rounded-bl-md border border-dashed px-3.5 py-2.5 text-[12px] text-muted-foreground italic"
                        >
                            <AppIcon
                                name="shield"
                                :size="13"
                                class="align-[-2px]"
                            />
                            This message is held by the safety checks and is
                            waiting for a moderator.
                        </p>
                        <div
                            v-else
                            class="flex flex-col gap-1.5 rounded-2xl px-3.5 py-2.5 text-[13px] break-words"
                            :class="[
                                isMine(m)
                                    ? 'rounded-br-md bg-primary text-primary-foreground'
                                    : m.human
                                      ? 'rounded-bl-md border border-success/25 bg-success-soft text-foreground'
                                      : 'rounded-bl-md bg-muted text-foreground',
                                m.state === 'sending' ? 'opacity-70' : '',
                                m.state === 'failed'
                                    ? 'ring-2 ring-danger/60'
                                    : '',
                                m.moderation.state === 'not_delivered'
                                    ? 'opacity-60'
                                    : '',
                            ]"
                        >
                            <span v-if="m.body" class="whitespace-pre-line">{{
                                m.body
                            }}</span>
                            <template v-for="a in m.attachments" :key="a.id">
                                <a
                                    v-if="a.url"
                                    :href="a.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-background/15 px-2 py-1 text-[12px] font-semibold underline-offset-2 hover:underline"
                                    ><AppIcon name="attach_file" :size="15" />
                                    <span class="truncate">{{ a.name }}</span>
                                    <span
                                        class="shrink-0 font-normal opacity-75"
                                        >{{ size(a.size) }}</span
                                    ></a
                                >
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 text-[12px]"
                                    ><AppIcon name="attach_file" :size="15" />
                                    {{ a.name }}</span
                                >
                            </template>
                        </div>
                        <span
                            v-if="isMine(m)"
                            class="mt-0.5 flex items-center gap-1 px-1 text-[11px]"
                            :class="
                                m.state === 'failed'
                                    ? 'text-danger'
                                    : 'text-muted-foreground'
                            "
                            :role="m.state === 'failed' ? 'alert' : undefined"
                        >
                            <template v-if="m.state === 'sending'"
                                ><AppIcon name="schedule" :size="13" />
                                Sending…</template
                            >
                            <template v-else-if="m.state === 'failed'">
                                <AppIcon name="error" :size="13" />
                                {{ m.error }}
                                <button
                                    type="button"
                                    class="font-semibold underline"
                                    @click="thread.retry(m)"
                                >
                                    Retry
                                </button>
                                <button
                                    type="button"
                                    class="underline"
                                    @click="thread.discard(m)"
                                >
                                    Delete
                                </button>
                            </template>
                            <template
                                v-else-if="m.moderation.state === 'pending'"
                                ><AppIcon name="hourglass_top" :size="13" />
                                {{ m.moderation.label }}</template
                            >
                            <template
                                v-else-if="
                                    m.moderation.state === 'not_delivered'
                                "
                                ><span
                                    class="flex items-center gap-1 text-danger"
                                    ><AppIcon name="block" :size="13" />
                                    {{ m.moderation.label }}</span
                                ></template
                            >
                            <template v-else
                                ><AppIcon name="done" :size="13" />
                                Sent</template
                            >
                        </span>
                    </div>
                </div>
            </template>

            <div
                v-if="agentTyping && !typing"
                class="chat-msg-in flex max-w-[92%] items-end gap-2 self-start sm:max-w-[80%]"
                role="status"
            >
                <ChatAvatar
                    kind="human"
                    :src="agentTyping.avatar"
                    :name="agentTyping.name"
                    class="mb-5"
                />
                <div class="flex flex-col">
                    <span class="mb-0.5 px-1 text-[11px] text-muted-foreground"
                        >{{ agentTyping.name }} is typing…</span
                    >
                    <div
                        class="flex items-center gap-1 rounded-2xl rounded-bl-md border border-success/25 bg-success-soft px-4 py-3.5"
                        aria-hidden="true"
                    >
                        <span class="chat-dot" />
                        <span class="chat-dot" style="animation-delay: 0.15s" />
                        <span class="chat-dot" style="animation-delay: 0.3s" />
                    </div>
                </div>
            </div>

            <div
                v-if="typing"
                class="chat-msg-in flex max-w-[88%] flex-col self-start sm:max-w-[75%]"
                role="status"
            >
                <span class="mb-0.5 px-1 text-[11px] text-muted-foreground"
                    >Automated assistant is typing…</span
                >
                <div
                    class="flex items-center gap-1 rounded-2xl rounded-bl-md bg-muted px-4 py-3.5"
                    aria-hidden="true"
                >
                    <span class="chat-dot" />
                    <span class="chat-dot" style="animation-delay: 0.15s" />
                    <span class="chat-dot" style="animation-delay: 0.3s" />
                </div>
            </div>
        </div>

        <p
            v-if="offline"
            class="px-1 pb-1 text-[11px] text-warning"
            role="status"
        >
            Can't reach the server. New messages will appear when the connection
            is back.
        </p>

        <form
            v-if="canReply"
            class="mt-2 flex flex-col gap-2 border-t pt-3"
            @submit.prevent="submit"
        >
            <ul v-if="files.length" class="flex flex-wrap gap-1.5">
                <li
                    v-for="(f, i) in files"
                    :key="f.name + i"
                    class="inline-flex max-w-full items-center gap-1 rounded-md bg-muted px-2 py-1 text-xs"
                >
                    <AppIcon name="attach_file" :size="14" />
                    <span class="max-w-[180px] truncate">{{ f.name }}</span>
                    <span class="text-muted-foreground">{{
                        size(f.size)
                    }}</span>
                    <button
                        type="button"
                        :aria-label="`Remove ${f.name}`"
                        @click="removeFile(i)"
                    >
                        <AppIcon name="close" :size="13" />
                    </button>
                </li>
            </ul>
            <div class="flex items-end gap-2">
                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    class="hidden"
                    :accept="composer.accept"
                    @change="pickFiles"
                />
                <Button
                    v-if="composer.max_files > 0"
                    type="button"
                    variant="outline"
                    size="icon"
                    class="shrink-0"
                    aria-label="Attach files"
                    :disabled="files.length >= composer.max_files"
                    @click="fileInput?.click()"
                >
                    <AppIcon name="attach_file" :size="18" />
                </Button>
                <textarea
                    v-model="body"
                    rows="1"
                    :maxlength="composer.max_body"
                    :placeholder="placeholder ?? 'Write a message…'"
                    aria-label="Message"
                    class="field-sizing-content max-h-40 min-h-9 flex-1 resize-none rounded-md border border-input bg-transparent px-3 py-2 text-[13px] shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    @keydown="onKeydown"
                    @input="thread.pingTyping()"
                />
                <Button
                    type="submit"
                    class="shrink-0"
                    :disabled="!canSend"
                    aria-label="Send message"
                >
                    <AppIcon name="send" :size="18" />
                    <span class="hidden sm:inline">Send</span>
                </Button>
            </div>
            <p class="text-[11px] text-muted-foreground">
                Enter to send · Shift+Enter for a new line ·
                <template v-if="composer.max_files > 0"
                    >up to {{ composer.max_files }} files,
                    {{ Math.round(composer.max_file_kb / 1024) }} MB
                    each</template
                >
                <template v-else>text only</template>
            </p>
        </form>
    </div>
</template>
