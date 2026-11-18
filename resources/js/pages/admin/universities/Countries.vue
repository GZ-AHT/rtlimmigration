<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SearchInput from '@/components/panel/SearchInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { flag } from '@/lib/flag';
import { index, store, update } from '@/routes/admin/countries';
import type { TableColumn } from '@/types';

/**
 * Countries used as study and skill migration destinations (REQ §15).
 * Add, rename, make a destination, deactivate.
 */
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
    { key: 'is_destination', label: 'Destination' },
    { key: 'is_active', label: 'Status', hideBelow: 'sm' },
    {
        key: 'universities_count',
        label: 'Universities',
        align: 'right',
        hideBelow: 'md',
    },
];

const patch = (row: Row, data: Partial<Row>) =>
    router.patch(update(row.id).url, data, {
        preserveScroll: true,
        only: ['countries', 'flash'],
    });

const open = ref(false);
const editing = ref<Row | null>(null);
const form = useForm({ name: '', iso2: '', is_destination: true });

function openNew(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(row: Row): void {
    editing.value = row;
    Object.assign(form, {
        name: row.name,
        iso2: row.iso2,
        is_destination: row.is_destination,
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
        form.patch(update(editing.value.id).url, options);
    } else {
        form.post(store().url, options);
    }
}
</script>

<template>
    <Head title="Countries" />

    <PageHeader
        eyebrow="Destinations"
        title="Countries"
        description="Destinations appear in the pathway finder, skill migration assessments and when adding universities. Deactivated countries are hidden from the website."
    >
        <template v-if="canManage" #actions>
            <Button @click="openNew"
                ><AppIcon name="add" :size="18" /> Add country</Button
            >
        </template>
    </PageHeader>

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
                <span class="mr-1.5" aria-hidden="true">{{
                    flag(row.iso2)
                }}</span>
                <span class="font-semibold">{{ row.name }}</span>
                <span class="ml-1 text-[11px] text-muted-foreground">{{
                    row.iso2
                }}</span>
            </template>
            <template #cell-is_destination="{ row }">
                <StatusBadge
                    :label="row.is_destination ? 'Destination' : 'No'"
                    :tone="row.is_destination ? 'success' : 'neutral'"
                />
            </template>
            <template #cell-is_active="{ row }">
                <StatusBadge
                    :label="row.is_active ? 'Active' : 'Inactive'"
                    :tone="row.is_active ? 'success' : 'warning'"
                />
            </template>
            <template v-if="canManage" #actions="{ row }">
                <button
                    type="button"
                    class="tiny-action"
                    @click="openEdit(row)"
                >
                    Edit
                </button>
                <button
                    type="button"
                    class="tiny-action"
                    @click="patch(row, { is_destination: !row.is_destination })"
                >
                    {{
                        row.is_destination
                            ? 'Remove as destination'
                            : 'Make destination'
                    }}
                </button>
                <button
                    type="button"
                    class="tiny-action"
                    @click="patch(row, { is_active: !row.is_active })"
                >
                    {{ row.is_active ? 'Deactivate' : 'Activate' }}
                </button>
            </template>
        </DataTable>
    </PanelCard>

    <Dialog v-model:open="open">
        <DialogContent class="rounded-[14px] sm:max-w-[440px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">{{
                    editing ? 'Edit country' : 'Add country'
                }}</DialogTitle>
                <DialogDescription class="text-xs"
                    >Countries can be study and skill migration
                    destinations.</DialogDescription
                >
            </DialogHeader>
            <form class="flex flex-col gap-3.5" @submit.prevent="save">
                <FormField
                    v-slot="{ id, invalid }"
                    label="Name"
                    required
                    :error="form.errors.name"
                >
                    <Input
                        :id="id"
                        v-model="form.name"
                        maxlength="80"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    label="Two-letter code"
                    required
                    hint="ISO code, e.g. DE for Germany. Used for the flag."
                    :error="form.errors.iso2"
                >
                    <Input
                        :id="id"
                        v-model="form.iso2"
                        maxlength="2"
                        class="uppercase"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <label class="flex items-center gap-2 text-[13px]">
                    <Checkbox
                        :model-value="form.is_destination"
                        @update:model-value="
                            (v) => (form.is_destination = v === true)
                        "
                    />
                    Study / migration destination
                </label>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing">{{
                        editing ? 'Save' : 'Add country'
                    }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
