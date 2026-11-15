<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { getInitials } from '@/composables/useInitials';
import { formatDate, relativeTime } from '@/lib/format';
import { create, edit, index, show } from '@/routes/admin/users';
import type {
    Option,
    Paginated,
    SortDirection,
    TableColumn,
    Tone,
} from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    status: 'active' | 'inactive' | 'deleted';
    email_verified: boolean;
    last_login_at: string | null;
    created_at: string | null;
    can: { update: boolean };
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Users', href: index() }] },
});

const props = defineProps<{
    users: Paginated<UserRow>;
    filters: {
        search: string;
        role: string;
        status: string;
        sort: string;
        direction: string;
    };
    roles: Option[];
    statuses: Option[];
    can: { create: boolean };
}>();

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['users', 'filters'] },
);

const statusTone: Record<UserRow['status'], Tone> = {
    active: 'success',
    inactive: 'neutral',
    deleted: 'danger',
};

const columns: TableColumn<UserRow>[] = [
    { key: 'name', label: 'User', sortable: true },
    { key: 'roles', label: 'Role', hideBelow: 'md' },
    { key: 'status', label: 'Status' },
    {
        key: 'last_login_at',
        label: 'Last login',
        sortable: true,
        hideBelow: 'lg',
    },
    {
        key: 'created_at',
        label: 'Created',
        sortable: true,
        hideBelow: 'lg',
        format: (r) => formatDate(r.created_at),
    },
];

function onSort(key: string, direction: SortDirection): void {
    filters.sort = key;
    filters.direction = direction;
}

const selectClass =
    'h-10 rounded-lg border bg-card px-3 text-[13px] text-secondary-foreground outline-none focus:border-ring focus:ring-3 focus:ring-accent';
</script>

<template>
    <Head title="Users" />

    <PageHeader
        eyebrow="Lead & user management"
        title="Users"
        description="Staff and client accounts, their roles and access."
    >
        <template v-if="can.create" #actions>
            <Button as-child>
                <Link :href="create()">
                    <AppIcon name="person_add" :size="18" />
                    Add user
                </Link>
            </Button>
        </template>
    </PageHeader>

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Search name or email…"
        >
            <template #filters>
                <select
                    v-model="filters.role"
                    :class="selectClass"
                    aria-label="Filter by role"
                >
                    <option value="">All roles</option>
                    <option
                        v-for="role in roles"
                        :key="role.value"
                        :value="role.value"
                    >
                        {{ role.label }}
                    </option>
                </select>
                <select
                    v-model="filters.status"
                    :class="selectClass"
                    aria-label="Filter by status"
                >
                    <option value="">All statuses</option>
                    <option
                        v-for="s in statuses"
                        :key="s.value"
                        :value="s.value"
                    >
                        {{ s.label }}
                    </option>
                    <option value="unverified">Email not verified</option>
                    <option value="deleted">Deleted</option>
                </select>
            </template>
            <template
                v-if="filters.search || filters.role || filters.status"
                #actions
            >
                <Button variant="ghost" size="sm" @click="reset"
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>

        <DataTable
            :columns="columns"
            :rows="users.data"
            :sort-key="filters.sort"
            :sort-direction="filters.direction as SortDirection"
            caption="Users"
            empty-icon="group"
            empty-title="No users found"
            empty-description="Try a different search or filter."
            @sort="onSort"
        >
            <template #cell-name="{ row }">
                <Link
                    :href="show(row.id)"
                    class="flex items-center gap-2.5 font-semibold text-foreground hover:text-primary"
                >
                    <span
                        class="grid size-[30px] shrink-0 place-items-center rounded-full bg-accent text-[11px] text-accent-foreground"
                    >
                        {{ getInitials(row.name) }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate">{{ row.name }}</span>
                        <span
                            class="block truncate text-[11px] font-normal text-muted-foreground"
                            >{{ row.email }}</span
                        >
                    </span>
                </Link>
            </template>
            <template #cell-roles="{ row }">
                {{ row.roles.join(', ') || '—' }}
            </template>
            <template #cell-status="{ row }">
                <span class="inline-flex flex-wrap items-center gap-1.5">
                    <StatusBadge
                        :label="
                            row.status.charAt(0).toUpperCase() +
                            row.status.slice(1)
                        "
                        :tone="statusTone[row.status]"
                    />
                    <StatusBadge
                        v-if="!row.email_verified && row.status !== 'deleted'"
                        label="Unverified"
                        tone="warning"
                    />
                </span>
            </template>
            <template #cell-last_login_at="{ row }">
                {{
                    row.last_login_at
                        ? relativeTime(row.last_login_at)
                        : 'Never'
                }}
            </template>
            <template #actions="{ row }">
                <Link
                    :href="show(row.id)"
                    class="rounded-md border bg-card px-2 py-1.5 text-[11px] text-secondary-foreground hover:border-ring hover:text-accent-foreground"
                    >View</Link
                >
                <Link
                    v-if="row.can.update"
                    :href="edit(row.id)"
                    class="rounded-md border bg-card px-2 py-1.5 text-[11px] text-secondary-foreground hover:border-ring hover:text-accent-foreground"
                    >Edit</Link
                >
            </template>
        </DataTable>

        <TablePagination :meta="users" />
    </PanelCard>
</template>
