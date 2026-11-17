import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * Tawk.to live chat widget (REQ §18).
 *
 * The server decides who gets the widget and sends it as the shared `chat`
 * prop: visitors and students do, staff do not. This file loads Tawk.to's
 * script once and then keeps the widget in step with that prop as Inertia
 * moves between pages, because the page never fully reloads.
 *
 * Identity comes from the server only. With Secure Mode on, the name and
 * email are sent together with a hash made on the server, which Tawk.to
 * checks; the browser never sees the key.
 */

export type ChatWidgetConfig = {
    propertyId: string;
    widgetId: string;
    visitor: { name: string; email: string; hash: string | null } | null;
    attributes: Record<string, string>;
    tags: string[];
};

export type ChatStatus = 'online' | 'away' | 'offline';

type TawkCallback = (error?: unknown) => void;

type TawkApi = {
    visitor?: { name: string; email: string; hash?: string };
    onLoad?: () => void;
    onStatusChange?: (status: ChatStatus) => void;
    getStatus?: () => ChatStatus;
    setAttributes?: (
        attributes: Record<string, string>,
        callback?: TawkCallback,
    ) => void;
    addTags?: (tags: string[], callback?: TawkCallback) => void;
    logout?: (callback?: TawkCallback) => void;
    showWidget?: () => void;
    hideWidget?: () => void;
    maximize?: () => void;
};

declare global {
    interface Window {
        Tawk_API?: TawkApi;
        Tawk_LoadStart?: Date;
    }
}

/** Whether the widget has finished loading on this page. */
export const chatReady = ref(false);
/** Whether Tawk.to's script could not be loaded (blocked or offline). */
export const chatFailed = ref(false);
/** Whether any agent is online, as reported by Tawk.to. Null until known. */
export const chatStatus = ref<ChatStatus | null>(null);

let scriptRequested = false;
let wanted: ChatWidgetConfig | null = null;
// The identity the widget currently carries: an email, '' for anonymous.
let appliedIdentity: string | null = null;

function api(): TawkApi {
    return (window.Tawk_API ??= {});
}

/** Send the signed-in student's details to an already loaded widget. */
function identify(config: ChatWidgetConfig): void {
    const tawk = api();
    const attributes: Record<string, string> = { ...config.attributes };

    // Tawk.to only accepts a name and email after load when they are signed.
    if (config.visitor?.hash) {
        attributes.name = config.visitor.name;
        attributes.email = config.visitor.email;
        attributes.hash = config.visitor.hash;
    }

    if (Object.keys(attributes).length > 0) {
        tawk.setAttributes?.(attributes, () => {});
    }

    if (config.tags.length > 0) {
        tawk.addTags?.(config.tags, () => {});
    }
}

function load(config: ChatWidgetConfig): void {
    scriptRequested = true;
    const tawk = api();

    // Must be set before their script runs.
    if (config.visitor) {
        tawk.visitor = {
            name: config.visitor.name,
            email: config.visitor.email,
            ...(config.visitor.hash ? { hash: config.visitor.hash } : {}),
        };
    }

    appliedIdentity = config.visitor?.email ?? '';

    tawk.onLoad = () => {
        chatReady.value = true;
        chatStatus.value = tawk.getStatus?.() ?? null;

        // The page may have changed while the script was loading.
        apply();
    };
    tawk.onStatusChange = (status) => (chatStatus.value = status);

    window.Tawk_LoadStart = new Date();

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://embed.tawk.to/${encodeURIComponent(config.propertyId)}/${encodeURIComponent(config.widgetId)}`;
    script.charset = 'UTF-8';
    script.setAttribute('crossorigin', '*');
    script.onerror = () => (chatFailed.value = true);
    document.head.appendChild(script);
}

/** Bring the loaded widget in line with what the current page wants. */
function apply(): void {
    if (!chatReady.value) {
        return;
    }

    const tawk = api();

    if (wanted === null) {
        tawk.hideWidget?.();

        return;
    }

    const identity = wanted.visitor?.email ?? '';

    if (identity !== appliedIdentity) {
        if (appliedIdentity !== '') {
            // Someone else was signed in (or has signed out). Start over
            // rather than let two people share one conversation.
            if (!tawk.logout) {
                window.location.reload();

                return;
            }

            const next = wanted;
            tawk.logout(() => {
                if (next.visitor) {
                    identify(next);
                }
            });
        } else {
            identify(wanted);
        }

        appliedIdentity = identity;
    } else if (identity !== '') {
        // Same student: file number and counselor may have changed.
        identify(wanted);
    }

    tawk.showWidget?.();
}

export function syncChatWidget(config: ChatWidgetConfig | null): void {
    if (typeof window === 'undefined') {
        return;
    }

    wanted = config;

    if (config !== null && !scriptRequested) {
        load(config);

        return;
    }

    apply();
}

/** Open the chat window, e.g. from the portal's Live Chat page. */
export function openChat(): void {
    api().maximize?.();
}

/** Keep the widget in step with every page Inertia shows. */
export function initializeChatWidget(): void {
    if (typeof window === 'undefined') {
        return;
    }

    router.on('navigate', (event) => {
        syncChatWidget(
            (event.detail.page.props.chat as ChatWidgetConfig | null) ?? null,
        );
    });
}
