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
import { destroy, store, update } from '@/routes/admin/blog/taxonomy';
import type { TableColumn } from '@/types';

type Item = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    posts_count: number;
};

const props = defineProps<{
    kind: 'categories' | 'tags';
    items: Item[];
    canManage: boolean;
}>();
const noun = computed(() => (props.kind === 'categories' ? 'category' : 'tag'));
const title = computed(() =>
    props.kind === 'categories' ? 'Blog categories' : 'Blog tags',
);

const form = useForm({ name: '', description: '', is_active: true });
const editing = ref<Item | null>(null);
const editForm = useForm({ name: '', description: '', is_active: true });
const deleting = ref<Item | null>(null);

const columns = computed<TableColumn<Item>[]>(() => [
    { key: 'name', label: props.kind === 'categories' ? 'Category' : 'Tag' },
    ...(props.kind === 'categories'
        ? [{ key: 'is_active', label: 'Status', hideBelow: 'sm' as const }]
        : []),
    { key: 'posts_count', label: 'Posts', align: 'right' },
]);

const onError = (e: Record<string, string>) =>
    toast.error(Object.values(e)[0] ?? 'Could not save.');

function add(): void {
    form.post(store(props.kind).url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function startEdit(item: Item): void {
    editing.value = item;
    editForm.name = item.name;
    editForm.description = item.description ?? '';
    editForm.is_active = item.is_active;
}

function saveEdit(): void {
    if (!editing.value) return;
    editForm.put(update([props.kind, editing.value.id]).url, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
        onError,
    });
}

function remove(): void {
    if (!deleting.value) return;
    router.delete(destroy([props.kind, deleting.value.id]).url, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
        onError,
    });
}
</script>

<template>
    <Head :title="title" />

    <PageHeader
        eyebrow="Blog management"
        :title="title"
        :description="
            kind === 'categories'
                ? 'Group posts by topic. Readers can filter the blog by category.'
                : 'Keywords attached to posts. New tags are also created from the post editor.'
        "
    />

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(280px,1fr)]">
        <PanelCard flush>
            <DataTable
                :columns="columns"
                :rows="items"
                :caption="title"
                :empty-title="`No ${kind} yet`"
            >
                <template #cell-name="{ row }">
                    <form
                        v-if="editing?.id === row.id"
                        class="flex flex-wrap items-center gap-2"
                        @submit.prevent="saveEdit"
                    >
                        <Input
                            v-model="editForm.name"
                            class="h-8 max-w-56"
                            :aria-label="`${noun} name`"
                        />
                        <Input
                            v-if="kind === 'categories'"
                            v-model="editForm.description"
                            class="h-8 max-w-64"
                            placeholder="Description"
                            aria-label="Description"
                        />
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="editForm.processing"
                            >Save</Button
                        >
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="editing = null"
                            >Cancel</Button
                        >
                    </form>
                    <template v-else>
                        <span class="block font-semibold text-foreground">{{
                            row.name
                        }}</span>
                        <span class="block text-[11px] text-muted-foreground"
                            >/{{ row.slug
                            }}<template v-if="row.description">
                                · {{ row.description }}</template
                            ></span
                        >
                    </template>
                </template>
                <template #cell-is_active="{ row }">
                    <StatusBadge
                        :label="row.is_active ? 'Active' : 'Hidden'"
                        :tone="row.is_active ? 'success' : 'neutral'"
                    />
                </template>
                <template v-if="canManage" #actions="{ row }">
                    <button
                        type="button"
                        class="tiny-action"
                        @click="startEdit(row)"
                    >
                        Edit
                    </button>
                    <button
                        v-if="kind === 'categories'"
                        type="button"
                        class="tiny-action"
                        @click="
                            router.put(
                                update([kind, row.id]).url,
                                {
                                    name: row.name,
                                    description: row.description,
                                    is_active: !row.is_active,
                                },
                                { preserveScroll: true, onError },
                            )
                        "
                    >
                        {{ row.is_active ? 'Hide' : 'Show' }}
                    </button>
                    <button
                        type="button"
                        class="tiny-action"
                        @click="deleting = row"
                    >
                        Delete
                    </button>
                </template>
            </DataTable>
        </PanelCard>

        <PanelCard v-if="canManage" :title="`Add ${noun}`" class="self-start">
            <form class="flex flex-col gap-3" @submit.prevent="add">
                <Input
                    v-model="form.name"
                    :placeholder="
                        kind === 'categories'
                            ? 'e.g. Study in Canada'
                            : 'e.g. scholarships'
                    "
                    :aria-label="`${noun} name`"
                    :aria-invalid="!!form.errors.name"
                />
                <p
                    v-if="form.errors.name"
                    class="text-xs text-danger"
                    role="alert"
                >
                    {{ form.errors.name }}
                </p>
                <Input
                    v-if="kind === 'categories'"
                    v-model="form.description"
                    placeholder="Short description (optional)"
                    aria-label="Description"
                />
                <Button
                    type="submit"
                    :disabled="form.processing || !form.name.trim()"
                    >Add {{ noun }}</Button
                >
            </form>
        </PanelCard>
    </div>

    <ConfirmDialog
        :open="deleting !== null"
        :title="`Delete ${deleting?.name}?`"
        :description="
            kind === 'categories'
                ? 'Its posts stay, without a category.'
                : 'It is removed from every post.'
        "
        confirm-label="Delete"
        destructive
        @update:open="(v) => !v && (deleting = null)"
        @confirm="remove"
    />
</template>
