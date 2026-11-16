<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ApplicationTimeline from '@/components/applications/ApplicationTimeline.vue';
import StageTrack from '@/components/applications/StageTrack.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import FileUpload from '@/components/panel/FileUpload.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import ProgressBar from '@/components/panel/ProgressBar.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatDateTime } from '@/lib/format';
import type {
    ApplicationHistoryEntry,
    ApplicationRow,
    VisaChecklistItem,
    PaymentRequestRow,
    StageStep,
    VisaRequirementItem,
} from '@/types';

const props = defineProps<{
    application: ApplicationRow & {
        notes: string | null;
        counselor: string | null;
        created_by: string | null;
        workflow: string;
        progress: number;
        scheduled_local: string | null;
        student_url: string | null;
        file_url: string | null;
    };
    stages: StageStep[];
    history: ApplicationHistoryEntry[];
    checklist: VisaChecklistItem[];
    requirements: VisaRequirementItem[];
    invoices: (PaymentRequestRow & { url: string | null })[];
    fee: { label: string; type: string } | null;
    can: {
        update: boolean;
        decide: boolean;
        withdraw: boolean;
        raiseFee: boolean;
    };
    upload: { max_mb: number; extensions: string[] };
    urls: {
        index: string;
        advance: string;
        update: string;
        withdraw: string;
        fee: string;
    };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Visa processing', href: props.urls.index }],
});

// The natural next step is offered first; any stage can be chosen.
const currentIndex = props.stages.findIndex((s) => s.state === 'current');
const next = props.stages[currentIndex + 1] ?? props.stages[currentIndex];

const update = useForm({
    stage_id: next ? String(next.id) : '',
    note: '',
    decision: '',
    rejection_reason: '',
    office_visit_at: '',
    result: '',
    files: [] as File[],
});

const target = computed(() =>
    props.stages.find((s) => String(s.id) === update.stage_id),
);
const isCurrent = computed(() => target.value?.state === 'current');
const needsDecision = computed(
    () => target.value?.kind === 'decision' && !isCurrent.value,
);
const stageOptions = computed(() =>
    props.stages.map((s, i) => ({
        value: String(s.id),
        label: `${i + 1}. ${s.name}${s.state === 'current' ? ' (current: post an update)' : ''}`,
    })),
);
const filesError = computed(
    () =>
        Object.entries(update.errors).find(
            ([k]) => k === 'files' || k.startsWith('files.'),
        )?.[1],
);

function submitUpdate(): void {
    update
        .transform((d) => ({
            stage_id: d.stage_id,
            note: d.note || null,
            ...(needsDecision.value
                ? {
                      decision: d.decision || null,
                      rejection_reason:
                          d.decision === 'rejected'
                              ? d.rejection_reason || null
                              : null,
                  }
                : {}),
            ...(target.value?.kind === 'visit_requested' && d.office_visit_at
                ? { office_visit_at: d.office_visit_at }
                : {}),
            ...(props.application.kind === 'exam' && d.result
                ? { result: d.result }
                : {}),
            files: d.files,
        }))
        .post(props.urls.advance, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: false,
        });
}

const editing = ref(false);
const details = useForm({
    title: props.application.title ?? '',
    organisation: props.application.organisation ?? '',
    reference_number: props.application.reference_number ?? '',
    scheduled_at: props.application.scheduled_local ?? '',
    result: props.application.result ?? '',
    notes: props.application.notes ?? '',
});

function saveDetails(): void {
    details
        .transform((d) => ({ ...d, scheduled_at: d.scheduled_at || null }))
        .put(props.urls.update, {
            preserveScroll: true,
            onSuccess: () => (editing.value = false),
        });
}

const dialog = ref<null | 'withdraw' | 'fee'>(null);
const reason = ref('');
const busy = ref(false);

function act(method: 'patch' | 'post', url: string, data = {}): void {
    router[method](url, data, {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onError: (e) => toast.error(Object.values(e)[0] ?? 'Could not save.'),
        onFinish: () => {
            busy.value = false;
            dialog.value = null;
        },
    });
}

const facts = computed(() =>
    [
        [props.application.labels.title, props.application.title],
        [props.application.labels.organisation, props.application.organisation],
        ['Reference number', props.application.reference_number],
        [
            props.application.labels.schedule,
            props.application.scheduled_at
                ? formatDateTime(props.application.scheduled_at)
                : null,
        ],
        ['Result', props.application.result],
        ['Country', props.application.country],
        ['Student file', props.application.file_number],
        ['Counselor', props.application.counselor],
        ['Workflow', props.application.workflow],
        ['Started', formatDate(props.application.created_at)],
    ].filter(([, v]) => v),
);
</script>

<template>
    <Head :title="`Application ${application.application_number}`" />

    <PageHeader
        :eyebrow="`${application.application_number} · ${application.visa_type}`"
        :title="application.student ?? 'Application'"
        :description="`${application.student_number}${application.country ? ` · ${application.country}` : ''} · ${application.stage ?? 'No stage'}`"
    >
        <template #actions>
            <StatusBadge
                :label="application.status_label"
                :tone="application.status_tone"
                class="self-center"
            />
            <Button v-if="application.student_url" variant="outline" as-child
                ><Link :href="application.student_url"
                    ><AppIcon name="school" :size="18" /> Student</Link
                ></Button
            >
            <Button
                v-if="can.withdraw"
                variant="outline"
                @click="dialog = 'withdraw'"
                ><AppIcon name="cancel" :size="18" /> Withdraw</Button
            >
        </template>
    </PageHeader>

    <div
        v-if="application.decision"
        class="mb-4 rounded-xl border p-4 text-[13px]"
        :class="
            application.decision === 'approved'
                ? 'border-success/30 bg-success-soft text-success'
                : 'border-danger/30 bg-danger-soft text-danger'
        "
    >
        <p class="flex items-center gap-2 font-bold">
            <AppIcon
                :name="
                    application.decision === 'approved' ? 'verified' : 'cancel'
                "
                :size="18"
            />
            {{
                application.decision === 'approved'
                    ? 'Visa approved'
                    : 'Visa rejected'
            }}
            · {{ formatDate(application.decided_at) }}
        </p>
        <p v-if="application.rejection_reason" class="mt-1 whitespace-pre-line">
            {{ application.rejection_reason }}
        </p>
        <p
            v-if="application.office_visit_requested_at"
            class="mt-1 text-foreground"
        >
            Office visit
            {{
                application.office_visit_completed_at
                    ? `completed ${formatDate(application.office_visit_completed_at)}`
                    : application.office_visit_at
                      ? `requested for ${formatDateTime(application.office_visit_at)}`
                      : 'requested'
            }}.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(320px,1fr)]">
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard
                v-if="can.update"
                title="Update status"
                description="Every update is recorded with your name, the time and any documents."
            >
                <form
                    class="flex flex-col gap-3.5"
                    novalidate
                    @submit.prevent="submitUpdate"
                >
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Stage"
                        required
                        :error="update.errors.stage_id"
                    >
                        <SelectInput
                            :id="id"
                            v-model="update.stage_id"
                            :options="stageOptions"
                            :invalid="invalid"
                        />
                    </FormField>

                    <p
                        v-if="needsDecision && !can.decide"
                        class="rounded-lg bg-warning-soft p-3 text-[13px] text-warning"
                    >
                        Recording a visa decision needs an extra permission you
                        do not have. Ask an administrator.
                    </p>
                    <template v-else-if="needsDecision">
                        <FormField
                            v-slot="{ id, invalid }"
                            label="Decision"
                            required
                            :error="update.errors.decision"
                        >
                            <SelectInput
                                :id="id"
                                v-model="update.decision"
                                :options="[
                                    { value: 'approved', label: 'Approved' },
                                    { value: 'rejected', label: 'Rejected' },
                                ]"
                                placeholder="Choose the outcome"
                                :invalid="invalid"
                            />
                        </FormField>
                        <FormField
                            v-if="update.decision === 'rejected'"
                            v-slot="{ id, invalid }"
                            label="Reason for rejection"
                            required
                            hint="As stated by the embassy. The student sees this."
                            :error="update.errors.rejection_reason"
                        >
                            <TextArea
                                :id="id"
                                v-model="update.rejection_reason"
                                :rows="3"
                                :invalid="invalid"
                            />
                        </FormField>
                    </template>

                    <FormField
                        v-if="target?.kind === 'visit_requested' && !isCurrent"
                        v-slot="{ id, invalid }"
                        label="Visit date and time"
                        hint="Leave empty if the student should arrange a time."
                        :error="update.errors.office_visit_at"
                    >
                        <Input
                            :id="id"
                            v-model="update.office_visit_at"
                            type="datetime-local"
                            :aria-invalid="invalid"
                        />
                    </FormField>

                    <FormField
                        v-if="application.kind === 'exam'"
                        v-slot="{ id, invalid }"
                        label="Result / score"
                        hint="Fill in once the result is known."
                        :error="update.errors.result"
                    >
                        <Input
                            :id="id"
                            v-model="update.result"
                            :aria-invalid="invalid"
                        />
                    </FormField>

                    <FormField
                        v-slot="{ id, invalid }"
                        label="Notes / remarks"
                        hint="The student sees these on their timeline."
                        :error="update.errors.note"
                    >
                        <TextArea
                            :id="id"
                            v-model="update.note"
                            :rows="3"
                            :invalid="invalid"
                        />
                    </FormField>

                    <FileUpload
                        v-model="update.files"
                        :label="
                            needsDecision
                                ? 'Decision document (required)'
                                : 'Supporting documents (optional)'
                        "
                        multiple
                        :max-files="5"
                        :accept="
                            upload.extensions.map((e) => `.${e}`).join(',')
                        "
                        :max-size-mb="upload.max_mb"
                        :error="filesError"
                        :hint="`${upload.extensions.join(', ').toUpperCase()} · up to ${upload.max_mb} MB each`"
                    />

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="
                                update.processing ||
                                (needsDecision && !can.decide)
                            "
                            ><Spinner v-if="update.processing" />
                            {{
                                isCurrent
                                    ? 'Post update'
                                    : `Move to "${target?.name ?? ''}"`
                            }}</Button
                        >
                    </div>
                </form>
            </PanelCard>

            <PanelCard
                title="Status history"
                description="Newest first. Previous and new stage, who, when, remarks and documents."
            >
                <ApplicationTimeline :history="history" staff />
            </PanelCard>

            <PanelCard
                v-if="invoices.length || can.raiseFee"
                title="Payments"
                description="Invoices on this application's student file. Payments are confirmed by Accounts."
            >
                <template v-if="can.raiseFee" #actions>
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="busy"
                        @click="dialog = 'fee'"
                        >Raise {{ fee?.label }} fee</Button
                    >
                </template>
                <EmptyState
                    v-if="!invoices.length"
                    icon="request_quote"
                    title="No invoices on this file yet"
                />
                <ul v-else class="divide-y">
                    <li
                        v-for="r in invoices"
                        :key="r.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0 text-[13px]">
                            <Link
                                v-if="r.url"
                                :href="r.url"
                                class="font-semibold hover:text-primary"
                                >{{ r.type }}</Link
                            >
                            <span v-else class="font-semibold">{{
                                r.type
                            }}</span>
                            <p class="text-xs text-muted-foreground">
                                {{ r.invoice_number }} ·
                                {{ r.amount_label }}
                            </p>
                        </div>
                        <StatusBadge
                            :label="r.status_label"
                            :tone="r.status_tone"
                        />
                    </li>
                </ul>
            </PanelCard>
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard title="Progress">
                <ProgressBar
                    :value="application.progress"
                    label="Through the workflow"
                    class="mb-4"
                />
                <StageTrack :stages="stages" />
            </PanelCard>

            <PanelCard title="Details">
                <template v-if="can.update" #actions>
                    <button
                        type="button"
                        class="tiny-action"
                        @click="editing = !editing"
                    >
                        {{ editing ? 'Cancel' : 'Edit' }}
                    </button>
                </template>
                <form
                    v-if="editing"
                    class="flex flex-col gap-3"
                    novalidate
                    @submit.prevent="saveDetails"
                >
                    <FormField
                        v-slot="{ id }"
                        :label="application.labels.title"
                        :error="details.errors.title"
                        ><Input :id="id" v-model="details.title"
                    /></FormField>
                    <FormField
                        v-slot="{ id }"
                        :label="application.labels.organisation"
                        :error="details.errors.organisation"
                        ><Input :id="id" v-model="details.organisation"
                    /></FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Reference number"
                        :error="details.errors.reference_number"
                        ><Input :id="id" v-model="details.reference_number"
                    /></FormField>
                    <FormField
                        v-slot="{ id }"
                        :label="application.labels.schedule"
                        :error="details.errors.scheduled_at"
                        ><Input
                            :id="id"
                            v-model="details.scheduled_at"
                            type="datetime-local"
                    /></FormField>
                    <FormField
                        v-if="application.kind === 'exam'"
                        v-slot="{ id }"
                        label="Result / score"
                        :error="details.errors.result"
                        ><Input :id="id" v-model="details.result"
                    /></FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Internal notes"
                        :error="details.errors.notes"
                        ><TextArea :id="id" v-model="details.notes" :rows="3"
                    /></FormField>
                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="details.processing"
                            ><Spinner v-if="details.processing" /> Save</Button
                        >
                    </div>
                </form>
                <template v-else>
                    <dl class="grid gap-3 text-[13px]">
                        <div
                            v-for="[label, value] in facts"
                            :key="label!"
                            class="flex justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">{{ label }}</dt>
                            <dd class="text-right font-medium">{{ value }}</dd>
                        </div>
                    </dl>
                    <p
                        v-if="application.notes"
                        class="mt-4 rounded-lg bg-muted p-3 text-[13px] whitespace-pre-line"
                    >
                        {{ application.notes }}
                    </p>
                </template>
            </PanelCard>

            <PanelCard
                v-if="checklist.length"
                title="Required documents"
                :description="`The checklist for ${application.visa_type}.`"
            >
                <ul class="flex flex-col gap-2.5">
                    <li
                        v-for="c in checklist"
                        :key="c.category"
                        class="flex items-center justify-between gap-3 text-[13px]"
                    >
                        <span>{{ c.category }}</span>
                        <StatusBadge :label="c.label" :tone="c.tone" />
                    </li>
                </ul>
                <Link
                    v-if="application.student_url"
                    :href="`${application.student_url}?tab=documents`"
                    class="mt-3 inline-block text-xs font-semibold text-primary hover:underline"
                    >Manage the student's documents</Link
                >
            </PanelCard>

            <PanelCard v-if="requirements.length" title="Visa requirements">
                <ul class="flex flex-col gap-3 text-[13px]">
                    <li v-for="r in requirements" :key="r.title">
                        <p class="font-semibold">{{ r.title }}</p>
                        <p
                            v-if="r.description"
                            class="text-xs text-secondary-foreground"
                        >
                            {{ r.description }}
                        </p>
                    </li>
                </ul>
            </PanelCard>
        </div>
    </div>

    <ConfirmDialog
        :open="dialog === 'withdraw'"
        title="Withdraw this application?"
        description="It stops here and can no longer be updated. The student is told. The history is kept."
        confirm-label="Withdraw application"
        destructive
        :loading="busy"
        @update:open="(v) => !v && (dialog = null)"
        @confirm="act('patch', urls.withdraw, { reason })"
    >
        <Input
            v-model="reason"
            placeholder="Reason (required)"
            aria-label="Reason for withdrawing"
        />
    </ConfirmDialog>
    <ConfirmDialog
        :open="dialog === 'fee'"
        title="Raise the visa fee invoice?"
        :description="`Creates a ${fee?.label} invoice (${fee?.type}) for ${application.student} and notifies them.`"
        confirm-label="Raise invoice"
        :loading="busy"
        @update:open="(v) => !v && (dialog = null)"
        @confirm="act('post', urls.fee)"
    />
</template>
