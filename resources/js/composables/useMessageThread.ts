import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import type { PortalMessage, PortalThread } from '@/types';

/**
 * State and transport for a message thread (portal thread or website chat).
 *
 * Changes arrive by polling every 5 seconds while the page is open and
 * visible: new messages and moderation updates, after a sequence cursor.
 * A poll with nothing new costs the server one row lookup. The transport
 * sits behind `refresh()`, so a Laravel Reverb listener can call it later
 * instead of the timer, with nothing else changing.
 *
 * Sending is optimistic: a message shows as "sending" at once, becomes
 * "sent" when the server has stored it, or "failed" with the server's
 * reason and a retry. A retry reuses the same client ID, so the server
 * never stores it twice. All checks happen on the server; the browser's
 * checks only save a round trip.
 */
export type ThreadMessage = PortalMessage & {
    key: string;
    state: 'sent' | 'sending' | 'failed';
    error?: string;
    files?: File[];
};

const POLL_MS = 5000;

/**
 * Automatic replies the server has already stored but the page has not shown
 * yet (message ID → time to show it). While one is waiting, the thread shows
 * the typing indicator; polling cannot reveal it early. Module-level so a
 * reply to a visitor's first message survives the chat form being replaced
 * by the thread.
 */
const waiting = reactive(new Map<number, number>());

/** Hold an automatic reply back for `ms`, then show it. */
export function holdReply(id: number, ms: number): void {
    waiting.set(id, Date.now() + ms);
    setTimeout(() => waiting.delete(id), ms);
}

export function uuid(): string {
    // crypto.randomUUID only exists in secure contexts (https, localhost).
    if (window.isSecureContext && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');

    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** JSON request headers with the CSRF token Laravel set in its XSRF-TOKEN cookie. */
export const headers = (): Record<string, string> => ({
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-XSRF-TOKEN': xsrfToken(),
});

type ErrorBody = {
    message?: PortalMessage | string;
    auto_reply?: {
        message: PortalMessage;
        typing_ms: number;
        handover: boolean;
    };
    errors?: Record<string, string[]>;
    captcha_required?: boolean;
};

function fromServer(m: PortalMessage): ThreadMessage {
    return { ...m, key: m.client_id ?? `m${m.id}`, state: 'sent' };
}

export function useMessageThread(options: {
    initial: PortalThread;
    messagesUrl: string;
    sendUrl: string;
    perspective: 'staff' | 'student' | 'visitor';
    senderName: string;
    onIncoming?: () => void;
    /** Asked for a CAPTCHA token when the server requires one (website chat). */
    onCaptchaRequired?: () => Promise<string | null>;
    /** Staff only: pinged while typing, so the visitor sees "… is typing". */
    typingUrl?: string;
}) {
    const messages = ref<ThreadMessage[]>(
        options.initial.messages.map(fromServer),
    );
    const cursor = ref(options.initial.cursor);
    const hasMore = ref(options.initial.has_more);
    const loadingEarlier = ref(false);
    const offline = ref(false);
    const status = ref<string | null>(null);
    /** The team member who is typing a reply right now (shown to visitors and students only). */
    const agentTyping = ref<{ name: string; avatar: string | null } | null>(
        null,
    );

    let timer: ReturnType<typeof setTimeout> | undefined;
    let lastPing = 0;
    let inFlight: AbortController | null = null;

    /** Adds or replaces messages by server ID or client ID; keeps ID order. */
    function merge(incoming: PortalMessage[]): boolean {
        let added = false;

        for (const m of incoming) {
            const i = messages.value.findIndex(
                (x) =>
                    (x.id > 0 && x.id === m.id) ||
                    (m.client_id !== null && x.client_id === m.client_id),
            );

            if (i >= 0) {
                messages.value[i] = {
                    ...fromServer(m),
                    key: messages.value[i].key,
                };
            } else {
                messages.value.push(fromServer(m));
                added = true;
            }
        }

        // Unsent messages (id 0) stay at the end, in the order they were written.
        messages.value.sort(
            (a, b) =>
                (a.id || Number.MAX_SAFE_INTEGER) -
                (b.id || Number.MAX_SAFE_INTEGER),
        );

        return added;
    }

    async function refresh(): Promise<void> {
        if (inFlight || document.visibilityState === 'hidden') {
            return;
        }

        inFlight = new AbortController();

        try {
            const url = new URL(options.messagesUrl, window.location.origin);
            url.searchParams.set('after', String(cursor.value));
            const response = await fetch(url, {
                headers: headers(),
                credentials: 'same-origin',
                signal: inFlight.signal,
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const data = (await response.json()) as {
                messages: PortalMessage[];
                cursor: number;
                status: string | null;
                typing?: { name: string; avatar: string | null } | null;
            };

            offline.value = false;
            agentTyping.value = data.typing ?? null;
            status.value = data.status;
            cursor.value = Math.max(cursor.value, data.cursor);
            const known = new Set(messages.value.map((m) => m.id));

            const fromOthers = data.messages.some(
                (m) =>
                    !known.has(m.id) &&
                    m.sender_type !== options.perspective &&
                    m.sender_type !== 'system',
            );

            if (merge(data.messages) && fromOthers) {
                options.onIncoming?.();
            }
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                offline.value = true;
            }
        } finally {
            inFlight = null;
        }
    }

    async function loadEarlier(): Promise<void> {
        const first = messages.value.find((m) => m.id > 0);

        if (!first || loadingEarlier.value) {
            return;
        }

        loadingEarlier.value = true;

        try {
            const url = new URL(options.messagesUrl, window.location.origin);
            url.searchParams.set('before', String(first.id));
            const response = await fetch(url, {
                headers: headers(),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const data = (await response.json()) as {
                messages: PortalMessage[];
                has_more: boolean;
            };

            merge(data.messages);
            hasMore.value = data.has_more;
        } catch {
            offline.value = true;
        } finally {
            loadingEarlier.value = false;
        }
    }

    async function deliver(
        item: ThreadMessage,
        captchaToken?: string,
    ): Promise<void> {
        item.state = 'sending';
        item.error = undefined;

        const form = new FormData();
        form.append('client_id', item.client_id ?? '');

        if (captchaToken) {
            form.append('captcha_token', captchaToken);
        }

        if (item.body) {
            form.append('body', item.body);
        }

        for (const file of item.files ?? []) {
            form.append('files[]', file);
        }

        try {
            const response = await fetch(options.sendUrl, {
                method: 'POST',
                headers: headers(),
                credentials: 'same-origin',
                body: form,
            });

            const data = (await response.json().catch(() => ({}))) as ErrorBody;

            if (response.ok && typeof data.message === 'object') {
                if (data.auto_reply) {
                    // Hold it before it can be merged, so a poll cannot show it early.
                    holdReply(
                        data.auto_reply.message.id,
                        data.auto_reply.typing_ms,
                    );
                    merge([data.auto_reply.message]);
                }

                merge([data.message]);

                return;
            }

            if (data.captcha_required && options.onCaptchaRequired) {
                const token = await options.onCaptchaRequired();

                if (token) {
                    return deliver(item, token);
                }
            }

            item.state = 'failed';
            item.error = errorText(response.status, data);
        } catch {
            item.state = 'failed';
            item.error = 'Not sent. Check your connection and retry.';
        }
    }

    function errorText(status: number, data: ErrorBody): string {
        if (status === 419) {
            return 'Your session expired. Reload the page, then retry.';
        }

        if (status === 422 && data.errors) {
            return (
                Object.values(data.errors)[0]?.[0] ??
                'This message could not be sent.'
            );
        }

        if (typeof data.message === 'string' && status < 500) {
            return data.message;
        }

        return status === 429
            ? 'Too many messages. Wait a moment, then retry.'
            : 'Not sent. Check your connection and retry.';
    }

    function send(body: string, files: File[]): void {
        const clientId = uuid();
        const item: ThreadMessage = {
            id: 0,
            key: clientId,
            client_id: clientId,
            sender_type: options.perspective,
            sender_name: options.senderName,
            body: body.trim() || null,
            attachments: files.map((f, i) => ({
                id: -i - 1,
                name: f.name,
                size: f.size,
                mime_type: f.type,
                url: '',
            })),
            moderation: { state: 'delivered', label: null, reasons: [] },
            created_at: new Date().toISOString(),
            state: 'sending',
            files,
        };

        messages.value.push(item);
        // Work on the reactive copy so state changes render.
        void deliver(messages.value[messages.value.length - 1]);
    }

    function retry(item: ThreadMessage): void {
        void deliver(item);
    }

    function discard(item: ThreadMessage): void {
        messages.value = messages.value.filter((m) => m.key !== item.key);
    }

    function onVisibility(): void {
        if (document.visibilityState === 'visible') {
            void refresh();
        }
    }

    /** Staff: tell the server they are typing (at most every 3 seconds). */
    function pingTyping(): void {
        if (!options.typingUrl || Date.now() - lastPing < 3000) {
            return;
        }

        lastPing = Date.now();
        void fetch(options.typingUrl, {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
        }).catch(() => undefined);
    }

    // Poll faster once a person is in the chat, so their replies and typing show quickly.
    function nextDelay(): number {
        return options.perspective !== 'staff' &&
            (agentTyping.value || messages.value.some((m) => m.human))
            ? 2500
            : POLL_MS;
    }

    function schedule(): void {
        timer = setTimeout(async () => {
            await refresh();
            schedule();
        }, nextDelay());
    }

    onMounted(() => {
        schedule();
        document.addEventListener('visibilitychange', onVisibility);
    });

    onBeforeUnmount(() => {
        clearTimeout(timer);
        inFlight?.abort();
        document.removeEventListener('visibilitychange', onVisibility);
    });

    /** Messages to show: automatic replies still "being typed" are left out. */
    const visible = computed(() =>
        messages.value.filter((m) => !waiting.has(m.id)),
    );
    /** True while an automatic reply is on its way. */
    const typing = computed(() =>
        messages.value.some((m) => waiting.has(m.id)),
    );

    return {
        messages,
        visible,
        typing,
        agentTyping,
        pingTyping,
        hasMore,
        loadingEarlier,
        offline,
        status,
        send,
        retry,
        discard,
        refresh,
        loadEarlier,
    };
}
