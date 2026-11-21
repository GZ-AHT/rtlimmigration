<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SearchInput from '@/components/panel/SearchInput.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { destroy, index, store, update } from '@/routes/admin/seo/redirects';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    from_path: string;
    to_url: string;
    status_code: number;
    is_active: boolean;
    hits: number;
    last_hit_at: string | null;
    note: string;
};

const props = defineProps<{
    redirects: Paginated<Row>;
    filters: { search: string };
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Redirects', href: index() }] },
});

const search = ref(props.filters.search);
watch(search, (value) =>
    router.get(
        index().url,
        { search: value || undefined },
        { preserveState: true, replace: true },
    ),
);

const newHint = 'A site path like /new-page, or a full https:// address.';
const statusOptions = [
    { value: '301', label: '301 · Moved permanently' },
    { value: '302', label: '302 · Temporary' },
];
const editing = ref<Row | null>(null);
const open = ref(false);
const deleting = ref<Row | null>(null);
const form = useForm({
    from_path: '',
    to_url: '',
    status_code: '301',
    is_active: true,
    note: '',
});

function openNew(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(r: Row): void {
    editing.value = r;
    Object.assign(form, {
        from_path: r.from_path,
        to_url: r.to_url,
        status_code: String(r.status_code),
        is_active: r.is_active,
        note: r.note,
    });
    form.clearErrors();
    open.value = true;
}

function save(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (editing.value) {
        form.put(update(editing.value.id).url, options);
    } else {
        form.post(store().url, options);
    }
}
</script>

<template>
    <Head title="Redirects" />

    <PageHeader
        eyebrow="SEO management"
        title="Redirects"
        description="Send visitors and search engines from an old address to its new home. Use 301 for permanent moves and 302 for temporary ones."
    >
        <template #actions>
            <Button @click="openNew"
                ><AppIcon name="add" :size="18" /> Add redirect</Button
            >
        </template>
    </PageHeader>

    <PanelCard :title="`Redirects (${redirects.total})`">
        <template #actions>
            <SearchInput
                v-model="search"
                placeholder="Search paths…"
                label="Search redirects"
            />
        </template>
        <p
            v-if="redirects.data.length === 0"
            class="py-6 text-center text-[13px] text-muted-foreground"
        >
            No redirects yet.
        </p>
        <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                <thead class="text-[11px] text-muted-foreground uppercase">
                    <tr>
                        <th class="py-2 pr-3 font-semibold">From</th>
                        <th class="py-2 pr-3 font-semibold">To</th>
                        <th class="py-2 pr-3 font-semibold">Type</th>
                        <th class="py-2 pr-3 font-semibold">Hits</th>
                        <th class="py-2 pr-3 font-semibold">Status</th>
                        <th class="py-2 font-semibold">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="r in redirects.data"
                        :key="r.id"
                        class="border-t align-top"
                    >
                        <td class="py-2.5 pr-3 font-mono text-[12px]">
                            {{ r.from_path }}
                            <p
                                v-if="r.note"
                                class="font-sans text-muted-foreground"
                            >
                                {{ r.note }}
                            </p>
                        </td>
                        <td class="py-2.5 pr-3 font-mono text-[12px] break-all">
                            {{ r.to_url }}
                        </td>
                        <td class="py-2.5 pr-3">{{ r.status_code }}</td>
                        <td class="py-2.5 pr-3">
                            {{ r.hits
                            }}<span
                                v-if="r.last_hit_at"
                                class="block text-[11px] text-muted-foreground"
                                >{{ r.last_hit_at }}</span
                            >
                        </td>
                        <td class="py-2.5 pr-3">
                            <StatusBadge
                                :label="r.is_active ? 'Active' : 'Off'"
                                :tone="r.is_active ? 'success' : 'neutral'"
                            />
                        </td>
                        <td class="py-2.5 text-right whitespace-nowrap">
                            <button
                                type="button"
                                class="tiny-action"
                                @click="openEdit(r)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="tiny-action"
                                @click="deleting = r"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <TablePagination :meta="redirects" />
    </PanelCard>

    <Dialog v-model:open="open">
        <DialogContent class="rounded-[14px] sm:max-w-[520px]">
            <DialogHeader
                ><DialogTitle class="font-display text-xl font-extrabold">{{
                    editing ? 'Edit redirect' : 'Add redirect'
                }}</DialogTitle></DialogHeader
            >
            <form class="flex flex-col gap-3.5" @submit.prevent="save">
                <FormField
                    v-slot="{ id, invalid }"
                    label="Old path"
                    required
                    :error="form.errors.from_path"
                    hint="For example /old-page. Not case-sensitive."
                >
                    <Input
                        :id="id"
                        v-model="form.from_path"
                        maxlength="255"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="New address"
                    required
                    :error="form.errors.to_url"
                    :hint="newHint"
                >
                    <Input
                        :id="id"
                        v-model="form.to_url"
                        maxlength="500"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Type"
                    :error="form.errors.status_code"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.status_code"
                        :options="statusOptions"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Note"
                    :error="form.errors.note"
                >
                    <Input :id="id" v-model="form.note" maxlength="255" />
                </FormField>
                <label class="flex items-center gap-2 text-[13px]"
                    ><Checkbox
                        :model-value="form.is_active"
                        @update:model-value="
                            (v) => (form.is_active = v === true)
                        "
                    />
                    Active</label
                >
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="deleting !== null"
        title="Delete this redirect?"
        description="Visitors to the old address will see the normal page or a 404."
        confirm-label="Delete"
        destructive
        @update:open="(v) => !v && (deleting = null)"
        @confirm="
            deleting &&
            router.delete(destroy(deleting.id).url, {
                preserveScroll: true,
                onFinish: () => (deleting = null),
            })
        "
    />
</template>
