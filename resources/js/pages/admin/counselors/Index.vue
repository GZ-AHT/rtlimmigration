<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { getInitials } from '@/composables/useInitials';
import { create, index, show } from '@/routes/admin/counselors';
import type { Option, TableColumn, Tone } from '@/types';

type Row = {
    id: number;
    name: string;
    email: string;
    designation: string | null;
    availability: string;
    availability_label: string;
    availability_tone: Tone;
    account_active: boolean;
    open_leads: number;
    capacity: number | null;
    converted: number;
    students: number;
};

const props = defineProps<{
    counselors: Row[];
    filters: { search: string; availability: string };
    availabilities: Option[];
    can: { create: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Counselors', href: index() }] },
});

const { filters } = useFilters(
    { ...props.filters },
    { only: ['counselors', 'filters'] },
);

const columns: TableColumn<Row>[] = [
    { key: 'name', label: 'Counselor' },
    { key: 'availability', label: 'Status' },
    { key: 'open_leads', label: 'Open leads', align: 'right' },
    { key: 'students', label: 'Students', align: 'right', hideBelow: 'sm' },
    { key: 'converted', label: 'Converted', align: 'right', hideBelow: 'md' },
];
</script>

<template>
    <Head title="Counselors" />

    <PageHeader
        eyebrow="Counselor management"
        title="Counselors"
        description="Who is available, their workload and results."
    >
        <template v-if="can.create" #actions>
            <Button as-child
                ><Link :href="create()"
                    ><AppIcon name="person_add" :size="18" /> Add
                    counselor</Link
                ></Button
            >
        </template>
    </PageHeader>

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Search name or email…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.availability"
                    :options="availabilities"
                    placeholder="Any status"
                    label="Filter by status"
                    compact
                />
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="counselors"
            caption="Counselors"
            empty-icon="support_agent"
            empty-title="No counselors yet"
            empty-description="Add a counselor, or give a user the Counselor role."
        >
            <template #cell-name="{ row }">
                <Link
                    :href="show(row.id)"
                    class="flex items-center gap-2.5 hover:text-primary"
                >
                    <span
                        class="grid size-[30px] shrink-0 place-items-center rounded-full bg-accent text-[11px] text-accent-foreground"
                        >{{ getInitials(row.name) }}</span
                    >
                    <span class="min-w-0">
                        <span
                            class="block truncate font-semibold text-foreground"
                            >{{ row.name }}</span
                        >
                        <span
                            class="block truncate text-[11px] text-muted-foreground"
                            >{{ row.designation ?? row.email }}</span
                        >
                    </span>
                </Link>
            </template>
            <template #cell-availability="{ row }">
                <span class="inline-flex flex-wrap gap-1.5">
                    <StatusBadge
                        :label="row.availability_label"
                        :tone="row.availability_tone"
                    />
                    <StatusBadge
                        v-if="!row.account_active"
                        label="Account inactive"
                        tone="danger"
                    />
                </span>
            </template>
            <template #cell-open_leads="{ row }">
                <span
                    :class="{
                        'font-bold text-warning':
                            row.capacity && row.open_leads >= row.capacity,
                    }"
                >
                    {{ row.open_leads
                    }}<span v-if="row.capacity" class="text-muted-foreground">
                        / {{ row.capacity }}</span
                    >
                </span>
            </template>
            <template #actions="{ row }">
                <Link :href="show(row.id)" class="tiny-action">Profile</Link>
            </template>
        </DataTable>
    </PanelCard>
</template>
