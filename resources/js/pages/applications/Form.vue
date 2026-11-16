<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { Option } from '@/types';

type TypeOption = Option & {
    kind: string;
    description: string | null;
    labels: { title: string; organisation: string; schedule: string };
};

const props = defineProps<{
    student: { id: number; name: string; number: string } | null;
    students: Option[];
    files: Option[];
    presetFile: string;
    types: TypeOption[];
    presetKind: string | null;
    countries: Option[];
    urls: { submit: string; cancel: string; self: string };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Visa processing', href: props.urls.cancel }],
});

const form = useForm({
    student_id: props.student ? String(props.student.id) : '',
    visa_type_id:
        props.types.find((t) => t.kind === props.presetKind)?.value ?? '',
    country_id: '',
    student_file_id: props.presetFile,
    title: '',
    organisation: '',
    reference_number: '',
    scheduled_at: '',
    notes: '',
});

const type = computed(() =>
    props.types.find((t) => t.value === form.visa_type_id),
);

// Picking a student reloads the page to offer that student's files.
function chooseStudent(id: string): void {
    if (id) {
        router.get(
            props.urls.self,
            { student: id, kind: props.presetKind ?? undefined },
            { preserveState: false },
        );
    }
}

function submit(): void {
    form.transform((d) => ({
        ...d,
        country_id: d.country_id || null,
        student_file_id: d.student_file_id || null,
        scheduled_at: d.scheduled_at || null,
    })).post(props.urls.submit);
}
</script>

<template>
    <Head title="New application" />

    <PageHeader
        eyebrow="Visa processing"
        title="New application"
        description="The visa type and country decide which workflow, checklist and fee apply. The student is notified when it starts."
    />

    <form class="max-w-[820px]" novalidate @submit.prevent="submit">
        <PanelCard title="Who and what">
            <div class="grid gap-3.5 sm:grid-cols-2">
                <FormField
                    v-slot="{ id, invalid }"
                    label="Student"
                    required
                    :error="form.errors.student_id"
                >
                    <Input
                        v-if="student"
                        :id="id"
                        :model-value="`${student.name} · ${student.number}`"
                        readonly
                    />
                    <SelectInput
                        v-else
                        :id="id"
                        v-model="form.student_id"
                        :options="students"
                        placeholder="Choose a student"
                        :invalid="invalid"
                        @update:model-value="chooseStudent"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Student file"
                    hint="Links the application to the file's invoices."
                    :error="form.errors.student_file_id"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.student_file_id"
                        :options="files"
                        :placeholder="
                            student ? 'No file' : 'Choose a student first'
                        "
                        :disabled="!student"
                        :invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Visa type"
                    required
                    :hint="type?.description ?? undefined"
                    :error="form.errors.visa_type_id"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.visa_type_id"
                        :options="types"
                        placeholder="Choose a visa type"
                        :invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Destination country"
                    :error="form.errors.country_id"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.country_id"
                        :options="countries"
                        placeholder="Not set"
                        :invalid="invalid"
                    />
                </FormField>
            </div>
        </PanelCard>

        <PanelCard title="Details" class="mt-4">
            <div class="grid gap-3.5 sm:grid-cols-2">
                <FormField
                    v-slot="{ id, invalid }"
                    :label="type?.labels.title ?? 'Programme / job / purpose'"
                    :error="form.errors.title"
                >
                    <Input
                        :id="id"
                        v-model="form.title"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    :label="
                        type?.labels.organisation ?? 'Institution / employer'
                    "
                    :error="form.errors.organisation"
                >
                    <Input
                        :id="id"
                        v-model="form.organisation"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Reference number"
                    hint="Embassy, portal or registration number, if there is one yet."
                    :error="form.errors.reference_number"
                >
                    <Input
                        :id="id"
                        v-model="form.reference_number"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    :label="type?.labels.schedule ?? 'Interview date'"
                    :error="form.errors.scheduled_at"
                >
                    <Input
                        :id="id"
                        v-model="form.scheduled_at"
                        type="datetime-local"
                        :aria-invalid="invalid"
                    />
                </FormField>
            </div>
            <FormField
                v-slot="{ id, invalid }"
                label="Internal notes"
                hint="Only staff see these."
                class="mt-3.5"
                :error="form.errors.notes"
            >
                <TextArea
                    :id="id"
                    v-model="form.notes"
                    :rows="3"
                    :invalid="invalid"
                />
            </FormField>
        </PanelCard>

        <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" as-child
                ><Link :href="urls.cancel">Cancel</Link></Button
            >
            <Button type="submit" :disabled="form.processing"
                ><Spinner v-if="form.processing" /> Start application</Button
            >
        </div>
    </form>
</template>
