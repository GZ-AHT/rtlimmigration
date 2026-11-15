<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import BarChart from '@/components/panel/BarChart.vue';
import DataTable from '@/components/panel/DataTable.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatCard from '@/components/panel/StatCard.vue';
import { formatDate, relativeTime } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import type { ActivityEntry, Stat, TableColumn } from '@/types';

type Registration = {
    id: number;
    name: string;
    email: string;
    registered_at: string | null;
    url: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Overview', href: dashboard() }],
    },
});

defineProps<{
    stats: Stat[];
    recentRegistrations: Registration[];
    recentActivity: ActivityEntry[] | null;
    quickActions: { label: string; icon: string; href: string }[];
    monthlyRevenue: { label: string; value: number; display: string }[] | null;
}>();

const columns: TableColumn<Registration>[] = [
    { key: 'name', label: 'Student' },
    { key: 'email', label: 'Email', hideBelow: 'sm' },
    {
        key: 'registered_at',
        label: 'Registered',
        align: 'right',
        format: (row) => formatDate(row.registered_at),
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <PageHeader
        eyebrow="Super admin"
        title="Dashboard overview"
        description="A live summary of students, counselors and platform activity."
    />

    <div class="mb-6 grid grid-cols-2 gap-2.5 sm:gap-4 xl:grid-cols-4">
        <StatCard
            v-for="stat in stats"
            :key="stat.key"
            :label="stat.label"
            :value="stat.value"
            :icon="stat.icon"
            :hint="stat.hint"
        />
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(300px,1fr)]">
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard
                v-if="monthlyRevenue"
                title="Monthly revenue"
                description="Confirmed payments over the last 12 months."
            >
                <BarChart
                    :data="monthlyRevenue"
                    caption="Monthly revenue, last 12 months"
                />
            </PanelCard>

            <PanelCard
                flush
                title="Recent student registrations"
                description="The latest clients who created an account."
            >
                <DataTable
                    class="mt-4"
                    :columns="columns"
                    :rows="recentRegistrations"
                    caption="Recent student registrations"
                    empty-icon="school"
                    empty-title="No students yet"
                    empty-description="New registrations from the website and counselors will appear here."
                >
                    <template #cell-name="{ row }">
                        <Link
                            v-if="row.url"
                            :href="row.url"
                            class="font-semibold text-foreground hover:text-primary"
                            >{{ row.name }}</Link
                        >
                        <span v-else class="font-semibold text-foreground">{{
                            row.name
                        }}</span>
                    </template>
                </DataTable>
            </PanelCard>

            <PanelCard
                v-if="recentActivity"
                title="Recent activity"
                description="Latest changes across the platform."
            >
                <EmptyState
                    v-if="!recentActivity.length"
                    icon="history"
                    title="No activity yet"
                />
                <ul v-else class="divide-y">
                    <li
                        v-for="entry in recentActivity"
                        :key="entry.id"
                        class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <span
                            class="grid size-[38px] shrink-0 place-items-center rounded-[9px] bg-accent text-accent-foreground"
                        >
                            <AppIcon
                                :name="
                                    entry.log === 'roles'
                                        ? 'admin_panel_settings'
                                        : 'person'
                                "
                                :size="18"
                            />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold">
                                {{ entry.subject ?? 'Record' }}
                                <span class="font-normal text-muted-foreground"
                                    >· {{ entry.summary }}</span
                                >
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                {{
                                    entry.causer
                                        ? `by ${entry.causer}`
                                        : 'by system'
                                }}
                                · {{ relativeTime(entry.created_at) }}
                            </p>
                        </div>
                    </li>
                </ul>
            </PanelCard>
        </div>

        <PanelCard title="Quick actions" class="self-start">
            <div class="grid grid-cols-2 gap-2.5">
                <Link
                    v-for="action in quickActions"
                    :key="action.href"
                    :href="action.href"
                    class="flex flex-col items-start gap-2.5 rounded-xl border p-3.5 text-[13px] font-semibold hover:border-ring hover:bg-accent hover:text-accent-foreground"
                >
                    <span
                        class="grid size-9 place-items-center rounded-[9px] bg-accent text-accent-foreground"
                    >
                        <AppIcon :name="action.icon" :size="18" />
                    </span>
                    {{ action.label }}
                </Link>
            </div>
        </PanelCard>
    </div>
</template>
