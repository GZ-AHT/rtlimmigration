<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/admin/ticket-categories';
import type { TableColumn } from '@/types';

type Category = {
    id: number;
    name: string;
    is_active: boolean;
    tickets_count: number;
};

defineProps<{ categories: Category[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Ticket categories', href: index() }] },
});

const form = useForm({ name: '', is_active: true });
// The server validates a slug derived from the name; show either error.
const addError = computed(
    () =>
        form.errors.name ??
        (form.errors as Record<string, string | undefined>).slug,
);
const editing = ref<Category | null>(null);
const editName = ref('');
const deleting = ref<Category | null>(null);

const columns: TableColumn<Category>[] = [
    { key: 'name', label: 'Category' },
    { key: 'is_active', label: 'Status' },
    { key: 'tickets_count', label: 'Tickets', align: 'right' },
];

function add(): void {
    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function saveEdit(category: Category, changes: Partial<Category>): void {
    router.put(
        update(category.id).url,
        {
            name: changes.name ?? category.name,
            is_active: changes.is_active ?? category.is_active,
        },
        {
            preserveScroll: true,
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Could not save.'),
            onSuccess: () => (editing.value = null),
        },
    );
}

function remove(): void {
    if (!deleting.value) {
        return;
    }

    router.delete(destroy(deleting.value.id).url, {
        preserveScroll: true,
        onError: (e) => toast.error(Object.values(e)[0] ?? 'Could not delete.'),
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Ticket categories" />

    <PageHeader
        eyebrow="Ticket & contact"
        title="Ticket categories"
        description="Group tickets so the right people pick them up."
    />

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(280px,1fr)]">
        <PanelCard flush>
            <DataTable
                :columns="columns"
                :rows="categories"
                caption="Ticket categories"
                empty-title="No categories yet"
            >
                <template #cell-name="{ row }">
                    <form
                        v-if="editing?.id === row.id"
                        class="flex gap-2"
                        @submit.prevent="saveEdit(row, { name: editName })"
                    >
                        <Input
                            v-model="editName"
                            class="h-8"
                            aria-label="Category name"
                        />
                        <Button type="submit" size="sm">Save</Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="editing = null"
                            >Cancel</Button
                        >
                    </form>
                    <span v-else class="font-semibold text-foreground">{{
                        row.name
                    }}</span>
                </template>
                <template #cell-is_active="{ row }">
                    <StatusBadge
                        :label="row.is_active ? 'Active' : 'Inactive'"
                        :tone="row.is_active ? 'success' : 'neutral'"
                    />
                </template>
                <template #actions="{ row }">
                    <button
                        type="button"
                        class="tiny-action"
                        @click="
                            editing = row;
                            editName = row.name;
                        "
                    >
                        Rename
                    </button>
                    <button
                        type="button"
                        class="tiny-action"
                        @click="saveEdit(row, { is_active: !row.is_active })"
                    >
                        {{ row.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                    <button
                        v-if="row.tickets_count === 0"
                        type="button"
                        class="tiny-action"
                        @click="deleting = row"
                    >
                        Delete
                    </button>
                </template>
            </DataTable>
        </PanelCard>

        <PanelCard title="Add category" class="self-start">
            <form class="flex flex-col gap-3" @submit.prevent="add">
                <Input
                    v-model="form.name"
                    placeholder="e.g. Scholarships"
                    aria-label="New category name"
                    :aria-invalid="Boolean(addError)"
                />
                <p v-if="addError" class="text-xs text-danger" role="alert">
                    {{ addError }}
                </p>
                <Button
                    type="submit"
                    :disabled="form.processing || !form.name.trim()"
                    >Add category</Button
                >
            </form>
        </PanelCard>
    </div>

    <ConfirmDialog
        :open="deleting !== null"
        :title="`Delete ${deleting?.name}?`"
        description="Only categories without tickets can be deleted."
        confirm-label="Delete"
        destructive
        @update:open="(v) => !v && (deleting = null)"
        @confirm="remove"
    />
</template>
