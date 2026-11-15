<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/admin/tickets';
import type { Option } from '@/types';

const props = defineProps<{
    options: {
        priorities: Option[];
        categories: Option[];
        assignees: Option[];
    };
    requester: { id: number; name: string; email: string } | null;
    canAssign: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tickets', href: index() }] },
});

const form = useForm({
    subject: '',
    description: '',
    category_id: '',
    priority: 'normal',
    requester_name: '',
    requester_email: '',
    assigned_to: '',
});

function submit(): void {
    form.transform((d) => ({
        ...d,
        category_id: d.category_id || null,
        assigned_to: d.assigned_to || null,
        requester_id: props.requester?.id ?? null,
        requester_name: props.requester ? null : d.requester_name,
        requester_email: props.requester ? null : d.requester_email,
    })).submit(store(), { preserveScroll: true });
}
</script>

<template>
    <Head title="New ticket" />

    <PageHeader
        eyebrow="Ticket & contact"
        title="New ticket"
        description="Log a question or issue raised by phone, email or in person."
    />

    <form
        class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(300px,1fr)]"
        novalidate
        @submit.prevent="submit"
    >
        <PanelCard title="Request">
            <div class="flex flex-col gap-3.5">
                <FormField
                    v-slot="{ id, invalid }"
                    label="Subject"
                    required
                    :error="form.errors.subject"
                >
                    <Input
                        :id="id"
                        v-model="form.subject"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Description"
                    required
                    :error="form.errors.description"
                >
                    <TextArea
                        :id="id"
                        v-model="form.description"
                        :rows="6"
                        :invalid="invalid"
                    />
                </FormField>
            </div>
        </PanelCard>

        <div class="flex flex-col gap-4">
            <PanelCard
                title="Requester"
                description="Replies are emailed here."
            >
                <p v-if="requester" class="text-[13px]">
                    <b>{{ requester.name }}</b
                    ><br /><span class="text-muted-foreground">{{
                        requester.email
                    }}</span>
                </p>
                <div v-else class="flex flex-col gap-3.5">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Name"
                        required
                        :error="form.errors.requester_name"
                    >
                        <Input
                            :id="id"
                            v-model="form.requester_name"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Email"
                        required
                        :error="form.errors.requester_email"
                    >
                        <Input
                            :id="id"
                            v-model="form.requester_email"
                            type="email"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                </div>
            </PanelCard>
            <PanelCard title="Details">
                <div class="flex flex-col gap-3.5">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Priority"
                        required
                        :error="form.errors.priority"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.priority"
                            :options="options.priorities"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Category"
                        :error="form.errors.category_id"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.category_id"
                            :options="options.categories"
                            placeholder="Uncategorised"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-if="canAssign"
                        v-slot="{ id, invalid }"
                        label="Assign to"
                        :error="form.errors.assigned_to"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.assigned_to"
                            :options="options.assignees"
                            placeholder="Unassigned"
                            :invalid="invalid"
                        />
                    </FormField>
                </div>
            </PanelCard>
        </div>

        <div class="flex justify-end gap-2 lg:col-span-2">
            <Button variant="outline" as-child
                ><Link :href="index()">Cancel</Link></Button
            >
            <Button type="submit" :disabled="form.processing"
                ><Spinner v-if="form.processing" /> Create ticket</Button
            >
        </div>
    </form>
</template>
