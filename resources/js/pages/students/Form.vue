<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { CounselorOption, Option } from '@/types';

const props = defineProps<{
    counselors: CounselorOption[];
    assignsSelf: boolean;
    urls: { submit: string; cancel: string };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Students', href: props.urls.cancel }],
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    counselor_id: '',
    send_invitation: true,
});

const counselorOptions = computed<Option[]>(() =>
    props.counselors.map((c) => ({
        value: c.value,
        label: `${c.label}${c.availability !== 'available' ? ` · ${c.availability_label}` : ''}`,
    })),
);

function submit(): void {
    form.transform((d) => ({
        ...d,
        phone: d.phone || null,
        counselor_id: d.counselor_id || null,
    })).post(props.urls.submit, { preserveScroll: true });
}
</script>

<template>
    <Head title="Add student" />

    <PageHeader
        eyebrow="Student management"
        title="Add student"
        description="Create a client account on their behalf. A unique student number is assigned, and they receive an email to set their password."
    />

    <form
        class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(300px,1fr)]"
        novalidate
        @submit.prevent="submit"
    >
        <PanelCard title="Student account">
            <div class="grid gap-3.5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Full name"
                        required
                        :error="form.errors.name"
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                </div>
                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    label="Email address"
                    required
                    hint="Used to sign in."
                    :error="form.errors.email"
                >
                    <Input
                        :id="id"
                        v-model="form.email"
                        type="email"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Phone / WhatsApp"
                    :error="form.errors.phone"
                >
                    <Input
                        :id="id"
                        v-model="form.phone"
                        type="tel"
                        :aria-invalid="invalid"
                    />
                </FormField>
            </div>
            <label
                class="mt-5 flex items-start gap-2.5 rounded-lg border bg-muted p-3 text-[13px]"
            >
                <Checkbox
                    :model-value="form.send_invitation"
                    class="mt-0.5"
                    @update:model-value="
                        (v) => (form.send_invitation = v === true)
                    "
                />
                <span>
                    <b class="block">Send an invitation email</b>
                    <span class="text-xs text-muted-foreground"
                        >A secure link (valid 72 hours) lets them set a
                        password. No password is sent by email.</span
                    >
                </span>
            </label>
        </PanelCard>

        <PanelCard title="Counselor">
            <p v-if="assignsSelf" class="text-[13px] text-muted-foreground">
                You'll be assigned as this student's counselor.
            </p>
            <FormField
                v-else
                v-slot="{ id, invalid, describedBy }"
                label="Assigned counselor"
                hint="Optional. You can assign one later."
                :error="form.errors.counselor_id"
            >
                <SelectInput
                    :id="id"
                    v-model="form.counselor_id"
                    :options="counselorOptions"
                    placeholder="Unassigned"
                    :invalid="invalid"
                    :described-by="describedBy"
                />
            </FormField>
        </PanelCard>

        <div class="flex justify-end gap-2 lg:col-span-2">
            <Button variant="outline" as-child
                ><Link :href="urls.cancel">Cancel</Link></Button
            >
            <Button type="submit" :disabled="form.processing"
                ><Spinner v-if="form.processing" /> Create student</Button
            >
        </div>
    </form>
</template>
