<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import FormField from '@/components/panel/FormField.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { EducationRow, Option, ProfileForm } from '@/types';

/**
 * Personal, contact, passport and education details (REQ §10, §10.1).
 * Used by staff (Student Profile Update) and by students in the portal.
 */
const props = defineProps<{
    profile: ProfileForm;
    options: {
        genders: Option[];
        maritalStatuses: Option[];
        educationLevels: Option[];
    };
    submitUrl: string;
    submitLabel?: string;
}>();

defineSlots<{ actions?: () => unknown }>();

const blankEducation = (): EducationRow => ({
    id: null,
    level: 'hsc',
    institution: '',
    board_or_university: '',
    country: '',
    major: '',
    result: '',
    passing_year: '',
});

const form = useForm({
    ...props.profile,
    educations: props.profile.educations.length
        ? props.profile.educations.map((e) => ({ ...e }))
        : [],
});

const sections: {
    title: string;
    description?: string;
    fields: {
        key: string;
        label: string;
        type?: string;
        options?: keyof typeof props.options;
        hint?: string;
        full?: boolean;
    }[];
}[] = [
    {
        title: 'Personal information',
        fields: [
            { key: 'date_of_birth', label: 'Date of birth', type: 'date' },
            { key: 'gender', label: 'Gender', options: 'genders' },
            {
                key: 'marital_status',
                label: 'Marital status',
                options: 'maritalStatuses',
            },
            { key: 'nationality', label: 'Nationality' },
            { key: 'place_of_birth', label: 'Place of birth' },
        ],
    },
    {
        title: 'Contact information',
        fields: [
            {
                key: 'phone',
                label: 'Phone / WhatsApp',
                type: 'tel',
                hint: 'Include the country code.',
            },
            { key: 'alternate_phone', label: 'Alternate phone', type: 'tel' },
            { key: 'address_line', label: 'Address', full: true },
            { key: 'city', label: 'City' },
            { key: 'state', label: 'State / Division' },
            { key: 'postal_code', label: 'Postal code' },
            { key: 'country', label: 'Country' },
        ],
    },
    {
        title: 'Emergency contact',
        fields: [
            { key: 'emergency_contact_name', label: 'Name' },
            { key: 'emergency_contact_relation', label: 'Relationship' },
            { key: 'emergency_contact_phone', label: 'Phone', type: 'tel' },
        ],
    },
    {
        title: 'Passport information',
        description: 'Must match the passport exactly. Stored encrypted.',
        fields: [
            { key: 'passport_number', label: 'Passport number' },
            { key: 'passport_issuing_country', label: 'Issuing country' },
            { key: 'passport_issue_date', label: 'Issue date', type: 'date' },
            { key: 'passport_expiry_date', label: 'Expiry date', type: 'date' },
            { key: 'passport_issue_place', label: 'Place of issue' },
        ],
    },
];

function error(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}

function addEducation(): void {
    form.educations.push(blankEducation());
}

function removeEducation(index: number): void {
    form.educations.splice(index, 1);
}

function submit(): void {
    form.transform((data) => {
        const out: Record<string, unknown> = {};

        for (const [k, v] of Object.entries(data) as [string, unknown][]) {
            out[k] = v === '' ? null : v;
        }

        out.educations = data.educations.map((e) => ({
            ...Object.fromEntries(
                Object.entries(e).map(([k, v]) => [k, v === '' ? null : v]),
            ),
            passing_year: e.passing_year ? Number(e.passing_year) : null,
        }));

        return out;
    }).put(props.submitUrl, { preserveScroll: true });
}
</script>

<template>
    <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
        <PanelCard
            v-for="section in sections"
            :key="section.title"
            :title="section.title"
            :description="section.description"
        >
            <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="f in section.fields"
                    :key="f.key"
                    :class="{ 'sm:col-span-2 lg:col-span-3': f.full }"
                >
                    <FormField
                        v-slot="{ id, invalid, describedBy }"
                        :label="f.label"
                        :hint="f.hint"
                        :error="error(f.key)"
                    >
                        <SelectInput
                            v-if="f.options"
                            :id="id"
                            v-model="
                                (form as unknown as Record<string, string>)[
                                    f.key
                                ]
                            "
                            :options="options[f.options]"
                            placeholder="Select…"
                            :invalid="invalid"
                            :described-by="describedBy"
                        />
                        <Input
                            v-else
                            :id="id"
                            v-model="
                                (form as unknown as Record<string, string>)[
                                    f.key
                                ]
                            "
                            :type="f.type ?? 'text'"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </FormField>
                </div>
            </div>
        </PanelCard>

        <PanelCard
            title="Education history"
            description="Most recent first. Upload certificates and transcripts under Documents."
        >
            <template #actions>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addEducation"
                    ><AppIcon name="add" :size="16" /> Add qualification</Button
                >
            </template>
            <p
                v-if="!form.educations.length"
                class="rounded-lg border border-dashed p-5 text-center text-[13px] text-muted-foreground"
            >
                No qualifications added yet.
            </p>
            <div
                v-for="(edu, i) in form.educations"
                :key="edu.id ?? `new-${i}`"
                class="mb-3 rounded-xl border p-4 last:mb-0"
            >
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-[13px] font-bold">
                        Qualification {{ i + 1 }}
                    </p>
                    <button
                        type="button"
                        class="tiny-action"
                        @click="removeEducation(i)"
                    >
                        Remove
                    </button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Level"
                        required
                        :error="error(`educations.${i}.level`)"
                    >
                        <SelectInput
                            :id="id"
                            v-model="edu.level"
                            :options="options.educationLevels"
                            :invalid="invalid"
                        />
                    </FormField>
                    <div class="lg:col-span-2">
                        <FormField
                            v-slot="{ id, invalid }"
                            label="Institution"
                            required
                            :error="error(`educations.${i}.institution`)"
                        >
                            <Input
                                :id="id"
                                v-model="edu.institution"
                                :aria-invalid="invalid"
                            />
                        </FormField>
                    </div>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Board / University"
                        :error="error(`educations.${i}.board_or_university`)"
                    >
                        <Input
                            :id="id"
                            v-model="edu.board_or_university as string"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Subject / Group"
                        :error="error(`educations.${i}.major`)"
                    >
                        <Input
                            :id="id"
                            v-model="edu.major as string"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Result (GPA / CGPA / %)"
                        :error="error(`educations.${i}.result`)"
                    >
                        <Input
                            :id="id"
                            v-model="edu.result as string"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Passing year"
                        :error="error(`educations.${i}.passing_year`)"
                    >
                        <Input
                            :id="id"
                            v-model="edu.passing_year as string"
                            type="number"
                            min="1950"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Country"
                        :error="error(`educations.${i}.country`)"
                    >
                        <Input
                            :id="id"
                            v-model="edu.country as string"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                </div>
            </div>
        </PanelCard>

        <div
            class="sticky bottom-0 -mx-[15px] flex justify-end gap-2 border-t bg-background/95 px-[15px] py-3 backdrop-blur md:-mx-8 md:px-8"
        >
            <slot name="actions" />
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ submitLabel ?? 'Save profile' }}
            </Button>
        </div>
    </form>
</template>
