<script setup lang="ts">
import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { Input } from '@/components/ui/input';
import { relativeTime } from '@/lib/format';
import type { Option, Paginated, TableColumn, Tone } from '@/types';

type Row = {
    id: number;
    name: string;
    original_name: string;
    student: string;
    student_number: string;
    uploaded_by: string | null;
    uploaded_at: string | null;
    status_label: string;
    status_tone: Tone;
    rejection_reason: string | null;
    can_review: boolean;
    view_url: string;
    review_url: string;
    student_url: string;
};

const props = defineProps<{
    documents: Paginated<Row>;
    status: string;
    statuses: Option[];
}>();
const page = usePage();
setLayoutProps({ breadcrumbs: [{ title: 'Document review', href: page.url }] });

const tab = ref(props.status);
watch(tab, (v) =>
    router.get(
        new URL(page.url, 'http://x').pathname,
        { status: v },
        { preserveState: true, replace: true },
    ),
);

const rejecting = ref<Row | null>(null);
const reject = useForm({ status: 'rejected', rejection_reason: '' });

function verify(row: Row): void {
    router.patch(
        row.review_url,
        { status: 'verified' },
        {
            preserveScroll: true,
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Could not verify.'),
        },
    );
}

function confirmReject(): void {
    if (!rejecting.value) {
        return;
    }

    reject.patch(rejecting.value.review_url, {
        preserveScroll: true,
        onSuccess: () => {
            rejecting.value = null;
            reject.reset();
        },
    });
}

const columns: TableColumn<Row>[] = [
    { key: 'name', label: 'Document' },
    { key: 'student', label: 'Student' },
    { key: 'uploaded_at', label: 'Uploaded', hideBelow: 'md' },
];
</script>

<template>
    <Head title="Document review" />

    <PageHeader
        eyebrow="Student documents"
        title="Document review"
        description="Oldest first, so nothing waits too long."
    />

    <PanelTabs
        v-model="tab"
        label="Document status"
        :tabs="statuses.map((s) => ({ key: s.value, label: s.label }))"
    />

    <PanelCard flush>
        <DataTable
            :columns="columns"
            :rows="documents.data"
            caption="Documents"
            empty-icon="task"
            :empty-title="
                status === 'pending'
                    ? 'Nothing waiting for review'
                    : 'No documents here'
            "
        >
            <template #cell-name="{ row }">
                <a
                    :href="row.view_url"
                    target="_blank"
                    rel="noopener"
                    class="font-semibold text-foreground hover:text-primary"
                    >{{ row.name }}</a
                >
                <span
                    class="block truncate text-[11px] text-muted-foreground"
                    >{{ row.original_name }}</span
                >
                <span
                    v-if="row.rejection_reason"
                    class="block text-[11px] text-danger"
                    >{{ row.rejection_reason }}</span
                >
            </template>
            <template #cell-student="{ row }">
                <Link
                    :href="row.student_url"
                    class="font-medium text-foreground hover:text-primary"
                    >{{ row.student }}</Link
                >
                <span class="block text-[11px] text-muted-foreground">{{
                    row.student_number
                }}</span>
            </template>
            <template #cell-uploaded_at="{ row }">
                {{ relativeTime(row.uploaded_at) }}
                <span
                    v-if="row.uploaded_by"
                    class="block text-[11px] text-muted-foreground"
                    >by {{ row.uploaded_by }}</span
                >
            </template>
            <template #actions="{ row }">
                <a
                    :href="row.view_url"
                    target="_blank"
                    rel="noopener"
                    class="tiny-action"
                    >View</a
                >
                <template v-if="row.can_review && status === 'pending'">
                    <button
                        type="button"
                        class="tiny-action !text-success"
                        @click="verify(row)"
                    >
                        Verify
                    </button>
                    <button
                        type="button"
                        class="tiny-action !text-danger"
                        @click="rejecting = row"
                    >
                        Reject
                    </button>
                </template>
                <StatusBadge
                    v-else
                    :label="row.status_label"
                    :tone="row.status_tone"
                />
            </template>
        </DataTable>
        <TablePagination :meta="documents" />
    </PanelCard>

    <ConfirmDialog
        :open="rejecting !== null"
        title="Reject document"
        description="The student sees this reason and can upload a new version."
        confirm-label="Reject"
        destructive
        :loading="reject.processing"
        @update:open="(v) => !v && (rejecting = null)"
        @confirm="confirmReject"
    >
        <Input
            v-model="reject.rejection_reason"
            placeholder="Reason"
            aria-label="Reason for rejection"
            :aria-invalid="!!reject.errors.rejection_reason"
        />
        <p
            v-if="reject.errors.rejection_reason"
            class="text-xs text-danger"
            role="alert"
        >
            {{ reject.errors.rejection_reason }}
        </p>
    </ConfirmDialog>
</template>
