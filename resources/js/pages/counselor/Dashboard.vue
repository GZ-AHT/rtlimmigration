<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatCard from '@/components/panel/StatCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { formatDateTime, relativeTime } from '@/lib/format';
import { dashboard } from '@/routes/counselor';
import type { PaymentRow, Stat } from '@/types';

type FollowUp = {
    id: number;
    due_at: string;
    overdue: boolean;
    note: string | null;
    lead: string;
    url: string;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Overview', href: dashboard() }],
    },
});

defineProps<{
    stats: Stat[];
    followUps: FollowUp[];
    payments: PaymentRow[];
}>();
</script>

<template>
    <Head title="My dashboard" />

    <PageHeader
        eyebrow="Counselor"
        title="My dashboard"
        description="Your leads, students, payment requests and follow-ups in one place."
    />

    <div class="mb-6 grid grid-cols-2 gap-2.5 sm:gap-4 xl:grid-cols-4">
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

    <div class="grid gap-4 lg:grid-cols-2">
        <PanelCard
            title="Follow-ups due"
            description="Due today or overdue, soonest first."
        >
            <EmptyState
                v-if="!followUps.length"
                icon="event_available"
                title="Nothing due"
                description="Follow-ups you schedule on leads appear here when they fall due."
            />
            <ul v-else class="divide-y">
                <li
                    v-for="f in followUps"
                    :key="f.id"
                    class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                >
                    <span
                        class="grid size-[38px] shrink-0 place-items-center rounded-[9px]"
                        :class="
                            f.overdue
                                ? 'bg-danger-soft text-danger'
                                : 'bg-accent text-accent-foreground'
                        "
                    >
                        <AppIcon name="event" :size="18" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="f.url"
                            class="block truncate text-[13px] font-semibold hover:text-primary"
                            >{{ f.lead }}</Link
                        >
                        <p class="truncate text-[11px] text-muted-foreground">
                            {{ formatDateTime(f.due_at)
                            }}{{ f.overdue ? ' · overdue' : '' }}
                            <template v-if="f.note"> · {{ f.note }}</template>
                        </p>
                    </div>
                </li>
            </ul>
        </PanelCard>

        <PanelCard
            title="Recent payments"
            description="Latest payment activity for your students."
        >
            <EmptyState
                v-if="!payments.length"
                icon="payments"
                title="No payments yet"
                description="Payments against your payment requests appear here."
            />
            <ul v-else class="divide-y">
                <li
                    v-for="p in payments"
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
                        <p class="truncate text-[11px] text-muted-foreground">
                            {{ p.type }} · {{ p.invoice_number }} ·
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
</template>
