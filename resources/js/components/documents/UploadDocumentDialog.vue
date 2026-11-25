<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import FileUpload from '@/components/panel/FileUpload.vue';
import FormField from '@/components/panel/FormField.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { Option, UploadLimits } from '@/types';

/** Upload (or replace) a student document. The server re-validates type and size. */
const props = defineProps<{
    submitUrl: string;
    categories: Option[];
    files: Option[];
    limits: UploadLimits;
    presetCategoryId?: number | null;
    replacing?: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    file: [] as File[],
    category_id: '',
    student_file_id: '',
    title: '',
    expires_at: '',
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
        form.category_id = props.presetCategoryId
            ? String(props.presetCategoryId)
            : '';
    }
});

const accept = computed(() =>
    props.limits.extensions.map((e) => `.${e}`).join(','),
);
const fileError = computed(
    () =>
        Object.entries(form.errors).find(
            ([k]) => k === 'file' || k.startsWith('file.'),
        )?.[1],
);

function submit(): void {
    form.transform((d) => ({
        file: d.file[0] ?? null,
        category_id: d.category_id || null,
        student_file_id: d.student_file_id || null,
        title: d.title || null,
        expires_at: d.expires_at || null,
    })).post(props.submitUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="rounded-[14px] sm:max-w-[520px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">{{
                    replacing ? 'Replace document' : 'Upload document'
                }}</DialogTitle>
                <DialogDescription class="text-xs">
                    {{
                        replacing
                            ? 'The current version is kept in the history. The new one goes for review.'
                            : 'It will be reviewed by your counselor.'
                    }}
                </DialogDescription>
            </DialogHeader>
            <form
                class="flex min-w-0 flex-col gap-3.5"
                @submit.prevent="submit"
            >
                <FileUpload
                    v-model="form.file"
                    label="Choose a file"
                    :accept="accept"
                    :max-size-mb="limits.max_mb"
                    :error="fileError"
                    :hint="`${limits.extensions.join(', ').toUpperCase()} · up to ${limits.max_mb} MB`"
                />
                <FormField
                    v-slot="{ id, invalid }"
                    label="Document type"
                    :error="form.errors.category_id"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.category_id"
                        :options="categories"
                        placeholder="Other (name it below)"
                        :invalid="invalid"
                        :disabled="!!presetCategoryId"
                    />
                </FormField>
                <FormField
                    v-if="!form.category_id"
                    v-slot="{ id, invalid }"
                    label="Document name"
                    required
                    :error="form.errors.title"
                >
                    <Input
                        :id="id"
                        v-model="form.title"
                        placeholder="e.g. Work experience letter"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <div class="grid gap-3.5 sm:grid-cols-2">
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
                            placeholder="All files"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid, describedBy }"
                        label="Expires on"
                        hint="For passports, test results…"
                        :error="form.errors.expires_at"
                    >
                        <Input
                            :id="id"
                            v-model="form.expires_at"
                            type="date"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </FormField>
                </div>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.file.length"
                    >
                        <Spinner v-if="form.processing" /> Upload
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
