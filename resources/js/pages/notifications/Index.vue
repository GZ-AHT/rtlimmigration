<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import NotificationItem from '@/components/panel/NotificationItem.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { Button } from '@/components/ui/button';
import { index, readAll } from '@/routes/notifications';
import type { AppNotification, Paginated } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Notifications', href: index() }] },
});

const props = defineProps<{
    notifications: Paginated<AppNotification>;
    filter: 'all' | 'unread';
}>();

const tab = ref(props.filter);

watch(tab, (value) =>
    router.get(index().url, value === 'all' ? {} : { filter: value }, {
        preserveState: true,
        replace: true,
    }),
);
</script>

<template>
    <Head title="Notifications" />

    <PageHeader
        eyebrow="Notification centre"
        title="Notifications"
        description="Updates about your account, payments and applications."
    >
        <template #actions>
            <Button
                variant="outline"
                @click="
                    router.post(readAll().url, {}, { preserveScroll: true })
                "
                >Mark all as read</Button
            >
        </template>
    </PageHeader>

    <PanelTabs
        v-model="tab"
        label="Filter notifications"
        :tabs="[
            { key: 'all', label: 'All' },
            {
                key: 'unread',
                label: 'Unread',
                count: $page.props.notifications?.unread ?? 0,
            },
        ]"
    />

    <PanelCard flush>
        <EmptyState
            v-if="!notifications.data.length"
            icon="notifications_off"
            :title="
                filter === 'unread'
                    ? 'No unread notifications'
                    : 'No notifications yet'
            "
            description="Payment and application updates will appear here."
        />
        <div v-else class="divide-y p-1.5">
            <NotificationItem
                v-for="n in notifications.data"
                :key="n.id"
                :notification="n"
            />
        </div>
        <TablePagination :meta="notifications" />
    </PanelCard>
</template>
