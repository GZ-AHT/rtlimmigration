<script setup lang="ts">
import { Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import DateRangeFilter from '@/components/panel/DateRangeFilter.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { formatDate } from '@/lib/format';
import type { DateRange, TableColumn } from '@/types';

type Row = {
    counselor: string;
    students: number;
    invoiced: string;
    collected: string;
    refunded: string;
    outstanding: string;
    value: number;
};

const props = defineProps<{
    filters: DateRange;
    rows: Row[];
    totals: {
        invoiced: string;
        collected: string;
        refunded: string;
        outstanding: string;
    };
    exportUrl: string | null;
}>();

const page = usePage();
setLayoutProps({
    breadcrumbs: [{ title: 'Counselor-wise collection', href: page.url }],
});

const { filters } = useFilters({ ...props.filters });

const top = computed(() => Math.max(1, ...props.rows.map((r) => r.value)));

const columns: TableColumn<Row>[] = [
    { key: 'counselor', label: 'Counselor' },
    { key: 'invoiced', label: 'Invoiced', align: 'right', hideBelow: 'md' },
    { key: 'collected', label: 'Collected', align: 'right' },
    { key: 'refunded', label: 'Refunded', align: 'right', hideBelow: 'lg' },
    { key: 'outstanding', label: 'Outstanding now', align: 'right' },
];
</script>

<template>
    <Head title="Counselor-wise collection" />

    <PageHeader
        eyebrow="Reports"
        title="Counselor-wise collection"
        :description="`${formatDate(filters.from)} to ${formatDate(filters.to)}. Money is counted under the counselor each student is assigned to today.`"
    >
        <template v-if="exportUrl" #actions>
            <Button variant="outline" as-child>
                <a :href="exportUrl"
                    ><AppIcon name="download" :size="18" /> Export to Excel</a
                >
            </Button>
        </template>
    </PageHeader>

    <PanelCard class="mb-4">
        <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />
    </PanelCard>

    <div class="mb-4 grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4">
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Invoiced</p>
            <p class="mt-1 font-display text-lg font-extrabold sm:text-xl">
                {{ totals.invoiced }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Collected</p>
            <p
                class="mt-1 font-display text-lg font-extrabold text-success sm:text-xl"
            >
                {{ totals.collected }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Refunded</p>
            <p class="mt-1 font-display text-lg font-extrabold sm:text-xl">
                {{ totals.refunded }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Outstanding now</p>
            <p class="mt-1 font-display text-lg font-extrabold sm:text-xl">
                {{ totals.outstanding }}
            </p>
        </div>
    </div>

    <PanelCard flush>
        <DataTable
            :columns="columns"
            :rows="rows"
            row-key="counselor"
            caption="Collection by counselor"
            empty-icon="support_agent"
            empty-title="No students yet"
        >
            <template #cell-counselor="{ row }">
                <span class="block font-semibold text-foreground">{{
                    row.counselor
                }}</span>
                <span class="block text-[11px] text-muted-foreground"
                    >{{ row.students }} student{{
                        row.students === 1 ? '' : 's'
                    }}</span
                >
            </template>
            <template #cell-collected="{ row }">
                <b class="text-foreground">{{ row.collected }}</b>
                <div
                    class="mt-1 ml-auto h-1.5 w-24 overflow-hidden rounded-full bg-muted"
                    aria-hidden="true"
                >
                    <div
                        class="h-full rounded-full bg-primary"
                        :style="{ width: `${(row.value / top) * 100}%` }"
                    />
                </div>
            </template>
        </DataTable>
    </PanelCard>
</template>
