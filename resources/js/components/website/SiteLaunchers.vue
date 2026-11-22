<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ChatLauncher from '@/components/chat/ChatLauncher.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import { openGuestChat } from '@/composables/useGuestChat';
import { useSite } from '@/composables/useSite';

/**
 * Figma "WhatsApp and live chat" (node 12:47): two pill buttons, fixed at
 * the bottom right of every website page. WhatsApp shows when a number is
 * set in Site settings; live chat when website chat is switched on.
 */
const { whatsappUrl, text } = useSite();
const whatsapp = computed(() => whatsappUrl.value);
const page = usePage();

// Same rule as the chat panel: switched on by an admin, and not on /chat itself.
const chatAvailable = computed(
    () => page.props.guestChat === true && !page.url.startsWith('/chat'),
);

const ACTION =
    'flex min-h-14 min-w-0 flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-1 text-site-ink transition-[transform,background-color] hover:bg-site-sand active:scale-95 active:bg-site-sand focus-visible:ring-2 focus-visible:ring-site-red/40 focus-visible:outline-none motion-reduce:transition-none';
const TILE = 'grid size-8 place-items-center rounded-full';
const LABEL =
    'max-w-full truncate text-[11px] leading-[14px] font-semibold tracking-[-0.01em]';
const phone = computed(() => text('general', 'phone').replace(/\s/g, ''));
</script>

<template>
    <div
        class="pointer-events-none fixed right-4 bottom-4 z-40 flex flex-col items-end gap-3 sm:right-6 sm:bottom-6"
    >
        <a
            v-if="whatsapp"
            :href="whatsapp"
            target="_blank"
            rel="noopener noreferrer"
            class="pointer-events-auto flex size-12 items-center justify-center gap-3 rounded-full border border-site-whatsapp bg-site-whatsapp shadow-[0_12px_30px_rgba(0,0,0,0.12)] transition-transform hover:scale-105 active:scale-95 max-sm:hidden sm:h-13 sm:w-[178px] sm:justify-start sm:px-4"
            aria-label="Chat on WhatsApp (opens WhatsApp)"
        >
            <span class="grid size-6 shrink-0 place-items-center">
                <img
                    src="/images/site/icon-whatsapp.svg"
                    alt=""
                    width="19.2"
                    height="19.18"
                />
            </span>
            <span class="hidden sm:inline">WhatsApp</span>
        </a>
        <ChatLauncher class="pointer-events-auto" />
    </div>

    <!-- Phones only: the quickest ways to reach an adviser, one tap each. -->
    <nav
        aria-label="Quick contact"
        class="fixed inset-x-0 bottom-0 z-30 grid auto-cols-fr grid-flow-col gap-1 border-t border-site-line bg-white/90 px-2 pt-1.5 pb-[max(0.375rem,env(safe-area-inset-bottom))] shadow-[0_-6px_20px_rgba(0,0,0,0.06)] backdrop-blur-[20px] sm:hidden"
    >
        <a
            v-if="phone"
            :href="`tel:${phone}`"
            :class="ACTION"
            aria-label="Call us"
        >
            <span :class="[TILE, 'bg-site-sand text-site-red']">
                <AppIcon name="call" :size="20" />
            </span>
            <span :class="LABEL">Call</span>
        </a>
        <a
            v-if="whatsapp"
            :href="whatsapp"
            target="_blank"
            rel="noopener noreferrer"
            :class="ACTION"
            aria-label="Chat on WhatsApp (opens WhatsApp)"
        >
            <span :class="[TILE, 'bg-site-whatsapp']">
                <img
                    src="/images/site/icon-whatsapp.svg"
                    alt=""
                    width="18"
                    height="18"
                />
            </span>
            <span :class="LABEL">WhatsApp</span>
        </a>
        <Link href="/contact" :class="ACTION" aria-label="Book a consultation">
            <span :class="[TILE, 'bg-site-red text-white']">
                <AppIcon name="event_available" :size="20" />
            </span>
            <span :class="[LABEL, 'text-site-red']">Book now</span>
        </Link>
        <button
            v-if="chatAvailable"
            type="button"
            :class="ACTION"
            aria-label="Open live chat"
            aria-controls="chat-popup"
            @click="openGuestChat"
        >
            <span :class="[TILE, 'bg-site-sand text-site-red']">
                <AppIcon name="chat_bubble" :size="20" />
            </span>
            <span :class="LABEL">Live chat</span>
        </button>
    </nav>
</template>
