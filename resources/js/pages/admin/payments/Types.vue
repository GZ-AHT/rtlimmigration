<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { destroy, index, store, update } from '@/routes/admin/payment-types';
import type { TableColumn } from '@/types';

type PaymentType = {
    id: number;
    name: string;
    description: string | null;
    default_amount: string | null;
    default_amount_label: string | null;
    generate_on_file_open: boolean;
    is_active: boolean;
    in_use: boolean;
};

defineProps<{ types: PaymentType[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Payment types', href: index() }] },
});

const editing = ref<PaymentType | null>(null);
const deleting = ref<PaymentType | null>(null);
const form = useForm({
    name: '',
    description: '',
    default_amount: '',
    generate_on_file_open: false,
    is_active: true,
});

// The server validates a slug derived from the name; show either error.
const nameError = computed(
    () =>
        form.errors.name ??
        (form.errors as Record<string, string | undefined>).slug,
);

const columns: TableColumn<PaymentType>[] = [
    { key: 'name', label: 'Fee type' },
    { key: 'default_amount_label', label: 'Default amount', align: 'right' },
    { key: 'is_active', label: 'Status', hideBelow: 'sm' },
];

function edit(type: PaymentType): void {
    editing.value = type;
    form.clearErrors();
    form.name = type.name;
    form.description = type.description ?? '';
    form.default_amount = type.default_amount ?? '';
    form.generate_on_file_open = type.generate_on_file_open;
    form.is_active = type.is_active;
}

function cancelEdit(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
}

function submit(): void {
    form.transform((d) => ({
        ...d,
        description: d.description || null,
        default_amount: d.default_amount || null,
    })).submit(editing.value ? update(editing.value.id) : store(), {
        preserveScroll: true,
        onSuccess: cancelEdit,
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
    <Head title="Payment types" />

    <PageHeader
        eyebrow="Payment & accounts"
        title="Payment types"
        description="The fees that can be raised against a student. Set a default amount to pre-fill new payment requests."
    />

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(300px,1fr)]">
        <PanelCard flush>
            <DataTable
                :columns="columns"
                :rows="types"
                caption="Payment types"
                empty-title="No payment types yet"
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
                    <StatusBadge
                        v-if="row.generate_on_file_open"
                        class="mt-1"
                        :label="
                            row.default_amount
                                ? 'Raised when a file opens'
                                : 'On file open: needs an amount'
                        "
                        :tone="row.default_amount ? 'info' : 'warning'"
                    />
                </template>
                <template #cell-default_amount_label="{ row }">
                    <b
                        v-if="row.default_amount_label"
                        class="text-foreground"
                        >{{ row.default_amount_label }}</b
                    >
                    <span v-else class="text-muted-foreground">Not set</span>
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
                        @click="edit(row)"
                    >
                        Edit
                    </button>
                    <button
                        v-if="!row.in_use"
                        type="button"
                        class="tiny-action"
                        @click="deleting = row"
                    >
                        Delete
                    </button>
                </template>
            </DataTable>
        </PanelCard>

        <PanelCard
            :title="editing ? `Edit ${editing.name}` : 'Add payment type'"
            class="self-start"
        >
            <form
                class="flex flex-col gap-3.5"
                novalidate
                @submit.prevent="submit"
            >
                <FormField
                    v-slot="{ id, invalid }"
                    label="Name"
                    required
                    :error="nameError"
                >
                    <Input
                        :id="id"
                        v-model="form.name"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Description"
                    :error="form.errors.description"
                >
                    <Input
                        :id="id"
                        v-model="form.description"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    label="Default amount (BDT)"
                    hint="Optional. Leave empty to enter the amount each time."
                    :error="form.errors.default_amount"
                >
                    <Input
                        :id="id"
                        v-model="form.default_amount"
                        inputmode="decimal"
                        placeholder="e.g. 5000"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <label class="flex items-start gap-2.5 text-[13px]">
                    <Checkbox
                        class="mt-0.5"
                        :model-value="form.generate_on_file_open"
                        @update:model-value="
                            (v) => (form.generate_on_file_open = v === true)
                        "
                    />
                    <span>
                        Raise automatically when a student file is opened
                        <span class="block text-xs text-muted-foreground"
                            >Needs a default amount. Without one, the counselor
                            is asked to raise it by hand.</span
                        >
                    </span>
                </label>
                <label class="flex items-center gap-2.5 text-[13px]">
                    <Checkbox
                        :model-value="form.is_active"
                        @update:model-value="
                            (v) => (form.is_active = v === true)
                        "
                    />
                    Active
                </label>
                <div class="flex justify-end gap-2">
                    <Button
                        v-if="editing"
                        type="button"
                        variant="outline"
                        @click="cancelEdit"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.name.trim()"
                    >
                        {{ editing ? 'Save changes' : 'Add type' }}
                    </Button>
                </div>
            </form>
        </PanelCard>
    </div>

    <ConfirmDialog
        :open="deleting !== null"
        :title="`Delete ${deleting?.name}?`"
        description="Only types that no invoice uses can be deleted."
        confirm-label="Delete"
        destructive
        @update:open="(v) => !v && (deleting = null)"
        @confirm="remove"
    />
</template>
