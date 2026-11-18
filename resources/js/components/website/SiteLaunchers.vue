<script setup lang="ts">
import { computed } from 'vue';
import ChatLauncher from '@/components/chat/ChatLauncher.vue';
import { useSite } from '@/composables/useSite';

/**
 * Figma "WhatsApp and live chat" (node 12:47): two pill buttons, fixed at
 * the bottom right of every website page. WhatsApp shows when a number is
 * set in Site settings; live chat when website chat is switched on.
 */
const { whatsappUrl } = useSite();
const whatsapp = computed(() => whatsappUrl.value);
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
            class="pointer-events-auto flex h-[52px] items-center gap-3 rounded-full border border-site-whatsapp bg-site-whatsapp pr-5 pl-[17px] text-[14px] leading-5 font-semibold text-white shadow-[0_12px_30px_rgba(0,0,0,0.12)] transition-transform hover:scale-[1.03] sm:w-[178px]"
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
</template>
