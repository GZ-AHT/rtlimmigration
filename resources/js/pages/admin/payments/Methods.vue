<script setup lang="ts">
import { Head, setLayoutProps, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DataTable from '@/components/panel/DataTable.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { Option, TableColumn } from '@/types';

type Method = {
    id: number;
    name: string;
    kind: string;
    kind_label: string;
    instructions: string | null;
    requires_reference: boolean;
    requires_evidence: boolean;
    student_selectable: boolean;
    is_active: boolean;
    in_use: boolean;
};

defineProps<{ methods: Method[]; kinds: Option[] }>();

// This page is mounted in both the admin and accounts areas, so its form
// posts back to whichever area it was opened from.
const page = usePage();
const base = new URL(page.url, 'http://x').pathname;
setLayoutProps({ breadcrumbs: [{ title: 'Payment methods', href: base }] });

const editing = ref<Method | null>(null);
const form = useForm({
    name: '',
    kind: 'bank_transfer',
    instructions: '',
    requires_reference: true,
    requires_evidence: false,
    student_selectable: true,
    is_active: true,
});

const nameError = computed(
    () =>
        form.errors.name ??
        (form.errors as Record<string, string | undefined>).slug,
);

const columns: TableColumn<Method>[] = [
    { key: 'name', label: 'Method' },
    { key: 'student_selectable', label: 'Students can use', hideBelow: 'md' },
    { key: 'is_active', label: 'Status' },
];

const toggles = [
    {
        key: 'requires_reference',
        label: 'Transaction ID / reference is required',
    },
    { key: 'requires_evidence', label: 'Proof of payment upload is required' },
    {
        key: 'student_selectable',
        label: 'Students can choose it when paying from the portal',
    },
    { key: 'is_active', label: 'Active' },
] as const;

function edit(method: Method): void {
    editing.value = method;
    form.clearErrors();
    form.name = method.name;
    form.kind = method.kind;
    form.instructions = method.instructions ?? '';
    form.requires_reference = method.requires_reference;
    form.requires_evidence = method.requires_evidence;
    form.student_selectable = method.student_selectable;
    form.is_active = method.is_active;
}

function cancelEdit(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: cancelEdit };
    form.transform((d) => ({ ...d, instructions: d.instructions || null }));

    if (editing.value) {
        form.put(`${base}/${editing.value.id}`, options);
    } else {
        form.post(base, options);
    }
}
</script>

<template>
    <Head title="Payment methods" />

    <PageHeader
        eyebrow="Payment & accounts"
        title="Payment methods"
        description="How students can pay. Add your bank account or mobile-banking number as instructions so students see where to send money."
    />

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(320px,1fr)]">
        <PanelCard flush>
            <DataTable
                :columns="columns"
                :rows="methods"
                caption="Payment methods"
                empty-title="No payment methods yet"
            >
                <template #cell-name="{ row }">
                    <span class="block font-semibold text-foreground">{{
                        row.name
                    }}</span>
                    <span class="block text-[11px] text-muted-foreground">
                        {{ row.kind_label }}
                        <template v-if="row.requires_reference">
                            · reference required</template
                        >
                        <template v-if="row.requires_evidence">
                            · proof required</template
                        >
                    </span>
                    <span
                        v-if="row.student_selectable && !row.instructions"
                        class="block text-[11px] text-warning"
                        >No payment instructions yet</span
                    >
                </template>
                <template #cell-student_selectable="{ row }">
                    {{ row.student_selectable ? 'Yes' : 'Staff only' }}
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
                </template>
            </DataTable>
        </PanelCard>

        <PanelCard
            :title="editing ? `Edit ${editing.name}` : 'Add payment method'"
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
                    label="Kind"
                    required
                    :error="form.errors.kind"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.kind"
                        :options="kinds"
                        :invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    label="Instructions for students"
                    hint="Account name and number, branch, bKash/Nagad number…"
                    :error="form.errors.instructions"
                >
                    <TextArea
                        :id="id"
                        v-model="form.instructions"
                        :rows="4"
                        :invalid="invalid"
                        :described-by="describedBy"
                    />
                </FormField>
                <label
                    v-for="t in toggles"
                    :key="t.key"
                    class="flex items-center gap-2.5 text-[13px]"
                >
                    <Checkbox
                        :model-value="form[t.key]"
                        @update:model-value="(v) => (form[t.key] = v === true)"
                    />
                    {{ t.label }}
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
                        {{ editing ? 'Save changes' : 'Add method' }}
                    </Button>
                </div>
            </form>
        </PanelCard>
    </div>
</template>
