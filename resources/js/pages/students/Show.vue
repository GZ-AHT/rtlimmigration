<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import DocumentChecklist from '@/components/documents/DocumentChecklist.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import ProgressBar from '@/components/panel/ProgressBar.vue';
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
import { getInitials } from '@/composables/useInitials';
import { formatDate, formatDateTime, relativeTime } from '@/lib/format';
import type {
    Checklist,
    Completeness,
    CounselorOption,
    EmailMessageRow,
    FileRow,
    MoneyTotals,
    Option,
    PaymentRequestRow,
    TableColumn,
    UploadLimits,
} from '@/types';

type Assignment = {
    id: number;
    counselor: string | null;
    assigned_by: string | null;
    note: string | null;
    assigned_at: string;
    ended_at: string | null;
};
type StudentDetail = {
    id: number;
    student_number: string;
    name: string;
    email: string;
    phone: string | null;
    counselor: string | null;
    counselor_id: number | null;
    source: string;
    account_status: 'active' | 'inactive' | 'deleted';
    email_verified: boolean;
    last_login_at: string | null;
    created_at: string | null;
    creator: string | null;
    lead: { id: number; number: string; visible: boolean } | null;
    assignments: Assignment[];
    can: { assign_counselor: boolean; view_user: boolean };
};
type Row = { label: string; value: string | null };
type ProfileSummary = {
    personal: Row[];
    contact: Row[];
    passport: Row[];
    passport_warning: 'expired' | 'expiring' | null;
    educations: {
        id: number;
        level: string;
        institution: string;
        board_or_university: string | null;
        major: string | null;
        result: string | null;
        passing_year: number | null;
        country: string | null;
    }[];
};
type Note = {
    id: number;
    body: string;
    author: string | null;
    created_at: string | null;
};

const props = defineProps<{
    tab: string;
    student: StudentDetail;
    counselors: CounselorOption[];
    completeness: Completeness;
    profile: ProfileSummary;
    files: FileRow[];
    checklist: Checklist;
    notes: Note[];
    emails: { items: EmailMessageRow[]; compose_url: string | null } | null;
    payments: {
        totals: MoneyTotals;
        requests: PaymentRequestRow[];
        create_url: string | null;
    } | null;
    fileOptions: { services: Option[]; countries: Option[] };
    documentOptions: {
        categories: Option[];
        files: Option[];
        limits: UploadLimits;
    };
    can: {
        update_profile: boolean;
        open_file: boolean;
        upload: boolean;
        add_note: boolean;
    };
    urls: Record<string, string | null>;
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Students', href: props.urls.index ?? '' }],
});

const tab = ref(props.tab);
watch(tab, (value) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', value);
    window.history.replaceState(window.history.state, '', url);
});

const tabs = computed(() => [
    { key: 'overview', label: 'Overview' },
    { key: 'profile', label: 'Profile' },
    { key: 'files', label: 'Files', count: props.files.length },
    {
        key: 'documents',
        label: 'Documents',
        count:
            props.checklist.items.filter(
                (i) => i.document?.status === 'pending',
            ).length || undefined,
    },
    ...(props.payments
        ? [
              {
                  key: 'payments',
                  label: 'Payments',
                  count: props.payments.requests.length || undefined,
              },
          ]
        : []),
    ...(props.emails
        ? [
              {
                  key: 'emails',
                  label: 'Emails',
                  count: props.emails.items.length || undefined,
              },
          ]
        : []),
    { key: 'notes', label: 'Notes', count: props.notes.length || undefined },
]);

const emailColumns: TableColumn<EmailMessageRow>[] = [
    { key: 'subject', label: 'Email' },
    { key: 'status', label: 'Status' },
    { key: 'open_count', label: 'Opened', align: 'right', hideBelow: 'md' },
    {
        key: 'created_at',
        label: 'Sent',
        hideBelow: 'sm',
        format: (r) => relativeTime(r.created_at),
    },
];

const paymentColumns: TableColumn<PaymentRequestRow>[] = [
    { key: 'invoice_number', label: 'Invoice' },
    { key: 'amount_label', label: 'Amount', align: 'right' },
    {
        key: 'outstanding_label',
        label: 'Outstanding',
        align: 'right',
        hideBelow: 'sm',
    },
    { key: 'status', label: 'Status' },
    {
        key: 'due_date',
        label: 'Due',
        hideBelow: 'md',
        format: (r) => (r.due_date ? formatDate(r.due_date) : '—'),
    },
];

/* Counselor assignment */
const counselorId = ref(
    props.student.counselor_id ? String(props.student.counselor_id) : '',
);
const assignNote = ref('');
const busy = ref(false);
const counselorOptions = computed<Option[]>(() =>
    props.counselors.map((c) => ({
        value: c.value,
        label: `${c.label}${c.availability !== 'available' ? ` · ${c.availability_label}` : ''}`,
    })),
);

function assign(): void {
    if (!props.urls.assign) {
        return;
    }

    router.patch(
        props.urls.assign,
        {
            counselor_id: counselorId.value || null,
            note: assignNote.value || null,
        },
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Could not assign.'),
            onSuccess: () => (assignNote.value = ''),
            onFinish: () => (busy.value = false),
        },
    );
}

/* Open file */
const opening = ref(false);
const fileForm = useForm({
    service_id: '',
    country_id: '',
    intake: '',
    notes: '',
});
function openFile(): void {
    fileForm
        .transform((d) => ({
            service_id: d.service_id || null,
            country_id: d.country_id || null,
            intake: d.intake || null,
            notes: d.notes || null,
        }))
        .post(props.urls.openFile!, {
            preserveScroll: true,
            onSuccess: () => (opening.value = false),
        });
}

/* Notes */
const noteForm = useForm({ body: '' });
function addNote(): void {
    noteForm.post(props.urls.notes!, {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}

const fileColumns: TableColumn<FileRow>[] = [
    { key: 'file_number', label: 'File' },
    { key: 'service', label: 'Service / destination', hideBelow: 'sm' },
    { key: 'status', label: 'Status' },
    {
        key: 'opened_at',
        label: 'Opened',
        hideBelow: 'md',
        format: (r) => formatDate(r.opened_at),
    },
];
</script>

<template>
    <Head :title="`${student.name} · ${student.student_number}`" />

    <PageHeader
        :eyebrow="student.student_number"
        :title="student.name"
        :description="student.email"
    >
        <template #actions>
            <Button
                v-if="can.update_profile && urls.profile"
                variant="outline"
                as-child
            >
                <Link :href="urls.profile"
                    ><AppIcon name="edit" :size="18" /> Edit profile</Link
                >
            </Button>
            <Button v-if="can.open_file" @click="opening = true"
                ><AppIcon name="create_new_folder" :size="18" /> Open
                file</Button
            >
            <Button v-if="urls.user" variant="outline" as-child>
                <Link :href="urls.user" aria-label="Account settings"
                    ><AppIcon name="manage_accounts" :size="18"
                /></Link>
            </Button>
        </template>
    </PageHeader>

    <PanelTabs v-model="tab" label="Student sections" :tabs="tabs" />

    <!-- Overview -->
    <div
        v-if="tab === 'overview'"
        class="grid gap-4 lg:grid-cols-[minmax(300px,1fr)_minmax(0,1.8fr)]"
    >
        <div class="flex flex-col gap-4">
            <PanelCard>
                <div class="flex items-center gap-3.5">
                    <span
                        class="grid size-14 shrink-0 place-items-center rounded-full bg-accent font-display text-lg font-extrabold text-accent-foreground"
                        >{{ getInitials(student.name) }}</span
                    >
                    <div class="min-w-0">
                        <p class="truncate font-display text-lg font-bold">
                            {{ student.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ student.student_number }}
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-1.5">
                    <StatusBadge
                        :label="
                            student.account_status === 'active'
                                ? 'Active'
                                : student.account_status === 'inactive'
                                  ? 'Inactive'
                                  : 'Deleted'
                        "
                        :tone="
                            student.account_status === 'active'
                                ? 'success'
                                : student.account_status === 'inactive'
                                  ? 'neutral'
                                  : 'danger'
                        "
                    />
                    <StatusBadge
                        :label="
                            student.email_verified
                                ? 'Account set up'
                                : 'Invitation pending'
                        "
                        :tone="student.email_verified ? 'success' : 'warning'"
                    />
                </div>
                <dl class="mt-5 grid gap-3 text-[13px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Email</dt>
                        <dd class="truncate">
                            <a
                                :href="`mailto:${student.email}`"
                                class="font-medium hover:text-primary"
                                >{{ student.email }}</a
                            >
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Phone</dt>
                        <dd class="font-medium">{{ student.phone ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Source</dt>
                        <dd class="font-medium">{{ student.source }}</dd>
                    </div>
                    <div v-if="student.lead" class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">From lead</dt>
                        <dd>
                            <Link
                                v-if="urls.lead"
                                :href="urls.lead"
                                class="font-medium hover:text-primary"
                                >{{ student.lead.number }}</Link
                            ><span v-else class="font-medium">{{
                                student.lead.number
                            }}</span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Joined</dt>
                        <dd class="font-medium">
                            {{ formatDate(student.created_at) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Last sign-in</dt>
                        <dd class="font-medium">
                            {{
                                student.last_login_at
                                    ? relativeTime(student.last_login_at)
                                    : 'Never'
                            }}
                        </dd>
                    </div>
                </dl>
            </PanelCard>

            <PanelCard title="Counselor">
                <p class="flex items-center gap-2 text-[13px]">
                    <AppIcon
                        name="support_agent"
                        :size="18"
                        class="text-muted-foreground"
                    />
                    <b v-if="student.counselor">{{ student.counselor }}</b>
                    <span v-else class="text-warning">Not assigned yet</span>
                </p>
                <form
                    v-if="student.can.assign_counselor && urls.assign"
                    class="mt-3 flex flex-col gap-2"
                    @submit.prevent="assign"
                >
                    <SelectInput
                        v-model="counselorId"
                        :options="counselorOptions"
                        placeholder="Unassigned"
                        label="Counselor"
                    />
                    <Input
                        v-model="assignNote"
                        placeholder="Reason for change (optional)"
                        aria-label="Reason"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="
                            busy ||
                            counselorId ===
                                (student.counselor_id
                                    ? String(student.counselor_id)
                                    : '')
                        "
                    >
                        {{ student.counselor_id ? 'Reassign' : 'Assign' }}
                    </Button>
                </form>
            </PanelCard>
        </div>

        <div class="flex min-w-0 flex-col gap-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <PanelCard title="Profile">
                    <ProgressBar
                        :value="completeness.percent"
                        label="Complete"
                    />
                    <p
                        v-if="completeness.missing.length"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        Missing: {{ completeness.missing.join(', ') }}
                    </p>
                    <p v-else class="mt-3 text-xs text-success">
                        All profile details are filled in.
                    </p>
                </PanelCard>
                <PanelCard title="Documents">
                    <ProgressBar
                        :value="
                            checklist.required
                                ? (checklist.verified / checklist.required) *
                                  100
                                : 100
                        "
                        label="Required verified"
                    />
                    <p class="mt-3 text-xs text-muted-foreground">
                        {{ checklist.verified }} of
                        {{ checklist.required }} required documents verified.
                    </p>
                </PanelCard>
            </div>
            <PanelCard
                title="Counselor history"
                description="Everyone who has been responsible for this student."
            >
                <EmptyState
                    v-if="!student.assignments.length"
                    icon="history"
                    title="No counselor assigned yet"
                />
                <ol v-else class="relative ml-1.5 border-l">
                    <li
                        v-for="a in student.assignments"
                        :key="a.id"
                        class="relative pb-4 pl-5 last:pb-0"
                    >
                        <span
                            class="absolute top-1 -left-[5px] size-2.5 rounded-full border-2 border-card"
                            :class="
                                a.ended_at
                                    ? 'bg-muted-foreground'
                                    : 'bg-primary'
                            "
                        />
                        <p class="text-[13px] font-semibold">
                            {{ a.counselor ?? 'Unassigned' }}
                            <span
                                v-if="!a.ended_at"
                                class="text-[11px] font-normal text-success"
                                >· current</span
                            >
                        </p>
                        <p
                            v-if="a.note"
                            class="text-xs text-secondary-foreground"
                        >
                            {{ a.note }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            {{ formatDateTime(a.assigned_at)
                            }}{{
                                a.ended_at
                                    ? ` – ${formatDateTime(a.ended_at)}`
                                    : ''
                            }}{{
                                a.assigned_by ? ` · by ${a.assigned_by}` : ''
                            }}
                        </p>
                    </li>
                </ol>
            </PanelCard>
        </div>
    </div>

    <!-- Profile -->
    <div v-else-if="tab === 'profile'" class="flex flex-col gap-4">
        <div
            v-if="profile.passport_warning"
            class="flex items-center gap-3 rounded-xl border p-4 text-[13px]"
            :class="
                profile.passport_warning === 'expired'
                    ? 'border-danger/30 bg-danger-soft text-danger'
                    : 'border-warning/30 bg-warning-soft text-warning'
            "
        >
            <AppIcon name="warning" :size="18" />
            {{
                profile.passport_warning === 'expired'
                    ? 'The passport has expired.'
                    : 'The passport expires within 6 months. Many destinations require at least 6 months of validity.'
            }}
        </div>
        <div class="grid gap-4 lg:grid-cols-3">
            <PanelCard
                v-for="(rows, title) in {
                    Personal: profile.personal,
                    Contact: profile.contact,
                    Passport: profile.passport,
                }"
                :key="title"
                :title="title"
            >
                <dl class="grid gap-3 text-[13px]">
                    <div
                        v-for="row in rows"
                        :key="row.label"
                        class="flex justify-between gap-3"
                    >
                        <dt class="text-muted-foreground">{{ row.label }}</dt>
                        <dd
                            class="text-right font-medium"
                            :class="{ 'text-muted-foreground': !row.value }"
                        >
                            {{ row.value ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </PanelCard>
        </div>
        <PanelCard title="Education">
            <EmptyState
                v-if="!profile.educations.length"
                icon="school"
                title="No education history yet"
            />
            <ul v-else class="divide-y">
                <li
                    v-for="e in profile.educations"
                    :key="e.id"
                    class="flex flex-wrap justify-between gap-2 py-3 text-[13px] first:pt-0 last:pb-0"
                >
                    <div>
                        <p class="font-semibold">
                            {{ e.level
                            }}<template v-if="e.major">
                                · {{ e.major }}</template
                            >
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ e.institution
                            }}<template v-if="e.board_or_university"
                                >, {{ e.board_or_university }}</template
                            ><template v-if="e.country">
                                · {{ e.country }}</template
                            >
                        </p>
                    </div>
                    <p class="text-right text-xs">
                        <b v-if="e.result">{{ e.result }}</b
                        ><span
                            v-if="e.passing_year"
                            class="block text-muted-foreground"
                            >{{ e.passing_year }}</span
                        >
                    </p>
                </li>
            </ul>
        </PanelCard>
    </div>

    <!-- Files -->
    <PanelCard v-else-if="tab === 'files'" flush>
        <DataTable
            :columns="fileColumns"
            :rows="files"
            caption="Student files"
            empty-icon="folder_shared"
            empty-title="No files opened yet"
            empty-description="Open a file when the student is ready to start an application."
        >
            <template #cell-file_number="{ row }">
                <Link
                    :href="row.url!"
                    class="font-semibold text-foreground hover:text-primary"
                    >{{ row.file_number }}</Link
                >
                <span
                    v-if="row.intake"
                    class="block text-[11px] text-muted-foreground"
                    >Intake {{ row.intake }}</span
                >
            </template>
            <template #cell-service="{ row }">{{
                [row.service, row.country].filter(Boolean).join(' · ') || '—'
            }}</template>
            <template #cell-status="{ row }"
                ><StatusBadge :label="row.status_label" :tone="row.status_tone"
            /></template>
            <template #actions="{ row }"
                ><Link :href="row.url!" class="tiny-action"
                    >Open</Link
                ></template
            >
            <template v-if="can.open_file" #empty>
                <EmptyState
                    icon="folder_shared"
                    title="No files opened yet"
                    description="Open a file when the student is ready to start an application."
                >
                    <Button size="sm" @click="opening = true">Open file</Button>
                </EmptyState>
            </template>
        </DataTable>
    </PanelCard>

    <!-- Documents -->
    <DocumentChecklist
        v-else-if="tab === 'documents'"
        :checklist="checklist"
        :can-upload="can.upload"
        :upload-url="urls.upload"
        :categories="documentOptions.categories"
        :files="documentOptions.files"
        :limits="documentOptions.limits"
    />

    <!-- Payments -->
    <div v-else-if="tab === 'payments' && payments" class="flex flex-col gap-4">
        <div class="grid grid-cols-3 gap-2.5 sm:gap-4">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Total fees</p>
                <p class="mt-1 font-display text-xl font-extrabold">
                    {{ payments.totals.total }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Paid</p>
                <p
                    class="mt-1 font-display text-xl font-extrabold text-success"
                >
                    {{ payments.totals.paid }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Outstanding</p>
                <p
                    class="mt-1 font-display text-xl font-extrabold"
                    :class="{ 'text-danger': payments.totals.due_minor > 0 }"
                >
                    {{ payments.totals.due }}
                </p>
            </div>
        </div>
        <PanelCard
            flush
            title="Invoices"
            description="Payment requests raised for this student."
        >
            <template v-if="payments.create_url" #actions>
                <Button size="sm" as-child>
                    <Link :href="payments.create_url">
                        <AppIcon name="add" :size="16" /> New payment request
                    </Link>
                </Button>
            </template>
            <DataTable
                class="mt-4"
                :columns="paymentColumns"
                :rows="payments.requests"
                caption="Invoices"
                empty-icon="receipt_long"
                empty-title="No payment requests yet"
            >
                <template #cell-invoice_number="{ row }">
                    <Link
                        :href="row.url!"
                        class="font-semibold text-foreground hover:text-primary"
                        >{{ row.invoice_number }}</Link
                    >
                    <span class="block text-[11px] text-muted-foreground">{{
                        row.type
                    }}</span>
                </template>
                <template #cell-status="{ row }">
                    <StatusBadge
                        :label="row.status_label"
                        :tone="row.status_tone"
                    />
                </template>
                <template #actions="{ row }">
                    <Link :href="row.url!" class="tiny-action">Open</Link>
                </template>
            </DataTable>
        </PanelCard>
    </div>

    <!-- Emails (REQ §3 Student Email History) -->
    <PanelCard
        v-else-if="tab === 'emails' && emails"
        title="Emails sent from the system"
        description="Compose and bulk emails to this student. Mail sent from webmail stays in that mailbox."
        flush
    >
        <template v-if="emails.compose_url" #actions>
            <Button size="sm" as-child
                ><Link :href="emails.compose_url"
                    ><AppIcon name="edit" :size="16" /> Email student</Link
                ></Button
            >
        </template>
        <DataTable
            :columns="emailColumns"
            :rows="emails.items"
            caption="Emails to this student"
            empty-title="No emails sent from the system yet"
            empty-icon="mail"
        >
            <template #cell-subject="{ row }">
                <span class="block truncate font-semibold text-foreground">{{
                    row.subject
                }}</span>
                <span class="block text-[11px] text-muted-foreground"
                    >From {{ row.from_email }}
                    <template v-if="row.sender">
                        · by {{ row.sender }}</template
                    ></span
                >
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
            </template>
            <template #cell-open_count="{ row }">
                {{ row.open_count > 0 ? row.open_count : '—' }}
            </template>
        </DataTable>
    </PanelCard>

    <!-- Notes -->
    <div
        v-else
        class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(280px,1fr)]"
    >
        <PanelCard
            title="Internal notes"
            description="Visible to staff only, never to the student."
        >
            <EmptyState
                v-if="!notes.length"
                icon="sticky_note_2"
                title="No notes yet"
            />
            <ul v-else class="divide-y">
                <li
                    v-for="n in notes"
                    :key="n.id"
                    class="py-3 first:pt-0 last:pb-0"
                >
                    <p class="text-[13px] whitespace-pre-line">{{ n.body }}</p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        {{ n.author ?? 'Staff' }} ·
                        {{ relativeTime(n.created_at) }}
                    </p>
                </li>
            </ul>
        </PanelCard>
        <PanelCard v-if="can.add_note" title="Add note" class="self-start">
            <form class="flex flex-col gap-3" @submit.prevent="addNote">
                <TextArea
                    v-model="noteForm.body"
                    :rows="4"
                    placeholder="Anything the team should know…"
                    aria-label="Note"
                    :invalid="!!noteForm.errors.body"
                />
                <p
                    v-if="noteForm.errors.body"
                    class="text-xs text-danger"
                    role="alert"
                >
                    {{ noteForm.errors.body }}
                </p>
                <Button
                    type="submit"
                    :disabled="noteForm.processing || !noteForm.body.trim()"
                    >Save note</Button
                >
            </form>
        </PanelCard>
    </div>

    <Dialog v-model:open="opening">
        <DialogContent class="rounded-[14px] sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold"
                    >Open a file</DialogTitle
                >
                <DialogDescription class="text-xs"
                    >Gives {{ student.name }} a new file number and tells them
                    by notification.</DialogDescription
                >
            </DialogHeader>
            <form class="flex flex-col gap-3.5" @submit.prevent="openFile">
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Service"
                        :error="fileForm.errors.service_id"
                    >
                        <SelectInput
                            :id="id"
                            v-model="fileForm.service_id"
                            :options="fileOptions.services"
                            placeholder="Choose"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Destination"
                        :error="fileForm.errors.country_id"
                    >
                        <SelectInput
                            :id="id"
                            v-model="fileForm.country_id"
                            :options="fileOptions.countries"
                            placeholder="Choose"
                            :invalid="invalid"
                        />
                    </FormField>
                </div>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Intake"
                    :error="fileForm.errors.intake"
                >
                    <Input
                        :id="id"
                        v-model="fileForm.intake"
                        placeholder="e.g. September 2027"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Notes"
                    :error="fileForm.errors.notes"
                >
                    <TextArea
                        :id="id"
                        v-model="fileForm.notes"
                        :rows="3"
                        :invalid="invalid"
                    />
                </FormField>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="opening = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="fileForm.processing"
                        ><Spinner v-if="fileForm.processing" /> Open
                        file</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
