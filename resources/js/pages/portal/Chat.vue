<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MessageThread from '@/components/chat/MessageThread.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { chatFailed, chatReady, chatStatus, openChat } from '@/lib/tawk';
import { chat } from '@/routes/portal';
import {
    index as messagesIndex,
    store as messagesStore,
} from '@/routes/portal/chat/messages';
import type { ChatComposerLimits, PortalThread, Tone } from '@/types';

defineProps<{
    available: boolean;
    counselor: string | null;
    thread: (PortalThread & { unread: number; status: string | null }) | null;
    composer: ChatComposerLimits;
    history: {
        id: number;
        kind_label: string;
        kind_icon: string;
        status_label: string;
        status_tone: Tone;
        preview: string | null;
        message_count: number;
        started_at: string | null;
    }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Live chat', href: chat() }] },
});

const me = computed(() => usePage().props.auth.user?.name ?? 'You');
</script>

<template>
    <Head title="Live chat" />

    <PageHeader
        eyebrow="Student portal"
        title="Live chat"
        :description="
            counselor
                ? `Chat with ${counselor} or our support team.`
                : 'Chat with our support team.'
        "
    />

    <PanelCard
        v-if="thread"
        class="mb-4"
        :title="
            counselor ? `Messages with ${counselor}` : 'Messages with our team'
        "
        :description="
            composer.max_files > 0
                ? 'Write any time. Your counselor replies here, and you can attach documents.'
                : 'Write any time. Your counselor replies here.'
        "
    >
        <template v-if="thread.unread" #actions>
            <StatusBadge :label="`${thread.unread} new`" tone="info" />
        </template>
        <MessageThread
            :thread="thread"
            :messages-url="messagesIndex.url()"
            :send-url="messagesStore.url()"
            perspective="student"
            :sender-name="me"
            :composer="composer"
            :can-reply="true"
            empty-text="No messages yet. Ask your counselor anything about your visa, admission, payments or documents."
        />
    </PanelCard>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <PanelCard>
            <EmptyState
                v-if="!available"
                icon="forum"
                title="Live chat isn't available right now"
                description="Please contact your counselor by email or phone instead."
            />
            <div v-else class="flex flex-col items-start gap-4">
                <span
                    class="grid size-12 place-items-center rounded-full bg-accent text-accent-foreground"
                    ><AppIcon name="forum" :size="24"
                /></span>
                <div>
                    <p class="font-display text-lg font-bold">
                        <template v-if="chatStatus === 'online'"
                            >We're online</template
                        >
                        <template v-else-if="chatStatus"
                            >No one is online right now</template
                        >
                        <template v-else>Live chat</template>
                    </p>
                    <p class="mt-1 text-[13px] text-muted-foreground">
                        <template v-if="chatStatus && chatStatus !== 'online'"
                            >Leave a message and we'll reply by email.</template
                        >
                        <template v-else
                            >Your name, student number and file number are
                            shared with whoever answers, so you don't need to
                            repeat them.</template
                        >
                    </p>
                </div>
                <Button :disabled="!chatReady" @click="openChat">
                    {{
                        chatStatus && chatStatus !== 'online'
                            ? 'Leave a message'
                            : 'Start chat'
                    }}
                </Button>
                <p v-if="chatFailed" class="text-xs text-danger" role="alert">
                    The chat couldn't load. An ad or tracker blocker may be
                    stopping it.
                </p>
                <p v-else-if="!chatReady" class="text-xs text-muted-foreground">
                    Loading chat…
                </p>
            </div>
        </PanelCard>

        <PanelCard
            title="Your live chats"
            description="Recent Tawk.to live chats and offline messages linked to your account."
        >
            <EmptyState
                v-if="!history.length"
                icon="history"
                title="No live chats yet"
            />
            <ul v-else class="divide-y">
                <li
                    v-for="h in history"
                    :key="h.id"
                    class="flex items-start gap-3 py-3 first:pt-0 last:pb-0"
                >
                    <AppIcon
                        :name="h.kind_icon"
                        :size="18"
                        class="mt-0.5 text-muted-foreground"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-semibold">
                            {{ h.preview ?? h.kind_label }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            {{ formatDateTime(h.started_at) }}
                            <template v-if="h.message_count > 1">
                                · {{ h.message_count }} messages</template
                            >
                        </p>
                    </div>
                    <StatusBadge
                        :label="h.status_label"
                        :tone="h.status_tone"
                    />
                </li>
            </ul>
        </PanelCard>
    </div>
</template>
