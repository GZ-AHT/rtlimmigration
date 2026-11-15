<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import { relativeTime } from '@/lib/format';
import { read } from '@/routes/notifications';
import type { AppNotification } from '@/types';

/** A notification row; clicking marks it read and follows its link. */
const props = defineProps<{
    notification: AppNotification;
    compact?: boolean;
}>();

function open(): void {
    router.post(read(props.notification.id).url, {}, { preserveScroll: true });
}
</script>

<template>
    <button
        type="button"
        class="flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-muted"
        @click="open"
    >
        <span
            class="grid size-8 shrink-0 place-items-center rounded-lg"
            :class="
                notification.read
                    ? 'bg-muted text-muted-foreground'
                    : 'bg-accent text-accent-foreground'
            "
        >
            <AppIcon :name="notification.icon" :size="17" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="flex items-center gap-2">
                <span
                    class="truncate text-[13px]"
                    :class="notification.read ? 'font-medium' : 'font-bold'"
                >
                    {{ notification.title }}
                </span>
                <span
                    v-if="!notification.read"
                    class="size-1.5 shrink-0 rounded-full bg-primary"
                    aria-label="Unread"
                />
            </span>
            <span
                class="block text-xs text-muted-foreground"
                :class="{ truncate: compact }"
                >{{ notification.body }}</span
            >
            <span class="mt-0.5 block text-[11px] text-muted-foreground">{{
                relativeTime(notification.created_at)
            }}</span>
        </span>
    </button>
</template>
