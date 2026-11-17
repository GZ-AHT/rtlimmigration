<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import GuestChatPanel from '@/components/chat/GuestChatPanel.vue';
import type { GuestChatState } from '@/components/chat/GuestChatPanel.vue';

/** Full-page website chat (REQ §11 F). The same chat opens from the popup on every website page. */
defineProps<GuestChatState>();
</script>

<template>
    <Head title="Chat with us" />

    <section
        class="bg-site-surface py-6 lg:py-10"
        aria-labelledby="chat-heading"
    >
        <div class="mx-auto max-w-[860px] px-4 sm:px-6">
            <div
                class="rounded-[20px] border border-site-line bg-white px-5 py-6 sm:px-8 sm:py-8"
            >
                <GuestChatPanel v-bind="$props" @started="router.reload()" />
            </div>
        </div>
    </section>
</template>
