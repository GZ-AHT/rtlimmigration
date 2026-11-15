<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import NotificationItem from '@/components/panel/NotificationItem.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index as notificationsIndex, readAll } from '@/routes/notifications';

/** Bell with unread indicator and the latest notifications (REQ §1, §20). */
const page = usePage();
const unread = computed(() => page.props.notifications?.unread ?? 0);
const recent = computed(() => page.props.notifications?.recent ?? []);

function markAllRead(): void {
    router.post(readAll().url, {}, { preserveScroll: true });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="relative grid size-9 place-items-center rounded-lg text-muted-foreground hover:bg-accent hover:text-accent-foreground"
            :aria-label="
                unread ? `Notifications, ${unread} unread` : 'Notifications'
            "
        >
            <AppIcon name="notifications" />
            <span
                v-if="unread"
                class="absolute top-1 right-1 grid h-4 min-w-4 place-items-center rounded-full bg-primary px-1 text-[9px] font-bold text-primary-foreground"
                >{{ unread > 9 ? '9+' : unread }}</span
            >
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="w-[340px] max-w-[calc(100vw-2rem)] p-1.5"
        >
            <div class="flex items-center justify-between px-3 pt-1.5 pb-2">
                <p class="font-display text-sm font-bold">Notifications</p>
                <button
                    v-if="unread"
                    type="button"
                    class="text-xs font-semibold text-primary hover:underline"
                    @click="markAllRead"
                >
                    Mark all read
                </button>
            </div>
            <EmptyState
                v-if="!recent.length"
                icon="notifications_off"
                title="You're all caught up"
                description="Payment and application updates will appear here."
            />
            <div v-else class="flex flex-col">
                <NotificationItem
                    v-for="n in recent"
                    :key="n.id"
                    :notification="n"
                    compact
                />
            </div>
            <Link
                :href="notificationsIndex()"
                class="mt-1 block rounded-lg border-t px-3 py-2.5 text-center text-xs font-semibold text-muted-foreground hover:text-foreground"
            >
                View all notifications
            </Link>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
