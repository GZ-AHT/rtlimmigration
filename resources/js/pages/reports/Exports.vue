<script setup lang="ts">
import { Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import DateRangeFilter from '@/components/panel/DateRangeFilter.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import { useFilters } from '@/composables/useFilters';
import { formatDate } from '@/lib/format';
import type { DateRange } from '@/types';

const props = defineProps<{
    filters: DateRange;
    reports: {
        key: string;
        title: string;
        description: string;
        dated: boolean;
        urls: { xlsx: string; csv: string; pdf: string };
    }[];
}>();

const page = usePage();
setLayoutProps({ breadcrumbs: [{ title: 'Export reports', href: page.url }] });

const { filters } = useFilters({ ...props.filters });

const formats = [
    { key: 'xlsx', label: 'Excel' },
    { key: 'csv', label: 'CSV' },
    { key: 'pdf', label: 'PDF' },
] as const;
</script>

<template>
    <Head title="Export reports" />

    <PageHeader
        eyebrow="Reports"
        title="Export reports"
        description="Download finance data as Excel, CSV or PDF. Each download is recorded in the activity log."
    />

    <PanelCard
        class="mb-4"
        title="Period"
        :description="`${formatDate(filters.from)} to ${formatDate(filters.to)}. Applies to the dated reports below.`"
    >
        <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />
    </PanelCard>

    <PanelCard flush>
        <ul class="divide-y">
            <li
                v-for="r in reports"
                :key="r.key"
                class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
            >
                <div class="min-w-0">
                    <p class="text-[13px] font-semibold">{{ r.title }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ r.description }}
                        <span v-if="!r.dated" class="font-medium">
                            Not affected by the period.</span
                        >
                    </p>
                </div>
                <div class="flex gap-1.5">
                    <a
                        v-for="f in formats"
                        :key="f.key"
                        :href="r.urls[f.key]"
                        class="tiny-action"
                        :aria-label="`Download ${r.title} as ${f.label}`"
                        ><AppIcon name="download" :size="14" /> {{ f.label }}</a
                    >
                </div>
            </li>
        </ul>
    </PanelCard>
</template>
