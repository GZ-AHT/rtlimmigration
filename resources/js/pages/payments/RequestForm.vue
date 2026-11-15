<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { Option } from '@/types';

type TypeOption = Option & { default_amount: string | null };

const props = defineProps<{
    student: { id: number; name: string; number: string } | null;
    students: Option[];
    files: Option[];
    presetFile: string;
    types: TypeOption[];
    currency: string;
    urls: { submit: string; cancel: string; self: string };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Payment requests', href: props.urls.cancel }],
});

const form = useForm({
    student_id: props.student ? String(props.student.id) : '',
    payment_type_id: '',
    amount: '',
    due_date: '',
    student_file_id: props.presetFile,
    description: '',
    notes: '',
});

// Pre-fill the type's default amount, if it has one.
watch(
    () => form.payment_type_id,
    (id) => {
        const t = props.types.find((x) => x.value === id);

        if (t?.default_amount && !form.amount) {
            form.amount = t.default_amount;
        }
    },
);

// Picking a student reloads the page to offer that student's files.
function chooseStudent(id: string): void {
    if (id) {
        router.get(props.urls.self, { student: id }, { preserveState: false });
    }
}

const minDate = new Date().toISOString().slice(0, 10);

function submit(): void {
    form.transform((d) => ({
        ...d,
        due_date: d.due_date || null,
        student_file_id: d.student_file_id || null,
        description: d.description || null,
        notes: d.notes || null,
    })).post(props.urls.submit, { preserveScroll: true });
}
</script>

<template>
    <Head title="New payment request" />

    <PageHeader
        eyebrow="Payments"
        title="New payment request"
        :description="
            student
                ? `For ${student.name} · ${student.number}`
                : 'Raise a fee for a student. They are notified and can pay from their portal.'
        "
    />

    <form
        class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(300px,1fr)]"
        novalidate
        @submit.prevent="submit"
    >
        <PanelCard title="Invoice">
            <div class="grid gap-3.5 sm:grid-cols-2">
                <div v-if="!student" class="sm:col-span-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Student"
                        required
                        :error="form.errors.student_id"
                    >
                        <SelectInput
                            :id="id"
                            :model-value="form.student_id"
                            :options="students"
                            placeholder="Choose a student"
                            :invalid="invalid"
                            @update:model-value="chooseStudent"
                        />
                    </FormField>
                </div>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Fee type"
                    required
                    :error="form.errors.payment_type_id"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.payment_type_id"
                        :options="types"
                        placeholder="Choose"
                        :invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    :label="`Amount (${currency})`"
                    required
                    :error="form.errors.amount"
                >
                    <Input
                        :id="id"
                        v-model="form.amount"
                        inputmode="decimal"
                        placeholder="e.g. 5000"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    label="Due date"
                    hint="Shown as overdue after this date."
                    :error="form.errors.due_date"
                >
                    <Input
                        :id="id"
                        v-model="form.due_date"
                        type="date"
                        :min="minDate"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <FormField
                    v-if="files.length"
                    v-slot="{ id, invalid }"
                    label="For file"
                    :error="form.errors.student_file_id"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.student_file_id"
                        :options="files"
                        placeholder="Not linked to a file"
                        :invalid="invalid"
                    />
                </FormField>
                <div class="sm:col-span-2">
                    <FormField
                        v-slot="{ id, invalid, describedBy }"
                        label="Description"
                        hint="Shown to the student on the invoice."
                        :error="form.errors.description"
                    >
                        <Input
                            :id="id"
                            v-model="form.description"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </FormField>
                </div>
            </div>
        </PanelCard>
        <PanelCard title="Internal note" description="Visible to staff only.">
            <TextArea
                v-model="form.notes"
                :rows="4"
                aria-label="Internal note"
            />
        </PanelCard>
        <div class="flex justify-end gap-2 lg:col-span-2">
            <Button variant="outline" as-child
                ><Link :href="urls.cancel">Cancel</Link></Button
            >
            <Button
                type="submit"
                :disabled="form.processing || !form.student_id"
                ><Spinner v-if="form.processing" /> Raise invoice</Button
            >
        </div>
    </form>
</template>
