<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import FileUpload from '@/components/panel/FileUpload.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import StatCard from '@/components/panel/StatCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { SortDirection, TableColumn, Tone } from '@/types';

/*
 * Development reference for the panel design system (local only).
 * The rows below are illustrative sample data, not real records.
 */

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'UI kit', href: '/admin/ui-kit' }],
    },
});

type SampleRow = {
    id: string;
    name: string;
    email: string;
    country: string;
    status: string;
};

const sample: SampleRow[] = [
    {
        id: 'STU-2026-00001',
        name: 'Sample Student A',
        email: 'a@example.com',
        country: 'Canada',
        status: 'In progress',
    },
    {
        id: 'STU-2026-00002',
        name: 'Sample Student B',
        email: 'b@example.com',
        country: 'United Kingdom',
        status: 'Approved',
    },
    {
        id: 'STU-2026-00003',
        name: 'Sample Student C',
        email: 'c@example.com',
        country: 'Australia',
        status: 'Pending',
    },
    {
        id: 'STU-2026-00004',
        name: 'Sample Student D',
        email: 'd@example.com',
        country: 'Denmark',
        status: 'Rejected',
    },
];

const statusTone: Record<string, Tone> = {
    'In progress': 'info',
    Approved: 'success',
    Pending: 'warning',
    Rejected: 'danger',
};

const columns: TableColumn<SampleRow>[] = [
    { key: 'name', label: 'Student', sortable: true },
    { key: 'id', label: 'Student no.', hideBelow: 'md' },
    { key: 'email', label: 'Email', hideBelow: 'lg' },
    { key: 'country', label: 'Destination', sortable: true },
    { key: 'status', label: 'Status' },
];

const tableState = ref<'data' | 'loading' | 'empty'>('data');
const search = ref('');
const sortKey = ref<string | null>('name');
const sortDirection = ref<SortDirection>('asc');

const rows = computed(() => {
    if (tableState.value === 'empty') {
        return [];
    }

    const q = search.value.toLowerCase();
    const filtered = sample.filter((r) =>
        [r.name, r.email, r.country].some((v) => v.toLowerCase().includes(q)),
    );
    const key = sortKey.value as keyof SampleRow | null;

    if (!key) {
        return filtered;
    }

    return [...filtered].sort(
        (a, b) =>
            a[key].localeCompare(b[key]) *
            (sortDirection.value === 'asc' ? 1 : -1),
    );
});

function onSort(key: string, direction: SortDirection): void {
    sortKey.value = key;
    sortDirection.value = direction;
}

const tab = ref('all');
const confirmOpen = ref(false);
const confirming = ref(false);
const files = ref<File[]>([]);
const fullName = ref('');
const nameError = ref<string | undefined>('The full name field is required.');

function confirmAction(): void {
    confirming.value = true;
    setTimeout(() => {
        confirming.value = false;
        confirmOpen.value = false;
        toast.success('Action confirmed');
    }, 900);
}

const tones: Tone[] = ['success', 'warning', 'danger', 'info', 'neutral'];
</script>

<template>
    <Head title="UI kit" />

    <PageHeader
        eyebrow="Design system"
        title="Panel UI kit"
        description="Shared components recreated from admin-demo.html. Local environment only."
    >
        <template #actions>
            <Button variant="outline">Secondary</Button>
            <Button
                variant="secondary"
                class="border border-[#ffe0e0] bg-accent text-accent-foreground"
                >Soft</Button
            >
            <Button>
                <AppIcon name="add" :size="18" />
                Primary
            </Button>
        </template>
    </PageHeader>

    <div class="mb-6 grid grid-cols-2 gap-2.5 sm:gap-4 xl:grid-cols-4">
        <StatCard
            label="Total Students"
            :value="1284"
            icon="school"
            hint="+12% this month"
        />
        <StatCard
            label="Pending Bills"
            :value="37"
            icon="receipt_long"
            hint="5 due today"
            hint-tone="warning"
        />
        <StatCard
            label="Open Tickets"
            :value="9"
            icon="confirmation_number"
            hint="2 high priority"
            hint-tone="danger"
        />
        <StatCard
            label="Revenue"
            value="৳ 4.2M"
            icon="payments"
            hint="Sample figure"
            hint-tone="neutral"
        />
    </div>

    <PanelTabs
        v-model="tab"
        label="Status filter"
        :tabs="[
            { key: 'all', label: 'All', count: 4 },
            { key: 'progress', label: 'In progress', count: 1 },
            { key: 'approved', label: 'Approved', count: 1 },
        ]"
    />

    <PanelCard flush class="mb-6">
        <TableToolbar
            v-model:search="search"
            search-placeholder="Search students…"
        >
            <template #filters>
                <select
                    v-model="tableState"
                    class="h-10 rounded-lg border bg-card px-3 text-[13px] text-secondary-foreground"
                    aria-label="Table state"
                >
                    <option value="data">With data</option>
                    <option value="loading">Loading</option>
                    <option value="empty">Empty</option>
                </select>
            </template>
            <template #actions>
                <Button variant="outline" size="sm">
                    <AppIcon name="download" :size="16" /> Export
                </Button>
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="tableState === 'loading'"
            :sort-key="sortKey"
            :sort-direction="sortDirection"
            caption="Sample students"
            empty-icon="school"
            empty-title="No students found"
            empty-description="Try a different search or filter."
            @sort="onSort"
        >
            <template #cell-name="{ row }">
                <span
                    class="flex items-center gap-2.5 font-semibold text-foreground"
                >
                    <span
                        class="grid size-[30px] place-items-center rounded-full bg-accent text-[11px] text-accent-foreground"
                    >
                        {{ row.name.split(' ').pop()?.charAt(0) }}
                    </span>
                    {{ row.name }}
                </span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :label="row.status"
                    :tone="statusTone[row.status]"
                />
            </template>
            <template #actions>
                <button
                    type="button"
                    class="rounded-md border bg-card px-2 py-1.5 text-[11px] text-secondary-foreground hover:border-ring hover:text-accent-foreground"
                >
                    View
                </button>
                <button
                    type="button"
                    class="rounded-md border bg-card px-2 py-1.5 text-[11px] text-secondary-foreground hover:border-ring hover:text-accent-foreground"
                    @click="confirmOpen = true"
                >
                    Delete
                </button>
            </template>
        </DataTable>
    </PanelCard>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(300px,1fr)]">
        <PanelCard
            title="Form controls"
            description="Labels, hints, validation errors and uploads."
        >
            <form class="grid gap-3.5 sm:grid-cols-2" @submit.prevent>
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Full name"
                    required
                    :error="nameError"
                >
                    <Input
                        :id="id"
                        v-model="fullName"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                        @input="
                            nameError = fullName
                                ? undefined
                                : 'The full name field is required.'
                        "
                    />
                </FormField>
                <FormField
                    v-slot="{ id, describedBy }"
                    label="Passport number"
                    hint="As printed on the passport."
                >
                    <Input :id="id" :aria-describedby="describedBy" />
                </FormField>
                <div class="sm:col-span-2">
                    <FileUpload
                        v-model="files"
                        label="Supporting documents"
                        accept=".pdf,.jpg,.jpeg,.png"
                        :max-size-mb="10"
                        multiple
                    />
                </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <Button variant="outline" type="button">Cancel</Button>
                    <Button type="submit" @click="toast.success('Saved')"
                        >Save</Button
                    >
                </div>
            </form>
        </PanelCard>

        <div class="flex flex-col gap-4">
            <PanelCard title="Status badges">
                <div class="flex flex-wrap gap-2">
                    <StatusBadge
                        v-for="tone in tones"
                        :key="tone"
                        :label="tone"
                        :tone="tone"
                    />
                </div>
            </PanelCard>
            <PanelCard title="Empty state">
                <EmptyState
                    icon="folder_open"
                    title="No documents uploaded"
                    description="Upload the passport and academic certificates to continue."
                >
                    <Button size="sm">Upload documents</Button>
                </EmptyState>
            </PanelCard>
        </div>
    </div>

    <ConfirmDialog
        v-model:open="confirmOpen"
        title="Delete this record?"
        description="This sample action shows the confirmation pattern for destructive operations."
        confirm-label="Delete"
        destructive
        :loading="confirming"
        @confirm="confirmAction"
    />
</template>
