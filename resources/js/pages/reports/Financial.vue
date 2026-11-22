<script setup lang="ts">
import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import BarChart from '@/components/panel/BarChart.vue';
import DateRangeFilter from '@/components/panel/DateRangeFilter.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { formatDate } from '@/lib/format';
import type { DateRange } from '@/types';

type Breakdown = { label: string; count: number; total: string; share: number };

type YearRow = {
    label: string;
    income: string;
    previous_income: string;
    income_change: number | null;
    expenses: string;
    previous_expenses: string;
    profit: string;
    profit_negative: boolean;
    previous_profit: string;
    previous_profit_negative: boolean;
    value: number;
    display: string;
};

const props = defineProps<{
    filters: DateRange;
    summary: {
        income: string;
        payments: number;
        refunds: string;
        refund_count: number;
        net_income: string;
        expenses: string;
        profit: string;
        profit_negative: boolean;
        invoiced: string;
        outstanding: string;
        overdue: string;
    };
    monthly: {
        label: string;
        income: string;
        refunds: string;
        expenses: string;
        profit: string;
        profit_negative: boolean;
        value: number;
        display: string;
    }[];
    yearly: {
        year: number;
        previous: number;
        years: string[];
        rows: YearRow[];
        totals: YearRow;
    };
    byType: Breakdown[];
    byMethod: Breakdown[];
    byCategory: Breakdown[];
    exportsUrl: string | null;
}>();

const page = usePage();
setLayoutProps({
    breadcrumbs: [{ title: 'Financial reports', href: page.url }],
});

const { filters } = useFilters({ ...props.filters });

const yearOptions = computed(() =>
    props.yearly.years.map((y) => ({ value: y, label: y })),
);

// Only the yearly section reloads; the chosen period is kept.
function chooseYear(year: string): void {
    router.get(
        page.url.split('?')[0],
        { ...filters, year },
        { preserveScroll: true, preserveState: true, only: ['yearly'] },
    );
}

const changeText = (change: number | null): string =>
    change === null
        ? '—'
        : `${change > 0 ? '▲' : change < 0 ? '▼' : ''} ${Math.abs(change)}%`;

const breakdowns = [
    {
        key: 'byType',
        title: 'Income by fee type',
        empty: 'No payments confirmed in this period',
        icon: 'sell',
    },
    {
        key: 'byMethod',
        title: 'Income by payment method',
        empty: 'No payments confirmed in this period',
        icon: 'credit_card',
    },
    {
        key: 'byCategory',
        title: 'Expenses by category',
        empty: 'No expenses recorded in this period',
        icon: 'shopping_cart',
    },
] as const;
</script>

<template>
    <Head title="Financial reports" />

    <PageHeader
        eyebrow="Reports"
        title="Financial reports"
        :description="`${formatDate(filters.from)} to ${formatDate(filters.to)}. Income counts payments on the day Accounts confirmed them.`"
    >
        <template v-if="exportsUrl" #actions>
            <Button variant="outline" as-child>
                <Link :href="exportsUrl"
                    ><AppIcon name="download" :size="18" /> Export</Link
                >
            </Button>
        </template>
    </PageHeader>

    <PanelCard class="mb-4">
        <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />
    </PanelCard>

    <div class="mb-4 grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4">
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Income</p>
            <p
                class="mt-1 font-display text-lg font-extrabold text-success sm:text-2xl"
            >
                {{ summary.income }}
            </p>
            <p class="text-[11px] text-muted-foreground">
                {{ summary.payments }} payment{{
                    summary.payments === 1 ? '' : 's'
                }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Refunds paid</p>
            <p class="mt-1 font-display text-lg font-extrabold sm:text-2xl">
                {{ summary.refunds }}
            </p>
            <p class="text-[11px] text-muted-foreground">
                {{ summary.refund_count }} refund{{
                    summary.refund_count === 1 ? '' : 's'
                }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Expenses</p>
            <p class="mt-1 font-display text-lg font-extrabold sm:text-2xl">
                {{ summary.expenses }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">
                {{ summary.profit_negative ? 'Loss' : 'Profit' }}
            </p>
            <p
                class="mt-1 font-display text-lg font-extrabold sm:text-2xl"
                :class="
                    summary.profit_negative ? 'text-danger' : 'text-success'
                "
            >
                {{ summary.profit_negative ? '−' : '' }}{{ summary.profit }}
            </p>
            <p class="text-[11px] text-muted-foreground">
                Income − refunds − expenses
            </p>
        </div>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-2.5 sm:grid-cols-3 sm:gap-4">
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Invoiced in period</p>
            <p class="mt-1 font-display text-lg font-extrabold">
                {{ summary.invoiced }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Outstanding today</p>
            <p class="mt-1 font-display text-lg font-extrabold">
                {{ summary.outstanding }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Of which overdue</p>
            <p class="mt-1 font-display text-lg font-extrabold text-danger">
                {{ summary.overdue }}
            </p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(300px,1fr)]">
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard
                v-if="monthly.length > 1"
                title="Income by month"
                description="Confirmed payments."
            >
                <BarChart :data="monthly" caption="Income by month" />
            </PanelCard>

            <PanelCard
                :title="`${yearly.year} compared with ${yearly.previous}`"
                description="Confirmed payments, expenses and profit for each month against the same month a year earlier."
            >
                <div class="mb-4 max-w-[180px]">
                    <label
                        for="compare-year"
                        class="mb-1 block text-xs font-semibold text-muted-foreground"
                        >Year to compare</label
                    >
                    <SelectInput
                        id="compare-year"
                        :model-value="String(yearly.year)"
                        :options="yearOptions"
                        @update:model-value="chooseYear"
                    />
                </div>
                <BarChart
                    :data="yearly.rows"
                    :caption="`Income by month in ${yearly.year}`"
                />
                <div class="-mx-5 mt-4 overflow-x-auto">
                    <table class="w-full min-w-[560px] text-[13px]">
                        <caption class="sr-only">
                            Income, expenses and profit by month for
                            {{
                                yearly.year
                            }}
                            and
                            {{
                                yearly.previous
                            }}
                        </caption>
                        <thead>
                            <tr
                                class="border-y text-left text-[11px] text-muted-foreground uppercase"
                            >
                                <th
                                    scope="col"
                                    class="px-5 py-2.5 font-semibold"
                                >
                                    Month
                                </th>
                                <th
                                    scope="col"
                                    class="px-3 py-2.5 text-right font-semibold"
                                >
                                    Income {{ yearly.year }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-3 py-2.5 text-right font-semibold"
                                >
                                    {{ yearly.previous }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-3 py-2.5 text-right font-semibold"
                                >
                                    Change
                                </th>
                                <th
                                    scope="col"
                                    class="px-3 py-2.5 text-right font-semibold"
                                >
                                    Expenses
                                </th>
                                <th
                                    scope="col"
                                    class="px-5 py-2.5 text-right font-semibold"
                                >
                                    Profit
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="r in [...yearly.rows, yearly.totals]"
                                :key="r.label"
                                :class="
                                    r.label === 'Total' &&
                                    'bg-muted/40 font-bold'
                                "
                            >
                                <th
                                    scope="row"
                                    class="px-5 py-2.5 text-left font-medium"
                                >
                                    {{ r.label }}
                                </th>
                                <td class="px-3 py-2.5 text-right">
                                    {{ r.income }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right text-muted-foreground"
                                >
                                    {{ r.previous_income }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right"
                                    :class="
                                        r.income_change === null
                                            ? 'text-muted-foreground'
                                            : r.income_change >= 0
                                              ? 'text-success'
                                              : 'text-danger'
                                    "
                                >
                                    {{ changeText(r.income_change) }}
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    {{ r.expenses }}
                                </td>
                                <td
                                    class="px-5 py-2.5 text-right"
                                    :class="{
                                        'text-danger': r.profit_negative,
                                    }"
                                >
                                    {{ r.profit_negative ? '−' : ''
                                    }}{{ r.profit }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </PanelCard>

            <PanelCard title="Month by month" flush>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <caption class="sr-only">
                            Income, refunds, expenses and profit for each month
                        </caption>
                        <thead>
                            <tr
                                class="border-b text-left text-[11px] text-muted-foreground uppercase"
                            >
                                <th class="px-5 py-3 font-semibold">Month</th>
                                <th class="px-3 py-3 text-right font-semibold">
                                    Income
                                </th>
                                <th class="px-3 py-3 text-right font-semibold">
                                    Refunds
                                </th>
                                <th class="px-3 py-3 text-right font-semibold">
                                    Expenses
                                </th>
                                <th class="px-5 py-3 text-right font-semibold">
                                    Profit
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="m in monthly" :key="m.label">
                                <td class="px-5 py-3 font-medium">
                                    {{ m.label }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    {{ m.income }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    {{ m.refunds }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    {{ m.expenses }}
                                </td>
                                <td
                                    class="px-5 py-3 text-right font-bold"
                                    :class="{
                                        'text-danger': m.profit_negative,
                                    }"
                                >
                                    {{ m.profit_negative ? '−' : ''
                                    }}{{ m.profit }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </PanelCard>
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard v-for="b in breakdowns" :key="b.key" :title="b.title">
                <EmptyState
                    v-if="!props[b.key].length"
                    :icon="b.icon"
                    :title="b.empty"
                />
                <ul v-else class="flex flex-col gap-4">
                    <li v-for="row in props[b.key]" :key="row.label">
                        <div
                            class="mb-1.5 flex justify-between gap-3 text-[13px]"
                        >
                            <span class="truncate font-medium">{{
                                row.label
                            }}</span>
                            <b>{{ row.total }}</b>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{ width: `${row.share}%` }"
                            />
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            {{ row.count }} record{{
                                row.count === 1 ? '' : 's'
                            }}
                            · {{ row.share }}%
                        </p>
                    </li>
                </ul>
            </PanelCard>
        </div>
    </div>
</template>
