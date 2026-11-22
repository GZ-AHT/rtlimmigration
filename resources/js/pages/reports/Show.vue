<script setup lang="ts">
import { Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import DateRangeFilter from '@/components/panel/DateRangeFilter.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import { useFilters } from '@/composables/useFilters';
import { formatDate } from '@/lib/format';
import type { DateRange, TableColumn } from '@/types';

/**
 * One screen for every operational report (REQ §24): headline figures,
 * breakdown bars and the first rows of the table. Downloads carry every row.
 */
type Cell = string | number | null;
type Row = Record<string, Cell>;

const props = defineProps<{
    report: { key: string; title: string; description: string };
    filters: DateRange;
    stats: { label: string; value: string | number }[];
    breakdowns: {
        title: string;
        items: { label: string; value: number; share: number }[];
    }[];
    headings: string[];
    rows: Cell[][];
    truncated: boolean;
    hubUrl: string;
    exports: { xlsx: string; csv: string; pdf: string } | null;
}>();

const page = usePage();
setLayoutProps({
    breadcrumbs: [
        { title: 'Reports', href: props.hubUrl },
        { title: props.report.title, href: page.url },
    ],
});

const { filters } = useFilters({ ...props.filters });

const columns = computed<TableColumn<Row>[]>(() =>
    props.headings.map((label, i) => ({ key: `c${i}`, label })),
);
const tableRows = computed<Row[]>(() =>
    props.rows.map((cells, r) => ({
        _row: r,
        ...Object.fromEntries(cells.map((v, i) => [`c${i}`, v])),
    })),
);

const formats = [
    { key: 'xlsx', label: 'Excel' },
    { key: 'csv', label: 'CSV' },
    { key: 'pdf', label: 'PDF' },
] as const;
</script>

<template>
    <Head :title="report.title" />

    <PageHeader
        eyebrow="Reports"
        :title="report.title"
        :description="`${formatDate(filters.from)} to ${formatDate(filters.to)}. ${report.description}`"
    >
        <template v-if="exports" #actions>
            <div class="flex flex-wrap gap-1.5">
                <a
                    v-for="f in formats"
                    :key="f.key"
                    :href="exports[f.key]"
                    class="tiny-action"
                    :aria-label="`Download ${report.title} as ${f.label}`"
                    ><AppIcon name="download" :size="14" /> {{ f.label }}</a
                >
            </div>
        </template>
    </PageHeader>

    <PanelCard class="mb-4">
        <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />
    </PanelCard>

    <div class="mb-4 grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4">
        <div
            v-for="s in stats"
            :key="s.label"
            class="min-w-0 rounded-xl border bg-card p-4"
        >
            <p class="text-xs text-muted-foreground">{{ s.label }}</p>
            <p
                class="mt-1 truncate font-display text-lg font-extrabold sm:text-xl"
                :title="String(s.value)"
            >
                {{ s.value }}
            </p>
        </div>
    </div>

    <div v-if="breakdowns.length" class="mb-4 grid gap-4 lg:grid-cols-3">
        <PanelCard v-for="b in breakdowns" :key="b.title" :title="b.title">
            <p v-if="!b.items.length" class="text-xs text-muted-foreground">
                Nothing in this period.
            </p>
            <ul v-else class="space-y-3">
                <li v-for="item in b.items" :key="item.label">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="truncate text-[13px] font-medium">{{
                            item.label
                        }}</span>
                        <span class="shrink-0 text-xs text-muted-foreground"
                            >{{ item.value }} · {{ item.share }}%</span
                        >
                    </div>
                    <div
                        class="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        <div
                            class="h-full rounded-full bg-primary"
                            :style="{ width: `${item.share}%` }"
                        />
                    </div>
                </li>
            </ul>
        </PanelCard>
    </div>

    <PanelCard flush>
        <DataTable
            :columns="columns"
            :rows="tableRows"
            row-key="_row"
            :caption="report.title"
            empty-icon="monitoring"
            empty-title="Nothing in this period"
        />
        <p
            v-if="truncated"
            class="border-t px-5 py-3 text-xs text-muted-foreground"
        >
            Showing the first 100 rows.
            {{
                exports
                    ? 'Download the report to get every row.'
                    : 'Ask an administrator for the full export.'
            }}
        </p>
    </PanelCard>
</template>
