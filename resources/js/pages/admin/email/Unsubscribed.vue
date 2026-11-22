<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SearchInput from '@/components/panel/SearchInput.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { relativeTime } from '@/lib/format';
import { destroy, index, store } from '@/routes/admin/email/unsubscribed';
import type { Paginated, TableColumn } from '@/types';

/** Addresses that opted out of bulk email (REQ §3 Bulk Email). */
type Row = { id: number; email: string; reason: string; created_at: string };

const props = defineProps<{
    addresses: Paginated<Row>;
    filters: { search: string };
    total: number;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Unsubscribed', href: index() }] },
});

const search = ref(props.filters.search);
watch(search, (value) =>
    router.get(
        index().url,
        { search: value || undefined },
        { preserveState: true, replace: true },
    ),
);

const form = useForm({ email: '' });

function add(): void {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const removing = ref<Row | null>(null);
const removeOpen = ref(false);

function ask(row: Row): void {
    removing.value = row;
    removeOpen.value = true;
}

function confirmRemove(): void {
    if (!removing.value) {
        return;
    }

    router.delete(destroy(removing.value.id).url, {
        preserveScroll: true,
        onFinish: () => (removeOpen.value = false),
    });
}

const columns: TableColumn<Row>[] = [
    { key: 'email', label: 'Address' },
    { key: 'reason', label: 'How', hideBelow: 'sm' },
    {
        key: 'created_at',
        label: 'When',
        hideBelow: 'md',
        format: (r) => relativeTime(r.created_at),
    },
    { key: 'actions', label: 'Actions', align: 'right' },
];
</script>

<template>
    <Head title="Unsubscribed" />

    <PageHeader
        eyebrow="Email"
        title="Unsubscribed"
        :description="`${total} address${total === 1 ? '' : 'es'} will not get bulk email. Messages about someone's own application (receipts, replies) are not affected.`"
    />

    <PanelCard
        class="mb-4"
        title="Add an address"
        description="For someone who asked to stop by phone or reply. People who use the link in an email are added automatically."
    >
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="add">
            <FormField
                label="Email address"
                :error="form.errors.email"
                class="min-w-[240px] flex-1"
            >
                <template #default="{ id, invalid, describedBy }">
                    <Input
                        :id="id"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>
            <Button type="submit" :disabled="form.processing || !form.email"
                ><AppIcon name="block" :size="18" /> Stop bulk email</Button
            >
        </form>
    </PanelCard>

    <PanelCard flush>
        <div class="border-b p-3">
            <SearchInput v-model="search" label="Search addresses" />
        </div>
        <DataTable
            :columns="columns"
            :rows="addresses.data"
            caption="Addresses that opted out of bulk email"
            empty-icon="mark_email_read"
            empty-title="Nobody has unsubscribed"
        >
            <template #cell-actions="{ row }">
                <button
                    type="button"
                    class="tiny-action"
                    :aria-label="`Allow bulk email to ${row.email} again`"
                    @click="ask(row)"
                >
                    <AppIcon name="undo" :size="14" />
                    <span class="max-sm:sr-only">Allow again</span>
                </button>
            </template>
        </DataTable>
        <TablePagination v-if="addresses.last_page > 1" :meta="addresses" />
    </PanelCard>

    <ConfirmDialog
        v-model:open="removeOpen"
        title="Allow bulk email again?"
        :description="`${removing?.email ?? 'This address'} will be included in future bulk sends. Only do this if they asked to hear from you again.`"
        confirm-label="Allow again"
        @confirm="confirmRemove"
    />
</template>
