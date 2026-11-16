<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
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
import {
    easyCheckoutSupported,
    loadEasyCheckout,
    openEasyCheckout,
} from '@/lib/easyCheckout';
import { formatDate } from '@/lib/format';
import { due, history, index } from '@/routes/portal/payments';
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
    invoice_url: string;
    online_url: string | null;
};

const props = defineProps<{
    dueOnly: boolean;
    gateway: {
        label: string;
        mode: 'hosted' | 'easy';
        script: string | null;
        endpoint: string | null;
        csrf: string | null;
    } | null;
    totals: MoneyTotals;
    requests: RequestItem[];
    methods: PaymentMethodOption[];
    evidence: { max_mb: number; extensions: string[] };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Payments', href: index() }] },
});

const paying = ref<RequestItem | null>(null);
const redirecting = ref(false);

// SSLCommerz Easy Checkout: the gateway opens in a popup over this page.
// See lib/easyCheckout.ts for how their script has to be driven.
const easyReady = ref(false);

onMounted(async () => {
    const gateway = props.gateway;

    if (
        gateway?.mode === 'easy' &&
        gateway.script &&
        gateway.endpoint &&
        easyCheckoutSupported()
    ) {
        easyReady.value = await loadEasyCheckout(
            gateway.script,
            gateway.endpoint,
        );
    }
});

/**
 * Starts an online payment: in the Easy Checkout popup when it is
 * available, otherwise on the gateway's own page. Either way, coming back
 * never marks anything paid: the gateway confirms to our server separately.
 */
function payOnline(request: RequestItem): void {
    if (
        easyReady.value &&
        props.gateway?.csrf &&
        openEasyCheckout(request.id, props.gateway.csrf)
    ) {
        return;
    }

    if (!request.online_url) {
        return;
    }

    router.post(
        request.online_url,
        {},
        {
            preserveScroll: true,
            onStart: () => (redirecting.value = true),
            onError: (e) => {
                redirecting.value = false;
                toast.error(
                    Object.values(e)[0] ?? 'Online payment is unavailable.',
                );
            },
        },
    );
}
</script>

<template>
    <Head :title="props.dueOnly ? 'Due payments' : 'Payments'" />

    <PageHeader
        eyebrow="Student portal"
        :title="props.dueOnly ? 'Due payments' : 'Payments'"
        :description="
            props.dueOnly
                ? 'What you still owe, earliest deadline first.'
                : 'Your fees and invoices. After you pay, our Accounts team verifies the payment and you are notified.'
        "
    >
        <template #actions>
            <Button v-if="!props.dueOnly" variant="outline" as-child>
                <Link :href="due()">
                    <AppIcon name="pending_actions" :size="18" /> Due payments
                </Link>
            </Button>
            <Button v-else variant="outline" as-child>
                <Link :href="index()">
                    <AppIcon name="payments" :size="18" /> All invoices
                </Link>
            </Button>
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

    <PanelCard :title="props.dueOnly ? 'Still to pay' : 'My invoices'">
        <EmptyState
            v-if="!requests.length"
            :icon="props.dueOnly ? 'task_alt' : 'receipt_long'"
            :title="
                props.dueOnly ? 'Nothing is due' : 'No payment requests yet'
            "
            :description="
                props.dueOnly
                    ? 'You have no outstanding payments.'
                    : 'When your counselor raises a fee, it appears here.'
            "
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
                    <div class="ml-auto text-right">
                        <p class="font-display text-lg font-extrabold">
                            {{ r.amount_label }}
                        </p>
                        <p
                            v-if="r.outstanding && r.outstanding !== r.amount"
                            class="text-xs text-muted-foreground"
                        >
                            {{ r.outstanding_label }} left
                        </p>
                        <div class="mt-2 flex flex-wrap justify-end gap-1.5">
                            <a :href="r.invoice_url" class="tiny-action"
                                ><AppIcon name="download" :size="14" />
                                Invoice</a
                            >
                            <Button
                                v-if="r.can_pay && r.online_url"
                                size="sm"
                                :disabled="redirecting"
                                @click="payOnline(r)"
                            >
                                Pay online
                            </Button>
                            <Button
                                v-if="r.can_pay"
                                size="sm"
                                :variant="r.online_url ? 'outline' : 'default'"
                                @click="paying = r"
                            >
                                {{
                                    r.online_url
                                        ? 'I have already paid'
                                        : 'Pay now'
                                }}
                            </Button>
                        </div>
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
