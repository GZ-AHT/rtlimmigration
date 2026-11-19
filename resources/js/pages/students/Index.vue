<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import ProgressBar from '@/components/panel/ProgressBar.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { getInitials } from '@/composables/useInitials';
import { formatDate } from '@/lib/format';
import type {
    CounselorOption,
    Option,
    Paginated,
    SortDirection,
    StudentRow,
    TableColumn,
} from '@/types';

const props = defineProps<{
    mode: 'list' | 'profiles';
    students: Paginated<StudentRow & { completeness: number }>;
    filters: {
        search: string;
        counselor: string;
        source: string;
        sort: string;
        direction: string;
    };
    sources: Option[];
    counselors: CounselorOption[];
    canSeeAll: boolean;
    urls: { create: string | null };
}>();

const page = usePage();
const title = computed(() =>
    props.canSeeAll ? 'Students' : 'Assigned students',
);
setLayoutProps({ breadcrumbs: [{ title: title.value, href: page.url }] });

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['students', 'filters'] },
);

const counselorOptions = computed<Option[]>(() => [
    { value: 'unassigned', label: 'Unassigned' },
    ...props.counselors.map((c) => ({ value: c.value, label: c.label })),
]);

const columns = computed<TableColumn<StudentRow & { completeness: number }>[]>(
    () => [
        { key: 'name', label: 'Student' },
        ...(props.mode === 'profiles'
            ? [{ key: 'completeness', label: 'Profile complete' }]
            : []),
        {
            key: 'student_number',
            label: 'Student no.',
            sortable: true,
            hideBelow: 'md',
        },
        ...(props.canSeeAll
            ? [
                  {
                      key: 'counselor',
                      label: 'Counselor',
                      hideBelow: 'md' as const,
                  },
              ]
            : []),
        { key: 'account_status', label: 'Account' },
        { key: 'source', label: 'Source', hideBelow: 'lg' },
        {
            key: 'created_at',
            label: 'Joined',
            sortable: true,
            hideBelow: 'lg',
            format: (r) => formatDate(r.created_at),
        },
    ],
);

function onSort(key: string, direction: SortDirection): void {
    filters.sort = key;
    filters.direction = direction;
}
</script>

<template>
    <Head :title="title" />

    <PageHeader
        eyebrow="Student management"
        :title="title"
        :description="
            canSeeAll
                ? 'Every student account and who is responsible for it.'
                : 'Students you are responsible for.'
        "
    >
        <template v-if="urls.create && mode === 'list'" #actions>
            <Button as-child
                ><Link :href="urls.create"
                    ><AppIcon name="person_add" :size="18" /> Add student</Link
                ></Button
            >
        </template>
    </PageHeader>

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Name, email, phone or student no.…"
        >
            <template #filters>
                <SelectInput
                    v-if="canSeeAll"
                    v-model="filters.counselor"
                    :options="counselorOptions"
                    placeholder="All counselors"
                    label="Filter by counselor"
                    compact
                />
                <SelectInput
                    v-model="filters.source"
                    :options="sources"
                    placeholder="Any source"
                    label="Filter by source"
                    compact
                />
            </template>
            <template
                v-if="filters.search || filters.counselor || filters.source"
                #actions
            >
                <Button
                    variant="ghost"
                    size="sm"
                    @click="
                        reset();
                        filters.sort = 'created_at';
                        filters.direction = 'desc';
                    "
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="students.data"
            :sort-key="filters.sort"
            :sort-direction="filters.direction as SortDirection"
            caption="Students"
            empty-icon="school"
            empty-title="No students found"
            :empty-description="
                canSeeAll
                    ? 'Students appear here when they register or a lead is converted.'
                    : 'Students assigned to you will appear here.'
            "
            @sort="onSort"
        >
            <template #cell-name="{ row }">
                <Link
                    :href="row.url!"
                    class="flex items-center gap-2.5 hover:text-primary"
                >
                    <span
                        class="grid size-7.5 shrink-0 place-items-center rounded-full bg-accent text-[11px] text-accent-foreground"
                        >{{ getInitials(row.name) }}</span
                    >
                    <span class="min-w-0">
                        <span
                            class="block truncate font-semibold text-foreground"
                            >{{ row.name }}</span
                        >
                        <span
                            class="block truncate text-[11px] text-muted-foreground"
                            >{{ row.email }}</span
                        >
                    </span>
                </Link>
            </template>
            <template #cell-completeness="{ row }">
                <div class="w-36">
                    <ProgressBar :value="row.completeness" /><span
                        class="text-[11px] text-muted-foreground"
                        >{{ row.completeness }}%</span
                    >
                </div>
            </template>
            <template #cell-counselor="{ row }">
                <span v-if="row.counselor">{{ row.counselor }}</span>
                <span v-else class="text-warning">Unassigned</span>
            </template>
            <template #cell-account_status="{ row }">
                <span class="inline-flex flex-wrap gap-1.5">
                    <StatusBadge
                        :label="
                            row.account_status === 'active'
                                ? 'Active'
                                : row.account_status === 'inactive'
                                  ? 'Inactive'
                                  : 'Deleted'
                        "
                        :tone="
                            row.account_status === 'active'
                                ? 'success'
                                : row.account_status === 'inactive'
                                  ? 'neutral'
                                  : 'danger'
                        "
                    />
                    <StatusBadge
                        v-if="!row.email_verified"
                        label="Invite pending"
                        tone="warning"
                    />
                </span>
            </template>
            <template #actions="{ row }">
                <Link :href="row.url!" class="tiny-action">
                    <AppIcon
                        :name="mode === 'profiles' ? 'edit' : 'visibility'"
                        :size="13"
                    />
                    <span>{{
                        mode === 'profiles' ? 'Edit profile' : 'Open'
                    }}</span>
                </Link>
            </template>
        </DataTable>
        <TablePagination :meta="students" />
    </PanelCard>
</template>
