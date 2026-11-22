<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import NotificationItem from '@/components/panel/NotificationItem.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { index, preferences, readAll } from '@/routes/notifications';
import type { AppNotification, Paginated } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Notifications', href: index() }] },
});

const props = defineProps<{
    notifications: Paginated<AppNotification>;
    filter: 'all' | 'unread';
    category: string | null;
    categories: {
        key: string;
        label: string;
        unread: number;
        in_app: boolean;
        email: boolean;
    }[];
    customised: boolean;
}>();

const tab = ref(props.filter);
const kind = ref(props.category ?? 'all');

function load(): void {
    router.get(
        index().url,
        {
            ...(tab.value === 'all' ? {} : { filter: tab.value }),
            ...(kind.value === 'all' ? {} : { category: kind.value }),
        },
        { preserveState: true, replace: true },
    );
}

watch([tab, kind], load);

// Own choices: show in the centre (In-app) and send an email copy (Email).
const choices = useForm({
    preferences: Object.fromEntries(
        props.categories.map((c) => [
            c.key,
            { in_app: c.in_app, email: c.email },
        ]),
    ) as Record<string, { in_app: boolean; email: boolean }>,
});
const settingsOpen = ref(false);

function savePreferences(): void {
    choices.put(preferences().url, { preserveScroll: true });
}

function resetPreferences(): void {
    router.put(preferences().url, { reset: true }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notifications" />

    <PageHeader
        eyebrow="Notification centre"
        title="Notifications"
        description="Updates about your account, payments and applications."
    >
        <template #actions>
            <Button variant="outline" @click="settingsOpen = !settingsOpen"
                >My preferences</Button
            >
            <Button
                variant="outline"
                @click="
                    router.post(readAll().url, {}, { preserveScroll: true })
                "
                >Mark all as read</Button
            >
        </template>
    </PageHeader>

    <PanelCard
        v-if="settingsOpen"
        class="mb-4"
        title="My notification choices"
        description="Choose where each kind of notification reaches you. Security alerts always appear here."
        flush
    >
        <ul class="divide-y">
            <li
                v-for="c in categories"
                :key="c.key"
                class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-[13px]"
            >
                <span class="font-semibold">{{ c.label }}</span>
                <span class="flex gap-5">
                    <label class="flex items-center gap-2">
                        <Checkbox
                            :model-value="choices.preferences[c.key].in_app"
                            @update:model-value="
                                (v) =>
                                    (choices.preferences[c.key].in_app =
                                        v === true)
                            "
                        />
                        In-app
                    </label>
                    <label class="flex items-center gap-2">
                        <Checkbox
                            :model-value="choices.preferences[c.key].email"
                            @update:model-value="
                                (v) =>
                                    (choices.preferences[c.key].email =
                                        v === true)
                            "
                        />
                        Email
                    </label>
                </span>
            </li>
        </ul>
        <div class="flex flex-wrap justify-end gap-2 border-t px-5 py-3">
            <Button
                v-if="customised"
                variant="outline"
                @click="resetPreferences"
                >Reset to role defaults</Button
            >
            <Button :disabled="choices.processing" @click="savePreferences"
                >Save choices</Button
            >
        </div>
    </PanelCard>

    <PanelTabs
        v-model="kind"
        label="Notification type"
        :tabs="[
            { key: 'all', label: 'All types' },
            ...categories.map((c) => ({
                key: c.key,
                label: c.label,
                count: c.unread || undefined,
            })),
        ]"
    />

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
