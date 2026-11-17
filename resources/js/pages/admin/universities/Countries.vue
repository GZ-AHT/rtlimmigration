<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SearchInput from '@/components/panel/SearchInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { index, update } from '@/routes/admin/countries';
import type { TableColumn } from '@/types';

type Row = {
    id: number;
    name: string;
    iso2: string;
    is_destination: boolean;
    is_active: boolean;
    universities_count: number;
};

const props = defineProps<{
    countries: Row[];
    search: string;
    canManage: boolean;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Countries', href: index() }] },
});

const search = ref(props.search);
watch(search, (v) =>
    router.get(
        index.url({ query: v ? { search: v } : {} }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['countries', 'search'],
        },
    ),
);

const columns: TableColumn<Row>[] = [
    { key: 'name', label: 'Country' },
    { key: 'is_destination', label: 'Study destination' },
    {
        key: 'universities_count',
        label: 'Universities',
        align: 'right',
        hideBelow: 'sm',
    },
];

const toggle = (row: Row) =>
    router.patch(
        update(row.id).url,
        { is_destination: !row.is_destination },
        { preserveScroll: true, only: ['countries', 'flash'] },
    );
</script>

<template>
    <Head title="Countries" />

    <PageHeader
        eyebrow="University management"
        title="Countries"
        description="Destinations appear in the pathway finder, the countries section and when adding universities."
    />

    <PanelCard flush>
        <div class="max-w-sm p-3">
            <SearchInput v-model="search" placeholder="Search countries…" />
        </div>
        <DataTable
            :columns="columns"
            :rows="countries"
            caption="Countries"
            empty-title="No countries found"
        >
            <template #cell-name="{ row }">
                <span class="font-semibold">{{ row.name }}</span>
                <span class="text-[11px] text-muted-foreground">{{
                    row.iso2
                }}</span>
            </template>
            <template #cell-is_destination="{ row }">
                <StatusBadge
                    :label="row.is_destination ? 'Destination' : 'No'"
                    :tone="row.is_destination ? 'success' : 'neutral'"
                />
            </template>
            <template v-if="canManage" #actions="{ row }">
                <button type="button" class="tiny-action" @click="toggle(row)">
                    {{
                        row.is_destination
                            ? 'Remove as destination'
                            : 'Make destination'
                    }}
                </button>
            </template>
        </DataTable>
    </PanelCard>
</template>
