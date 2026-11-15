<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BarChart from '@/components/panel/BarChart.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatCard from '@/components/panel/StatCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { relativeTime } from '@/lib/format';
import { dashboard } from '@/routes/accounts';
import type { PaymentRow, Stat } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Overview', href: dashboard() }],
    },
});

defineProps<{
    stats: Stat[];
    monthly: { label: string; value: number; display: string }[];
    methods: { label: string; count: number; total: string; share: number }[];
    queue: PaymentRow[];
}>();
</script>

<template>
    <Head title="Accounts dashboard" />

    <PageHeader
        eyebrow="Accounts"
        title="Accounts dashboard"
        description="Revenue, dues and the payments waiting for you."
    />

    <div class="mb-6 grid grid-cols-2 gap-2.5 sm:gap-4 xl:grid-cols-5">
        <StatCard
            v-for="stat in stats"
            :key="stat.key"
            :label="stat.label"
            :value="stat.value"
            :icon="stat.icon"
            :hint="stat.hint"
            hint-tone="neutral"
        />
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(300px,1fr)]">
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard
                title="Monthly revenue"
                description="Confirmed payments over the last 12 months."
            >
                <BarChart
                    :data="monthly"
                    caption="Monthly revenue, last 12 months"
                />
            </PanelCard>

            <PanelCard
                title="Waiting for verification"
                description="Oldest first."
            >
                <EmptyState
                    v-if="!queue.length"
                    icon="task_alt"
                    title="Nothing waiting"
                    description="Payments students submit appear here for you to verify."
                />
                <ul v-else class="divide-y">
                    <li
                        v-for="p in queue"
                        :key="p.id"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0">
                            <Link
                                v-if="p.url"
                                :href="p.url"
                                class="block truncate text-[13px] font-semibold hover:text-primary"
                                >{{ p.student }} · {{ p.amount_label }}</Link
                            >
                            <p
                                class="truncate text-[11px] text-muted-foreground"
                            >
                                {{ p.method }} · {{ p.invoice_number }} ·
                                {{ relativeTime(p.created_at) }}
                            </p>
                        </div>
                        <StatusBadge
                            :label="p.status_label"
                            :tone="p.status_tone"
                        />
                    </li>
                </ul>
            </PanelCard>
        </div>

        <PanelCard
            title="Payment methods"
            description="Share of confirmed revenue."
            class="self-start"
        >
            <EmptyState
                v-if="!methods.length"
                icon="credit_card"
                title="No confirmed payments yet"
            />
            <ul v-else class="flex flex-col gap-4">
                <li v-for="m in methods" :key="m.label">
                    <div class="mb-1.5 flex justify-between gap-3 text-[13px]">
                        <span class="truncate font-medium">{{ m.label }}</span>
                        <b>{{ m.total }}</b>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-primary"
                            :style="{ width: `${m.share}%` }"
                        />
                    </div>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        {{ m.count }} payment{{ m.count === 1 ? '' : 's' }} ·
                        {{ m.share }}%
                    </p>
                </li>
            </ul>
        </PanelCard>
    </div>
</template>
