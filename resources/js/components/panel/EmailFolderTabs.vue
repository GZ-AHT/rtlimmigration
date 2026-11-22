<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import type { MailFolderCounts } from '@/types';

/** Inbox / Sent / Drafts / Spam / Trash, scrolling sideways on a phone. */
const props = defineProps<{
    active: 'inbox' | 'sent' | 'drafts' | 'spam' | 'trash';
    urls: { inbox: string; sent: string };
    counts?: MailFolderCounts;
}>();

const tabs = [
    { key: 'inbox', label: 'Inbox', icon: 'inbox' },
    { key: 'sent', label: 'Sent', icon: 'send' },
    { key: 'drafts', label: 'Drafts', icon: 'draft' },
    { key: 'spam', label: 'Spam', icon: 'report' },
    { key: 'trash', label: 'Trash', icon: 'delete' },
] as const;

function href(key: string): string {
    return key === 'sent'
        ? props.urls.sent
        : `${props.urls.inbox}?folder=${key}`;
}

function badge(key: string): number {
    const c = props.counts;

    if (!c) {
        return 0;
    }

    return key === 'inbox'
        ? c.inbox_unread
        : key === 'drafts'
          ? c.drafts
          : key === 'spam'
            ? c.spam
            : key === 'trash'
              ? c.trash
              : 0;
}
</script>

<template>
    <nav
        class="mb-4 no-scrollbar flex gap-1 overflow-x-auto rounded-xl border border-border bg-card p-1"
        aria-label="Mail folders"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="href(tab.key)"
            class="flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] transition-colors"
            :class="
                active === tab.key
                    ? 'bg-primary/10 font-semibold text-primary'
                    : 'text-muted-foreground hover:bg-muted'
            "
            :aria-current="active === tab.key ? 'page' : undefined"
        >
            <AppIcon :name="tab.icon" :size="16" />
            {{ tab.label }}
            <span
                v-if="badge(tab.key) > 0"
                class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                :class="
                    tab.key === 'inbox'
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-muted-foreground'
                "
                >{{ badge(tab.key) }}</span
            >
        </Link>
    </nav>
</template>
