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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/admin/document-categories';
import type { TableColumn } from '@/types';

type Category = {
    id: number;
    name: string;
    description: string | null;
    is_required: boolean;
    is_active: boolean;
    documents_count: number;
};

defineProps<{ categories: Category[] }>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Document categories', href: index() }] },
});

const form = useForm({
    name: '',
    description: '',
    is_required: false,
    is_active: true,
});
const addError = computed(
    () =>
        form.errors.name ??
        (form.errors as Record<string, string | undefined>).slug,
);
const deleting = ref<Category | null>(null);

const columns: TableColumn<Category>[] = [
    { key: 'name', label: 'Category' },
    { key: 'is_required', label: 'Checklist' },
    { key: 'is_active', label: 'Status', hideBelow: 'sm' },
    {
        key: 'documents_count',
        label: 'Documents',
        align: 'right',
        hideBelow: 'md',
    },
];

function save(category: Category, changes: Partial<Category>): void {
    router.put(
        update(category.id).url,
        { ...category, ...changes },
        {
            preserveScroll: true,
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Could not save.'),
        },
    );
}

function add(): void {
    form.transform((d) => ({
        ...d,
        description: d.description || null,
    })).submit(store(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
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
    <Head title="Document categories" />

    <PageHeader
        eyebrow="Student file management"
        title="Document categories"
        description="The documents students are asked for. Required ones make up each student's checklist."
    />

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(280px,1fr)]">
        <PanelCard flush>
            <DataTable
                :columns="columns"
                :rows="categories"
                caption="Document categories"
                empty-title="No categories yet"
            >
                <template #cell-name="{ row }">
                    <span class="block font-semibold text-foreground">{{
                        row.name
                    }}</span>
                    <span
                        v-if="row.description"
                        class="block text-[11px] text-muted-foreground"
                        >{{ row.description }}</span
                    >
                </template>
                <template #cell-is_required="{ row }">
                    <StatusBadge
                        :label="row.is_required ? 'Required' : 'Optional'"
                        :tone="row.is_required ? 'info' : 'neutral'"
                    />
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
                        @click="save(row, { is_required: !row.is_required })"
                    >
                        {{
                            row.is_required ? 'Make optional' : 'Make required'
                        }}
                    </button>
                    <button
                        type="button"
                        class="tiny-action"
                        @click="save(row, { is_active: !row.is_active })"
                    >
                        {{ row.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                    <button
                        v-if="row.documents_count === 0"
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
                    placeholder="e.g. Police clearance"
                    aria-label="Category name"
                    :aria-invalid="!!addError"
                />
                <p v-if="addError" class="text-xs text-danger" role="alert">
                    {{ addError }}
                </p>
                <Input
                    v-model="form.description"
                    placeholder="Hint for students (optional)"
                    aria-label="Description"
                />
                <label class="flex items-center gap-2 text-[13px]">
                    <Checkbox
                        :model-value="form.is_required"
                        @update:model-value="
                            (v) => (form.is_required = v === true)
                        "
                    />
                    Required for every student
                </label>
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
        description="Only categories no documents use can be deleted."
        confirm-label="Delete"
        destructive
        @update:open="(v) => !v && (deleting = null)"
        @confirm="remove"
    />
</template>
