<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
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
import { index, store } from '@/routes/admin/visa-types';
import type { Option } from '@/types';

type VisaType = {
    id: number;
    name: string;
    kind: string;
    kind_label: string;
    description: string | null;
    is_active: boolean;
    payment_type_id: string;
    fee_decimal: string;
    fee_label: string | null;
    applications_count: number;
    document_category_ids: string[];
    documents: string[];
    requirements: {
        id: number;
        title: string;
        description: string | null;
        country: string | null;
        delete_url: string;
    }[];
    update_url: string;
    requirement_url: string;
};

defineProps<{
    types: VisaType[];
    kinds: Option[];
    paymentTypes: Option[];
    documentCategories: Option[];
    countries: Option[];
    currency: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Visa types', href: index() }] },
});

const editing = ref<VisaType | null>(null);
const formOpen = ref(false);
const form = useForm({
    name: '',
    kind: 'study',
    description: '',
    is_active: true,
    payment_type_id: '',
    fee: '',
    document_category_ids: [] as string[],
});

function open(type: VisaType | null): void {
    editing.value = type;
    form.clearErrors();
    form.name = type?.name ?? '';
    form.kind = type?.kind ?? 'study';
    form.description = type?.description ?? '';
    form.is_active = type?.is_active ?? true;
    form.payment_type_id = type?.payment_type_id ?? '';
    form.fee = type?.fee_decimal ?? '';
    form.document_category_ids = [...(type?.document_category_ids ?? [])];
    formOpen.value = true;
}

function toggleDocument(id: string): void {
    const at = form.document_category_ids.indexOf(id);

    if (at === -1) {
        form.document_category_ids.push(id);
    } else {
        form.document_category_ids.splice(at, 1);
    }
}

function save(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (formOpen.value = false),
    };
    const payload = form.transform((d) => ({
        ...d,
        description: d.description || null,
        payment_type_id: d.payment_type_id || null,
        fee: d.fee || null,
    }));

    if (editing.value) {
        payload.put(editing.value.update_url, options);
    } else {
        payload.post(store.url(), options);
    }
}

const requirementFor = ref<VisaType | null>(null);
const requirement = useForm({ title: '', description: '', country_id: '' });

function saveRequirement(): void {
    if (!requirementFor.value) {
        return;
    }

    requirement
        .transform((d) => ({
            title: d.title,
            description: d.description || null,
            country_id: d.country_id || null,
        }))
        .post(requirementFor.value.requirement_url, {
            preserveScroll: true,
            onSuccess: () => {
                requirement.reset();
                requirementFor.value = null;
            },
        });
}

function removeRequirement(url: string): void {
    router.delete(url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Visa types" />

    <PageHeader
        eyebrow="Visa processing"
        title="Visa types"
        description="Every kind of application the office handles. Each has its own requirements, document checklist and fee, and can have its own workflow."
    >
        <template #actions>
            <Button @click="open(null)"
                ><AppIcon name="add" :size="18" /> New visa type</Button
            >
        </template>
    </PageHeader>

    <EmptyState
        v-if="!types.length"
        icon="flight_takeoff"
        title="No visa types yet"
    />
    <div v-else class="grid gap-4 lg:grid-cols-2">
        <PanelCard v-for="t in types" :key="t.id" :title="t.name">
            <template #actions>
                <StatusBadge
                    :label="t.is_active ? 'In use' : 'Switched off'"
                    :tone="t.is_active ? 'success' : 'neutral'"
                />
                <button type="button" class="tiny-action" @click="open(t)">
                    Edit
                </button>
            </template>
            <p class="text-xs text-muted-foreground">
                {{ t.kind_label }} · {{ t.applications_count }} application{{
                    t.applications_count === 1 ? '' : 's'
                }}
                · {{ t.fee_label ?? 'No fee set' }}
            </p>
            <p
                v-if="t.description"
                class="mt-2 text-[13px] text-secondary-foreground"
            >
                {{ t.description }}
            </p>

            <h3 class="mt-4 mb-1.5 text-xs font-bold text-secondary-foreground">
                Required documents
            </h3>
            <p
                v-if="!t.documents.length"
                class="text-[13px] text-muted-foreground"
            >
                None chosen. Edit the visa type to build its checklist.
            </p>
            <ul v-else class="flex flex-wrap gap-1.5">
                <li
                    v-for="d in t.documents"
                    :key="d"
                    class="rounded-full bg-muted px-2.5 py-1 text-xs"
                >
                    {{ d }}
                </li>
            </ul>

            <div class="mt-4 mb-1.5 flex items-center justify-between gap-3">
                <h3 class="text-xs font-bold text-secondary-foreground">
                    Requirements
                </h3>
                <button
                    type="button"
                    class="tiny-action"
                    @click="requirementFor = t"
                >
                    <AppIcon name="add" :size="14" /> Add
                </button>
            </div>
            <p
                v-if="!t.requirements.length"
                class="text-[13px] text-muted-foreground"
            >
                No requirements listed yet.
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="r in t.requirements"
                    :key="r.id"
                    class="flex items-start justify-between gap-3 py-2 text-[13px] first:pt-0 last:pb-0"
                >
                    <div class="min-w-0">
                        <p class="font-medium">
                            {{ r.title }}
                            <span
                                v-if="r.country"
                                class="text-xs font-normal text-muted-foreground"
                                >· {{ r.country }} only</span
                            >
                        </p>
                        <p
                            v-if="r.description"
                            class="text-xs text-secondary-foreground"
                        >
                            {{ r.description }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="tiny-action"
                        :aria-label="`Remove requirement: ${r.title}`"
                        @click="removeRequirement(r.delete_url)"
                    >
                        Remove
                    </button>
                </li>
            </ul>
        </PanelCard>
    </div>

    <Dialog v-model:open="formOpen">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto rounded-[14px] sm:max-w-[560px]"
        >
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">
                    {{ editing ? 'Edit visa type' : 'New visa type' }}
                </DialogTitle>
                <DialogDescription class="text-xs">
                    The family decides which menu lists it (Working Visa, Work &
                    Examination) and how a few fields are labelled.
                </DialogDescription>
            </DialogHeader>
            <form
                class="flex flex-col gap-3.5"
                novalidate
                @submit.prevent="save"
            >
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Name"
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
                        label="Family"
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
                </div>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Description"
                    :error="form.errors.description"
                >
                    <TextArea
                        :id="id"
                        v-model="form.description"
                        :rows="2"
                        :invalid="invalid"
                    />
                </FormField>
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        :label="`Fee (${currency})`"
                        hint="Leave empty for no standard fee."
                        :error="form.errors.fee"
                    >
                        <Input
                            :id="id"
                            v-model="form.fee"
                            inputmode="decimal"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Invoiced as"
                        :error="form.errors.payment_type_id"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.payment_type_id"
                            :options="paymentTypes"
                            placeholder="Choose a fee type"
                            :invalid="invalid"
                        />
                    </FormField>
                </div>
                <fieldset>
                    <legend
                        class="mb-1.5 text-xs font-bold text-secondary-foreground"
                    >
                        Required documents
                    </legend>
                    <div class="grid gap-1.5 sm:grid-cols-2">
                        <label
                            v-for="c in documentCategories"
                            :key="c.value"
                            class="flex items-center gap-2 text-[13px]"
                        >
                            <input
                                type="checkbox"
                                class="size-4 accent-primary"
                                :checked="
                                    form.document_category_ids.includes(c.value)
                                "
                                @change="toggleDocument(c.value)"
                            />
                            {{ c.label }}
                        </label>
                    </div>
                </fieldset>
                <label class="flex items-center gap-2 text-[13px]">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4 accent-primary"
                    />
                    In use (can be chosen for new applications)
                </label>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="formOpen = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        ><Spinner v-if="form.processing" /> Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="requirementFor !== null"
        @update:open="(v) => !v && (requirementFor = null)"
    >
        <DialogContent class="rounded-[14px] sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">
                    Add a requirement
                </DialogTitle>
                <DialogDescription class="text-xs">
                    {{ requirementFor?.name }} · shown to staff and to the
                    student on their application.
                </DialogDescription>
            </DialogHeader>
            <form
                class="flex flex-col gap-3.5"
                novalidate
                @submit.prevent="saveRequirement"
            >
                <FormField
                    v-slot="{ id, invalid }"
                    label="Requirement"
                    required
                    :error="requirement.errors.title"
                >
                    <Input
                        :id="id"
                        v-model="requirement.title"
                        placeholder="e.g. IELTS 6.0 or above"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Details"
                    :error="requirement.errors.description"
                >
                    <TextArea
                        :id="id"
                        v-model="requirement.description"
                        :rows="2"
                        :invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Applies to"
                    :error="requirement.errors.country_id"
                >
                    <SelectInput
                        :id="id"
                        v-model="requirement.country_id"
                        :options="countries"
                        placeholder="Every country"
                        :invalid="invalid"
                    />
                </FormField>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="requirementFor = null"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="requirement.processing"
                        ><Spinner v-if="requirement.processing" /> Add</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
