<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import GuestChatPanel from '@/components/chat/GuestChatPanel.vue';
import type { GuestChatState } from '@/components/chat/GuestChatPanel.vue';
import { headers } from '@/composables/useMessageThread';
import { widget } from '@/routes/chat/guest';

/**
 * Floating "Chat with us" button on every public website page (REQ §11 F).
 * The panel loads the visitor's chat only when opened, and the thread inside
 * polls only while the panel is open, so idle pages cost the server nothing.
 * Hidden on the full /chat page and whenever website chat is switched off.
 */
const page = usePage();
const enabled = computed(
    () => page.props.guestChat === true && !page.url.startsWith('/chat'),
);

const STORAGE_KEY = 'rtl-chat-open';
const open = ref(false);
const state = ref<GuestChatState | null>(null);
const loading = ref(false);
const failed = ref(false);
const panel = ref<HTMLElement | null>(null);
const button = ref<HTMLButtonElement | null>(null);

async function load(): Promise<void> {
    loading.value = true;
    failed.value = false;

    try {
        const response = await fetch(widget.url(), {
            headers: headers(),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        state.value = (await response.json()) as GuestChatState;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

function remember(value: boolean): void {
    try {
        sessionStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    } catch {
        // Private mode: the panel just won't reopen on the next page.
    }
}

async function toggle(): Promise<void> {
    open.value = !open.value;
    remember(open.value);

    if (open.value) {
        if (!state.value) {
            await load();
        }

        await nextTick();
        panel.value?.focus();
    } else {
        button.value?.focus();
    }
}

function close(): void {
    if (open.value) {
        void toggle();
    }
}

function onKey(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKey);

    try {
        // Keep an ongoing conversation open while the visitor browses pages.
        if (enabled.value && sessionStorage.getItem(STORAGE_KEY) === '1') {
            void toggle();
        }
    } catch {
        // Storage unavailable: start closed.
    }
});

onBeforeUnmount(() => document.removeEventListener('keydown', onKey));

// Switched off by an admin, or moved to the /chat page: close quietly.
watch(enabled, (value) => {
    if (!value) {
        open.value = false;
    }
});
</script>

<template>
    <div
        v-if="enabled"
        class="fixed right-4 bottom-4 z-50 sm:right-6 sm:bottom-6"
    >
        <div
            v-show="open"
            id="chat-popup"
            ref="panel"
            role="dialog"
            aria-modal="false"
            aria-labelledby="chat-popup-heading"
            tabindex="-1"
            class="fixed inset-0 flex flex-col bg-white outline-none sm:absolute sm:inset-auto sm:right-0 sm:bottom-[76px] sm:max-h-[min(640px,calc(100vh-120px))] sm:w-[380px] sm:rounded-[18px] sm:border sm:border-site-line sm:shadow-2xl"
        >
            <div
                class="flex items-center justify-between gap-3 bg-site-red px-4 py-3 text-white sm:rounded-t-[18px]"
            >
                <span class="flex items-center gap-2 text-sm font-semibold">
                    <AppIcon name="forum" :size="20" /> Chat with us
                </span>
                <button
                    type="button"
                    class="grid size-9 place-items-center rounded-full hover:bg-white/15"
                    aria-label="Close chat"
                    @click="close"
                >
                    <AppIcon name="close" :size="20" />
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                <p
                    v-if="loading && !state"
                    class="py-10 text-center text-sm text-site-muted"
                >
                    Loading…
                </p>
                <div
                    v-else-if="failed && !state"
                    class="py-10 text-center text-sm text-site-muted"
                >
                    <p>The chat could not load.</p>
                    <button
                        type="button"
                        class="mt-3 font-semibold text-site-red underline"
                        @click="load"
                    >
                        Try again
                    </button>
                </div>
                <!-- Unmounted while closed, so the thread stops polling. -->
                <GuestChatPanel
                    v-else-if="state && open"
                    :key="state.thread ? 'thread' : 'start'"
                    v-bind="state"
                    compact
                    @started="load"
                />
            </div>
        </div>

        <button
            ref="button"
            type="button"
            class="grid size-[60px] place-items-center rounded-full bg-site-red text-white shadow-xl transition-transform hover:scale-105 hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none"
            :class="open ? 'hidden sm:grid' : ''"
            :aria-expanded="open"
            aria-controls="chat-popup"
            :aria-label="open ? 'Close chat' : 'Chat with us'"
            @click="toggle"
        >
            <AppIcon :name="open ? 'close' : 'chat'" :size="28" />
        </button>
    </div>
</template>
