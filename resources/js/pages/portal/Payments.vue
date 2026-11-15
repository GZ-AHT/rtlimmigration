<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import PaymentForm from '@/components/payments/PaymentForm.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDate } from '@/lib/format';
import { history, index } from '@/routes/portal/payments';
import type {
    MoneyTotals,
    PaymentMethodOption,
    PaymentRequestRow,
    PaymentRow,
} from '@/types';

type RequestItem = PaymentRequestRow & {
    outstanding_decimal: string;
    awaiting: PaymentRow[];
    can_pay: boolean;
    pay_url: string;
};

defineProps<{
    totals: MoneyTotals;
    requests: RequestItem[];
    methods: PaymentMethodOption[];
    evidence: { max_mb: number; extensions: string[] };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Payments', href: index() }] },
});

const paying = ref<RequestItem | null>(null);
</script>

<template>
    <Head title="Payments" />

    <PageHeader
        eyebrow="Student portal"
        title="Payments"
        description="Your fees and invoices. After you pay, our Accounts team verifies the payment and you are notified."
    >
        <template #actions>
            <Button variant="outline" as-child>
                <Link :href="history()">
                    <AppIcon name="history" :size="18" /> Payment history
                </Link>
            </Button>
        </template>
    </PageHeader>

    <div class="mb-6 grid grid-cols-3 gap-2.5 sm:gap-4">
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Total fees</p>
            <p class="mt-1 font-display text-lg font-extrabold sm:text-2xl">
                {{ totals.total }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Paid</p>
            <p
                class="mt-1 font-display text-lg font-extrabold text-success sm:text-2xl"
            >
                {{ totals.paid }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Due</p>
            <p
                class="mt-1 font-display text-lg font-extrabold sm:text-2xl"
                :class="{ 'text-danger': totals.due_minor > 0 }"
            >
                {{ totals.due }}
            </p>
        </div>
    </div>

    <PanelCard title="My invoices">
        <EmptyState
            v-if="!requests.length"
            icon="receipt_long"
            title="No payment requests yet"
            description="When your counselor raises a fee, it appears here."
        />
        <ul v-else class="divide-y">
            <li
                v-for="r in requests"
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
                            {{ r.invoice_number }}
                            <template v-if="r.file_number">
                                · {{ r.file_number }}</template
                            >
                            <template v-if="r.due_date">
                                ·
                                <span
                                    :class="{
                                        'font-semibold text-danger': r.overdue,
                                    }"
                                    >due {{ formatDate(r.due_date) }}</span
                                ></template
                            >
                        </p>
                        <p
                            v-if="r.description"
                            class="mt-1 text-xs text-secondary-foreground"
                        >
                            {{ r.description }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-display text-lg font-extrabold">
                            {{ r.amount_label }}
                        </p>
                        <p
                            v-if="r.outstanding && r.outstanding !== r.amount"
                            class="text-xs text-muted-foreground"
                        >
                            {{ r.outstanding_label }} left
                        </p>
                        <Button
                            v-if="r.can_pay"
                            size="sm"
                            class="mt-2"
                            @click="paying = r"
                        >
                            Pay now
                        </Button>
                    </div>
                </div>
                <p
                    v-for="p in r.awaiting"
                    :key="p.id"
                    class="mt-2 flex items-center gap-2 rounded-lg bg-warning-soft px-3 py-2 text-xs text-warning"
                >
                    <AppIcon name="hourglass_top" :size="15" />
                    {{ p.amount_label }} by {{ p.method }} is being verified by
                    Accounts.
                </p>
            </li>
        </ul>
    </PanelCard>

    <Dialog :open="paying !== null" @update:open="(v) => !v && (paying = null)">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto rounded-[14px] sm:max-w-[520px]"
        >
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">
                    Pay {{ paying?.type }}
                </DialogTitle>
                <DialogDescription class="text-xs">
                    {{ paying?.invoice_number }} · Pay using one of the methods
                    below, then enter the details here so we can verify it.
                </DialogDescription>
            </DialogHeader>
            <p
                v-if="!methods.length"
                class="rounded-lg bg-muted p-3 text-[13px] text-muted-foreground"
            >
                Online submission isn't available yet. Please pay at our office
                or contact your counselor.
            </p>
            <PaymentForm
                v-else-if="paying"
                :key="paying.id"
                :submit-url="paying.pay_url"
                :methods="methods"
                :outstanding="paying.outstanding_decimal"
                :currency="paying.currency"
                :evidence="evidence"
                submit-label="Submit for verification"
                @done="paying = null"
            />
        </DialogContent>
    </Dialog>
</template>
