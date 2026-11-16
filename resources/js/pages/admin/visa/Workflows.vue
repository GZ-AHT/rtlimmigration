<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
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
import { index } from '@/routes/admin/workflows';
import type { Option } from '@/types';

type Stage = {
    id: number;
    name: string;
    kind: string;
    kind_label: string;
    description: string | null;
    notify_student: boolean;
    update_url: string;
    delete_url: string;
};

type Workflow = {
    id: number;
    name: string;
    is_default: boolean;
    is_active: boolean;
    visa_type_id: string;
    country_id: string;
    applies_to: string;
    applications_count: number;
    stages: Stage[];
    update_url: string;
    delete_url: string;
    stage_url: string;
    reorder_url: string;
};

const props = defineProps<{
    workflows: Workflow[];
    kinds: Option[];
    types: Option[];
    countries: Option[];
    urls: { store: string };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Workflows & stages', href: index() }] },
});

const onError = (e: Record<string, string>) =>
    toast.error(Object.values(e)[0] ?? 'Could not save.');

// --- workflow create / edit
const editingWorkflow = ref<Workflow | null>(null);
const workflowOpen = ref(false);
const workflowForm = useForm({
    name: '',
    visa_type_id: '',
    country_id: '',
    is_active: true,
    copy_from: '',
});

function openWorkflow(w: Workflow | null): void {
    editingWorkflow.value = w;
    workflowForm.clearErrors();
    workflowForm.name = w?.name ?? '';
    workflowForm.visa_type_id = w?.visa_type_id ?? '';
    workflowForm.country_id = w?.country_id ?? '';
    workflowForm.is_active = w?.is_active ?? true;
    workflowForm.copy_from = '';
    workflowOpen.value = true;
}

function saveWorkflow(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (workflowOpen.value = false),
    };
    const payload = workflowForm.transform((d) => ({
        name: d.name,
        visa_type_id: d.visa_type_id || null,
        country_id: d.country_id || null,
        is_active: d.is_active,
        copy_from: d.copy_from || null,
    }));

    if (editingWorkflow.value) {
        payload.put(editingWorkflow.value.update_url, options);
    } else {
        payload.post(props.urls.store, options);
    }
}

// --- stage create / edit
const stageContext = ref<{ workflow: Workflow; stage: Stage | null } | null>(
    null,
);
const stageForm = useForm({
    name: '',
    kind: 'standard',
    description: '',
    notify_student: true,
});

function openStage(workflow: Workflow, stage: Stage | null): void {
    stageContext.value = { workflow, stage };
    stageForm.clearErrors();
    stageForm.name = stage?.name ?? '';
    stageForm.kind = stage?.kind ?? 'standard';
    stageForm.description = stage?.description ?? '';
    stageForm.notify_student = stage?.notify_student ?? true;
}

function saveStage(): void {
    if (!stageContext.value) {
        return;
    }

    const { workflow, stage } = stageContext.value;
    const options = {
        preserveScroll: true,
        onSuccess: () => (stageContext.value = null),
    };
    const payload = stageForm.transform((d) => ({
        ...d,
        description: d.description || null,
    }));

    if (stage) {
        payload.put(stage.update_url, options);
    } else {
        payload.post(workflow.stage_url, options);
    }
}

function move(workflow: Workflow, from: number, by: -1 | 1): void {
    const order = workflow.stages.map((s) => s.id);
    const to = from + by;

    if (to < 0 || to >= order.length) {
        return;
    }

    [order[from], order[to]] = [order[to], order[from]];
    router.put(
        workflow.reorder_url,
        { order },
        { preserveScroll: true, onError },
    );
}

// --- deletes
const removing = ref<{ title: string; text: string; url: string } | null>(null);
const busy = ref(false);

function remove(): void {
    if (!removing.value) {
        return;
    }

    router.delete(removing.value.url, {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onError,
        onFinish: () => {
            busy.value = false;
            removing.value = null;
        },
    });
}
</script>

<template>
    <Head title="Workflows & stages" />

    <PageHeader
        eyebrow="Visa processing"
        title="Workflows & stages"
        description="The steps an application moves through. A workflow for a visa type and country wins over one for the visa type alone, which wins over the default."
    >
        <template #actions>
            <Button @click="openWorkflow(null)"
                ><AppIcon name="add" :size="18" /> New workflow</Button
            >
        </template>
    </PageHeader>

    <div class="flex flex-col gap-4">
        <PanelCard
            v-for="w in workflows"
            :key="w.id"
            :title="w.name"
            :description="`${w.applies_to} · ${w.applications_count} application${w.applications_count === 1 ? '' : 's'}`"
        >
            <template #actions>
                <StatusBadge v-if="w.is_default" label="Default" tone="info" />
                <StatusBadge
                    v-else
                    :label="w.is_active ? 'In use' : 'Switched off'"
                    :tone="w.is_active ? 'success' : 'neutral'"
                />
                <button
                    type="button"
                    class="tiny-action"
                    @click="openWorkflow(w)"
                >
                    Edit
                </button>
                <button
                    v-if="!w.is_default"
                    type="button"
                    class="tiny-action"
                    @click="
                        removing = {
                            title: `Delete “${w.name}”?`,
                            text: 'Only workflows no application uses can be deleted.',
                            url: w.delete_url,
                        }
                    "
                >
                    Delete
                </button>
            </template>

            <ol class="divide-y">
                <li
                    v-for="(s, i) in w.stages"
                    :key="s.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="grid size-6 shrink-0 place-items-center rounded-full bg-muted text-[11px] font-bold"
                            >{{ i + 1 }}</span
                        >
                        <div class="min-w-0 text-[13px]">
                            <p class="font-medium">{{ s.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ s.kind_label }} ·
                                {{
                                    s.notify_student
                                        ? 'student notified'
                                        : 'student not notified'
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-1.5">
                        <button
                            type="button"
                            class="tiny-action"
                            :disabled="i === 0"
                            :aria-label="`Move ${s.name} up`"
                            @click="move(w, i, -1)"
                        >
                            <AppIcon name="arrow_upward" :size="14" />
                        </button>
                        <button
                            type="button"
                            class="tiny-action"
                            :disabled="i === w.stages.length - 1"
                            :aria-label="`Move ${s.name} down`"
                            @click="move(w, i, 1)"
                        >
                            <AppIcon name="arrow_downward" :size="14" />
                        </button>
                        <button
                            type="button"
                            class="tiny-action"
                            @click="openStage(w, s)"
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            class="tiny-action"
                            :aria-label="`Remove ${s.name}`"
                            @click="
                                removing = {
                                    title: `Remove “${s.name}”?`,
                                    text: 'Past history keeps the stage name. No application may be at this stage.',
                                    url: s.delete_url,
                                }
                            "
                        >
                            Remove
                        </button>
                    </div>
                </li>
            </ol>
            <Button
                variant="outline"
                size="sm"
                class="mt-3"
                @click="openStage(w, null)"
                ><AppIcon name="add" :size="16" /> Add stage</Button
            >
        </PanelCard>
    </div>

    <Dialog v-model:open="workflowOpen">
        <DialogContent class="rounded-[14px] sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">
                    {{ editingWorkflow ? 'Edit workflow' : 'New workflow' }}
                </DialogTitle>
                <DialogDescription class="text-xs">
                    {{
                        editingWorkflow?.is_default
                            ? 'The default covers every application without a more specific workflow.'
                            : 'Choose a visa type, a country, or both.'
                    }}
                </DialogDescription>
            </DialogHeader>
            <form
                class="flex flex-col gap-3.5"
                novalidate
                @submit.prevent="saveWorkflow"
            >
                <FormField
                    v-slot="{ id, invalid }"
                    label="Name"
                    required
                    :error="workflowForm.errors.name"
                >
                    <Input
                        :id="id"
                        v-model="workflowForm.name"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <template v-if="!editingWorkflow?.is_default">
                    <div class="grid gap-3.5 sm:grid-cols-2">
                        <FormField
                            v-slot="{ id, invalid }"
                            label="Visa type"
                            :error="workflowForm.errors.visa_type_id"
                        >
                            <SelectInput
                                :id="id"
                                v-model="workflowForm.visa_type_id"
                                :options="types"
                                placeholder="Any visa type"
                                :invalid="invalid"
                            />
                        </FormField>
                        <FormField
                            v-slot="{ id, invalid }"
                            label="Country"
                            :error="workflowForm.errors.country_id"
                        >
                            <SelectInput
                                :id="id"
                                v-model="workflowForm.country_id"
                                :options="countries"
                                placeholder="Any country"
                                :invalid="invalid"
                            />
                        </FormField>
                    </div>
                    <FormField
                        v-if="!editingWorkflow"
                        v-slot="{ id }"
                        label="Start with the stages of"
                    >
                        <SelectInput
                            :id="id"
                            v-model="workflowForm.copy_from"
                            :options="
                                workflows.map((w) => ({
                                    value: String(w.id),
                                    label: w.name,
                                }))
                            "
                            placeholder="The default workflow"
                        />
                    </FormField>
                    <label class="flex items-center gap-2 text-[13px]">
                        <input
                            v-model="workflowForm.is_active"
                            type="checkbox"
                            class="size-4 accent-primary"
                        />
                        In use for new applications
                    </label>
                </template>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="workflowOpen = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="workflowForm.processing"
                        ><Spinner v-if="workflowForm.processing" /> Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="stageContext !== null"
        @update:open="(v) => !v && (stageContext = null)"
    >
        <DialogContent class="rounded-[14px] sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">
                    {{ stageContext?.stage ? 'Edit stage' : 'Add a stage' }}
                </DialogTitle>
                <DialogDescription class="text-xs">
                    {{ stageContext?.workflow.name }}
                </DialogDescription>
            </DialogHeader>
            <form
                class="flex flex-col gap-3.5"
                novalidate
                @submit.prevent="saveStage"
            >
                <FormField
                    v-slot="{ id, invalid }"
                    label="Stage name"
                    required
                    :error="stageForm.errors.name"
                >
                    <Input
                        :id="id"
                        v-model="stageForm.name"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="What this stage does"
                    required
                    hint="Decision asks for approved or rejected and a document. Office-visit and completed stages record their dates."
                    :error="stageForm.errors.kind"
                >
                    <SelectInput
                        :id="id"
                        v-model="stageForm.kind"
                        :options="kinds"
                        :invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Message for the student"
                    hint="Shown on their application while it is at this stage."
                    :error="stageForm.errors.description"
                >
                    <TextArea
                        :id="id"
                        v-model="stageForm.description"
                        :rows="2"
                        :invalid="invalid"
                    />
                </FormField>
                <label class="flex items-center gap-2 text-[13px]">
                    <input
                        v-model="stageForm.notify_student"
                        type="checkbox"
                        class="size-4 accent-primary"
                    />
                    Notify the student when an application reaches this stage
                </label>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="stageContext = null"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="stageForm.processing"
                        ><Spinner v-if="stageForm.processing" /> Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removing !== null"
        :title="removing?.title ?? ''"
        :description="removing?.text"
        confirm-label="Remove"
        destructive
        :loading="busy"
        @update:open="(v) => !v && (removing = null)"
        @confirm="remove"
    />
</template>
