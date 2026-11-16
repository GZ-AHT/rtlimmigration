<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { index } from '@/routes/portal/refunds';
import { history } from '@/routes/portal/payments';
import type { RefundRow } from '@/types';

defineProps<{ refunds: RefundRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Refunds', href: index() }] },
});
</script>

<template>
    <Head title="Refunds" />

    <PageHeader
        eyebrow="Student portal"
        title="Refunds"
        description="Your refund requests and where each one stands. To ask for a refund, open your payment history and choose the payment."
    >
        <template #actions>
            <Button variant="outline" as-child>
                <Link :href="history()">
                    <AppIcon name="history" :size="18" /> Payment history
                </Link>
            </Button>
        </template>
    </PageHeader>

    <PanelCard title="My refund requests">
        <EmptyState
            v-if="!refunds.length"
            icon="currency_exchange"
            title="No refund requests"
            description="If you ask for a refund, it appears here with its status."
        />
        <ul v-else class="divide-y">
            <li
                v-for="r in refunds"
                :key="r.id"
                class="py-4 first:pt-0 last:pb-0"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p
                            class="flex flex-wrap items-center gap-2 text-[13px] font-semibold"
                        >
                            {{ r.type }}
                            <StatusBadge
                                :label="r.status_label"
                                :tone="r.status_tone"
                            />
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ r.refund_number }} · {{ r.invoice_number }} ·
                            requested {{ formatDate(r.created_at) }}
                        </p>
                        <p class="mt-1 text-xs text-secondary-foreground">
                            {{ r.reason }}
                        </p>
                    </div>
                    <p class="font-display text-lg font-extrabold">
                        {{ r.amount_label }}
                    </p>
                </div>
                <p
                    v-if="r.status === 'processed'"
                    class="mt-2 flex items-center gap-2 rounded-lg bg-success-soft px-3 py-2 text-xs text-success"
                >
                    <AppIcon name="task_alt" :size="15" />
                    Paid back on {{ formatDate(r.processed_at)
                    }}<template v-if="r.method"> by {{ r.method }}</template
                    ><template v-if="r.transaction_reference">
                        · ref {{ r.transaction_reference }}</template
                    >.
                </p>
                <p
                    v-else-if="r.status === 'rejected'"
                    class="mt-2 rounded-lg bg-danger-soft px-3 py-2 text-xs text-danger"
                >
                    <b>Not approved:</b>
                    {{ r.decision_note ?? 'Please contact your counselor.' }}
                </p>
                <p
                    v-else
                    class="mt-2 flex items-center gap-2 rounded-lg bg-warning-soft px-3 py-2 text-xs text-warning"
                >
                    <AppIcon name="hourglass_top" :size="15" />
                    {{
                        r.status === 'approved'
                            ? 'Approved. Our Accounts team will pay it back shortly.'
                            : 'Being reviewed. We will notify you of the decision.'
                    }}
                </p>
            </li>
        </ul>
    </PanelCard>
</template>
