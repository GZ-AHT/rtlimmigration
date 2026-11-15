<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { index, show, store, update } from '@/routes/admin/counselors';
import type { Option } from '@/types';

type EditableCounselor = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    designation: string | null;
    availability: string;
    max_active_leads: number | null;
    bio: string | null;
};

const props = defineProps<{
    counselor: EditableCounselor | null;
    availabilities: Option[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Counselors', href: index() }] },
});

const editing = computed(() => props.counselor !== null);

const form = useForm({
    name: props.counselor?.name ?? '',
    email: props.counselor?.email ?? '',
    phone: props.counselor?.phone ?? '',
    designation: props.counselor?.designation ?? '',
    availability: props.counselor?.availability ?? 'available',
    max_active_leads: props.counselor?.max_active_leads
        ? String(props.counselor.max_active_leads)
        : '',
    bio: props.counselor?.bio ?? '',
    send_invitation: true,
});

function submit(): void {
    form.transform((d) => ({
        ...d,
        phone: d.phone || null,
        designation: d.designation || null,
        max_active_leads: d.max_active_leads
            ? Number(d.max_active_leads)
            : null,
        bio: d.bio || null,
    }));
    form.submit(editing.value ? update(props.counselor!.id) : store(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="editing ? `Edit ${counselor?.name}` : 'Add counselor'" />

    <PageHeader
        eyebrow="Counselor management"
        :title="editing ? `Edit ${counselor?.name}` : 'Add counselor'"
        :description="
            editing
                ? 'Update profile, availability and workload limit.'
                : 'Creates a staff account with the Counselor role and emails them an invitation.'
        "
    />

    <form
        class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(300px,1fr)]"
        novalidate
        @submit.prevent="submit"
    >
        <PanelCard title="Profile">
            <div class="grid gap-3.5 sm:grid-cols-2">
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
                <FormField
                    v-slot="{ id, invalid }"
                    label="Email address"
                    required
                    :error="form.errors.email"
                >
                    <Input
                        :id="id"
                        v-model="form.email"
                        type="email"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Phone"
                    :error="form.errors.phone"
                >
                    <Input
                        :id="id"
                        v-model="form.phone"
                        type="tel"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Designation"
                    :error="form.errors.designation"
                >
                    <Input
                        :id="id"
                        v-model="form.designation"
                        placeholder="e.g. Senior Counselor"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <div class="sm:col-span-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Short bio"
                        :error="form.errors.bio"
                    >
                        <TextArea
                            :id="id"
                            v-model="form.bio"
                            :rows="3"
                            :maxlength="2000"
                            :invalid="invalid"
                        />
                    </FormField>
                </div>
            </div>
            <label
                v-if="!editing"
                class="mt-5 flex items-start gap-2.5 rounded-lg border bg-muted p-3 text-[13px]"
            >
                <Checkbox
                    :model-value="form.send_invitation"
                    class="mt-0.5"
                    @update:model-value="
                        (v) => (form.send_invitation = v === true)
                    "
                />
                <span
                    ><b class="block">Send an invitation email</b
                    ><span class="text-xs text-muted-foreground"
                        >They choose their own password from a secure
                        link.</span
                    ></span
                >
            </label>
        </PanelCard>

        <PanelCard title="Workload">
            <div class="flex flex-col gap-3.5">
                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    label="Availability"
                    required
                    hint="Shown when leads are being assigned."
                    :error="form.errors.availability"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.availability"
                        :options="availabilities"
                        :invalid="invalid"
                        :described-by="describedBy"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    label="Open lead limit"
                    hint="Optional. A warning shows when assigning beyond it."
                    :error="form.errors.max_active_leads"
                >
                    <Input
                        :id="id"
                        v-model="form.max_active_leads"
                        type="number"
                        min="1"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
            </div>
        </PanelCard>

        <div class="flex justify-end gap-2 lg:col-span-2">
            <Button variant="outline" as-child
                ><Link :href="editing ? show(counselor!.id) : index()"
                    >Cancel</Link
                ></Button
            >
            <Button type="submit" :disabled="form.processing"
                ><Spinner v-if="form.processing" />
                {{ editing ? 'Save changes' : 'Add counselor' }}</Button
            >
        </div>
    </form>
</template>
