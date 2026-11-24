<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import MessageThread from '@/components/chat/MessageThread.vue';
import { headers, holdReply, uuid } from '@/composables/useMessageThread';
import { renderTurnstile } from '@/lib/turnstile';
import { contact } from '@/routes';
import { link, start } from '@/routes/chat/guest';
import {
    index as messagesIndex,
    store as messagesStore,
} from '@/routes/chat/guest/messages';
import type { ChatComposerLimits, PortalThread } from '@/types';

/**
 * Website chat for visitors without an account (REQ §11 F), used by the
 * /chat page and the chat popup on every website page. The server finds the
 * visitor's chat from an HTTP-only cookie; this never sees or sends a
 * conversation ID. Every check (limits, CAPTCHA, content, moderation) runs
 * on the server.
 */
export type GuestChatState = {
    enabled: boolean;
    visitor: { name: string; suspended: boolean; linked: boolean } | null;
    thread: (PortalThread & { status: string | null }) | null;
    composer: ChatComposerLimits;
    formToken: string;
    captcha: { siteKey: string | null; required: boolean };
    canLink: boolean;
    portalChatUrl: string | null;
};

const props = defineProps<GuestChatState & { compact?: boolean }>();
const emit = defineEmits<{ started: [] }>();

/* ---------------- Start form ---------------- */

const form = reactive({ name: '', email: '', body: '', website: '' });
const errors = ref<Record<string, string>>({});
const notice = ref<string | null>(null);
const sending = ref(false);
const needsCaptcha = ref(props.captcha.required && !!props.captcha.siteKey);
const startToken = ref<string | null>(null);
const startCaptchaEl = ref<HTMLElement | null>(null);
let removeStartCaptcha: (() => void) | null = null;

async function mountStartCaptcha(): Promise<void> {
    await nextTick();

    if (!props.captcha.siteKey || !startCaptchaEl.value || removeStartCaptcha) {
        return;
    }

    try {
        removeStartCaptcha = await renderTurnstile(
            startCaptchaEl.value,
            props.captcha.siteKey,
            (t) => (startToken.value = t),
        );
    } catch {
        notice.value =
            'The security check could not load. Check your connection and reload the page.';
    }
}

watch(needsCaptcha, (v) => {
    if (v) {
        void mountStartCaptcha();
    }
});

onMounted(() => {
    if (needsCaptcha.value) {
        void mountStartCaptcha();
    }
});

onBeforeUnmount(() => {
    removeStartCaptcha?.();
    removeThreadCaptcha?.();
});

async function startChat(): Promise<void> {
    errors.value = {};
    notice.value = null;

    if (needsCaptcha.value && !startToken.value) {
        notice.value = 'Please complete the security check.';

        return;
    }

    sending.value = true;

    try {
        const response = await fetch(start.url(), {
            method: 'POST',
            headers: { ...headers(), 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                ...form,
                email: form.email || null,
                form_token: props.formToken,
                captcha_token: startToken.value,
                client_id: uuid(),
            }),
        });

        if (response.ok) {
            const created = (await response.json().catch(() => ({}))) as {
                auto_reply?: { message: { id: number }; typing_ms: number };
            };

            // Show the typing indicator before the automatic reply appears.
            if (created.auto_reply) {
                holdReply(
                    created.auto_reply.message.id,
                    created.auto_reply.typing_ms,
                );
            }

            emit('started');

            return;
        }

        const data = (await response.json().catch(() => ({}))) as {
            message?: string;
            errors?: Record<string, string[]>;
            captcha_required?: boolean;
        };

        if (data.errors) {
            errors.value = Object.fromEntries(
                Object.entries(data.errors).map(([k, v]) => [k, v[0]]),
            );
        }

        if (data.captcha_required && props.captcha.siteKey) {
            needsCaptcha.value = true;
            startToken.value = null;
            removeStartCaptcha?.();
            removeStartCaptcha = null;
            void mountStartCaptcha();
        }

        notice.value =
            response.status === 419
                ? 'This page has expired. Reload it and try again.'
                : data.errors
                  ? null
                  : (data.message ?? 'Something went wrong. Please try again.');
    } catch {
        notice.value = 'Could not reach us. Check your connection and retry.';
    } finally {
        sending.value = false;
    }
}

/* ---------------- CAPTCHA asked for while chatting ---------------- */

const threadCaptchaOpen = ref(false);
const threadCaptchaEl = ref<HTMLElement | null>(null);
let removeThreadCaptcha: (() => void) | null = null;

function askCaptcha(): Promise<string | null> {
    if (!props.captcha.siteKey) {
        return Promise.resolve(null);
    }

    threadCaptchaOpen.value = true;

    return new Promise((resolve) => {
        void nextTick(async () => {
            if (!threadCaptchaEl.value) {
                resolve(null);

                return;
            }

            try {
                removeThreadCaptcha = await renderTurnstile(
                    threadCaptchaEl.value,
                    props.captcha.siteKey!,
                    (t) => {
                        if (t) {
                            removeThreadCaptcha?.();
                            removeThreadCaptcha = null;
                            threadCaptchaOpen.value = false;
                            resolve(t);
                        }
                    },
                );
            } catch {
                threadCaptchaOpen.value = false;
                resolve(null);
            }
        });
    });
}

function linkToAccount(): void {
    router.post(link.url());
}

const field =
    'h-[50px] w-full rounded-[10px] border border-site-field-border bg-site-field px-[13px] text-sm text-site-ink outline-none placeholder:text-site-muted focus:border-site-red focus:ring-3 focus:ring-site-red/15 aria-invalid:border-site-red';
const span = props.compact ? '' : 'sm:col-span-2';
const label = 'mb-2 block text-sm leading-[19.5px] font-medium text-site-ink';
</script>

<template>
    <!-- In the popup the thread fills the space left, so only the thread scrolls, not the popup too. -->
    <div :class="compact ? 'flex min-h-0 flex-1 flex-col' : ''">
        <p
            class="text-[11px] font-semibold tracking-wide text-site-red uppercase"
        >
            Website chat
        </p>
        <h1
            :id="compact ? 'chat-popup-heading' : 'chat-heading'"
            :class="[
                'mt-2 leading-tight font-bold text-site-ink',
                compact ? 'text-lg' : 'text-[26px]',
            ]"
        >
            {{
                thread
                    ? `Hi ${visitor?.name}, we're here to help`
                    : 'Chat with an RTL adviser'
            }}
        </h1>

        <!-- Switched off -->
        <div v-if="!enabled" class="mt-4 text-sm text-site-muted">
            <p>Website chat isn't available right now.</p>
            <p class="mt-2">
                Please
                <Link
                    :href="contact()"
                    class="font-semibold text-site-red underline"
                    >send us an enquiry</Link
                >
                and we'll get back to you.
            </p>
        </div>

        <!-- An ongoing chat -->
        <template v-else-if="thread && visitor">
            <p class="mt-2 text-sm text-site-muted">
                Ask about study abroad, visas, admissions or documents. Replies
                appear here; keep this page open or come back later on this
                device.
            </p>

            <div
                v-if="canLink"
                class="mt-4 flex flex-wrap items-center gap-3 rounded-[12px] bg-site-field p-3 text-sm"
            >
                <span class="flex-1"
                    >You're signed in. Save this chat to your student account so
                    your counselor can see it.</span
                >
                <button
                    type="button"
                    class="h-10 rounded-[10px] bg-site-red px-4 text-sm font-semibold text-white hover:bg-site-red-dark"
                    @click="linkToAccount"
                >
                    Save to my account
                </button>
            </div>
            <p v-else-if="portalChatUrl" class="mt-4 text-sm text-site-muted">
                You're signed in.
                <Link
                    :href="portalChatUrl"
                    class="font-semibold text-site-red underline"
                    >Go to your portal messages</Link
                >.
            </p>

            <p
                v-if="visitor.suspended"
                class="mt-4 rounded-[10px] border border-site-red/30 bg-site-red/5 p-3 text-sm text-site-red"
                role="status"
            >
                This chat has been paused. Please contact us by email or phone.
            </p>

            <div
                :class="compact ? 'mt-4 flex min-h-0 flex-1 flex-col' : 'mt-5'"
            >
                <MessageThread
                    :thread="thread"
                    :messages-url="messagesIndex.url()"
                    :send-url="messagesStore.url()"
                    perspective="visitor"
                    :sender-name="visitor.name"
                    :composer="composer"
                    :can-reply="!visitor.suspended && !visitor.linked"
                    :on-captcha-required="askCaptcha"
                    :compact="compact"
                    empty-text="Your chat has started. An adviser will reply here."
                />
                <div
                    v-show="threadCaptchaOpen"
                    class="mt-3 rounded-[12px] border border-site-line p-3"
                >
                    <p class="mb-2 text-sm text-site-ink">
                        Please complete this quick check to send your message.
                    </p>
                    <div ref="threadCaptchaEl" />
                </div>
            </div>
        </template>

        <!-- Start a chat -->
        <form
            v-else
            :class="[
                'relative grid',
                compact ? 'mt-4 gap-4' : 'mt-5 gap-5 sm:grid-cols-2',
            ]"
            novalidate
            @submit.prevent="startChat"
        >
            <div>
                <label for="gc-name" :class="label">Your name *</label>
                <input
                    id="gc-name"
                    v-model="form.name"
                    type="text"
                    autocomplete="name"
                    maxlength="80"
                    required
                    :class="field"
                    :aria-invalid="!!errors.name || undefined"
                    aria-describedby="gc-name-err"
                />
                <p
                    v-if="errors.name"
                    id="gc-name-err"
                    class="mt-1.5 text-xs text-site-red"
                >
                    {{ errors.name }}
                </p>
            </div>
            <div>
                <label for="gc-email" :class="label">Email (optional)</label>
                <input
                    id="gc-email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    maxlength="255"
                    :class="field"
                    :aria-invalid="!!errors.email || undefined"
                    aria-describedby="gc-email-err"
                />
                <p
                    v-if="errors.email"
                    id="gc-email-err"
                    class="mt-1.5 text-xs text-site-red"
                >
                    {{ errors.email }}
                </p>
            </div>
            <div :class="span">
                <label for="gc-body" :class="label">How can we help? *</label>
                <textarea
                    id="gc-body"
                    v-model="form.body"
                    rows="4"
                    :maxlength="composer.max_body"
                    required
                    placeholder="Tell us what you'd like to study, where, and any questions you have…"
                    :class="[field, 'h-[120px] resize-y py-[13px]']"
                    :aria-invalid="!!errors.body || undefined"
                    aria-describedby="gc-body-err"
                />
                <p
                    v-if="errors.body"
                    id="gc-body-err"
                    class="mt-1.5 text-xs text-site-red"
                >
                    {{ errors.body }}
                </p>
            </div>

            <!-- Honeypot: hidden from people and screen readers. -->
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="gc-website">Website</label>
                <input
                    id="gc-website"
                    v-model="form.website"
                    type="text"
                    tabindex="-1"
                    autocomplete="off"
                />
            </div>

            <div v-if="needsCaptcha" :class="span">
                <div ref="startCaptchaEl" />
            </div>

            <p
                v-if="notice"
                :class="['text-sm text-site-red', span]"
                role="alert"
            >
                {{ notice }}
            </p>

            <div :class="['flex flex-wrap items-center gap-4', span]">
                <button
                    type="submit"
                    :disabled="sending"
                    class="h-[52px] rounded-[12px] bg-site-red px-5 text-sm font-semibold text-white transition-colors hover:bg-site-red-dark disabled:opacity-60"
                >
                    {{ sending ? 'Starting…' : 'Start chat' }}
                </button>
                <p class="text-xs text-site-muted">
                    Please don't share passwords or card details in chat.
                </p>
            </div>
        </form>
    </div>
</template>
