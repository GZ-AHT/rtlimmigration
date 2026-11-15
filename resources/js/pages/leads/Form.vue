<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { CounselorOption, Option } from '@/types';

type EditableLead = {
    id: number;
    lead_number: string;
    name: string;
    email: string | null;
    phone: string | null;
    country_id: number | null;
    service_id: number | null;
    contact_preference: string | null;
    message: string | null;
    source_value: string;
};

const props = defineProps<{
    lead: EditableLead | null;
    options: {
        countries: Option[];
        services: Option[];
        channels: Option[];
        manualSources: Option[];
        counselors: CounselorOption[];
    };
    canAssign: boolean;
    urls: { submit: string; cancel: string };
}>();

const editing = computed(() => props.lead !== null);

setLayoutProps({ breadcrumbs: [{ title: 'Leads', href: props.urls.cancel }] });

const form = useForm({
    name: props.lead?.name ?? '',
    email: props.lead?.email ?? '',
    phone: props.lead?.phone ?? '',
    country_id: props.lead?.country_id ? String(props.lead.country_id) : '',
    service_id: props.lead?.service_id ? String(props.lead.service_id) : '',
    contact_preference: props.lead?.contact_preference ?? '',
    message: props.lead?.message ?? '',
    source: props.lead?.source_value ?? 'manual',
    counselor_id: '',
});

const counselorOptions = computed<Option[]>(() =>
    props.options.counselors.map((c) => ({
        value: c.value,
        label: `${c.label} · ${c.open_leads} open${c.capacity ? ` / ${c.capacity}` : ''}${c.availability !== 'available' ? ` · ${c.availability_label}` : ''}`,
    })),
);

function submit(): void {
    form.transform((data) => {
        const payload: Record<string, unknown> = {
            ...data,
            email: data.email || null,
            phone: data.phone || null,
            country_id: data.country_id || null,
            service_id: data.service_id || null,
            contact_preference: data.contact_preference || null,
            message: data.message || null,
        };

        if (editing.value) {
            delete payload.source;
            delete payload.counselor_id;
        } else if (!data.counselor_id) {
            delete payload.counselor_id;
        }

        return payload;
    });

    if (editing.value) {
        form.put(props.urls.submit, { preserveScroll: true });
    } else {
        form.post(props.urls.submit, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="editing ? `Edit ${lead?.lead_number}` : 'New lead'" />

    <PageHeader
        eyebrow="Lead management"
        :title="editing ? `Edit ${lead?.name}` : 'New lead'"
        :description="
            editing
                ? lead?.lead_number
                : 'Record an enquiry from a phone call, walk-in, referral or other channel.'
        "
    />

    <form
        class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(300px,1fr)]"
        novalidate
        @submit.prevent="submit"
    >
        <PanelCard
            title="Contact details"
            description="Enter at least an email address or a phone number."
        >
            <div class="grid gap-3.5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <FormField
                        v-slot="{ id, describedBy, invalid }"
                        label="Full name"
                        required
                        :error="form.errors.name"
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                </div>
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Email address"
                    :error="form.errors.email"
                >
                    <Input
                        :id="id"
                        v-model="form.email"
                        type="email"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Phone / WhatsApp"
                    hint="Include the country code."
                    :error="form.errors.phone"
                >
                    <Input
                        :id="id"
                        v-model="form.phone"
                        type="tel"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Interested in"
                    :error="form.errors.service_id"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.service_id"
                        :options="options.services"
                        placeholder="Choose a service"
                        :described-by="describedBy"
                        :invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Preferred destination"
                    :error="form.errors.country_id"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.country_id"
                        :options="options.countries"
                        placeholder="Not decided"
                        :described-by="describedBy"
                        :invalid="invalid"
                    />
                </FormField>
                <div class="sm:col-span-2">
                    <FormField
                        v-slot="{ id, describedBy, invalid }"
                        label="Message / notes from the enquiry"
                        :error="form.errors.message"
                    >
                        <TextArea
                            :id="id"
                            v-model="form.message"
                            :maxlength="2000"
                            :described-by="describedBy"
                            :invalid="invalid"
                        />
                    </FormField>
                </div>
            </div>
        </PanelCard>

        <div class="flex flex-col gap-4">
            <PanelCard title="Lead details">
                <div class="flex flex-col gap-3.5">
                    <FormField
                        v-if="!editing"
                        v-slot="{ id, describedBy, invalid }"
                        label="Source"
                        required
                        :error="form.errors.source"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.source"
                            :options="options.manualSources"
                            :described-by="describedBy"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, describedBy, invalid }"
                        label="Prefers contact by"
                        :error="form.errors.contact_preference"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.contact_preference"
                            :options="options.channels"
                            placeholder="No preference"
                            :described-by="describedBy"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-if="!editing && canAssign"
                        v-slot="{ id, describedBy, invalid }"
                        label="Assign to counselor"
                        hint="Optional. You can assign it later."
                        :error="form.errors.counselor_id"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.counselor_id"
                            :options="counselorOptions"
                            placeholder="Leave unassigned"
                            :described-by="describedBy"
                            :invalid="invalid"
                        />
                    </FormField>
                </div>
            </PanelCard>
        </div>

        <div class="flex justify-end gap-2 lg:col-span-2">
            <Button variant="outline" as-child
                ><Link :href="urls.cancel">Cancel</Link></Button
            >
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ editing ? 'Save changes' : 'Create lead' }}
            </Button>
        </div>
    </form>
</template>
