<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import UploadDocumentDialog from '@/components/documents/UploadDocumentDialog.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import ProgressBar from '@/components/panel/ProgressBar.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, relativeTime } from '@/lib/format';
import type {
    Checklist,
    ChecklistItem,
    DocumentItem,
    Option,
    UploadLimits,
} from '@/types';

/**
 * Document checklist (REQ §10 "Documents Management", §11.2 verification).
 * Shows each category with its current document; lets permitted users
 * upload, replace, review and withdraw.
 */
const props = defineProps<{
    checklist: Checklist;
    canUpload: boolean;
    uploadUrl: string | null;
    categories: Option[];
    files: Option[];
    limits: UploadLimits;
}>();

const uploading = ref(false);
const preset = ref<number | null>(null);
const replacing = ref(false);
const expanded = ref<number | null>(null);
const reviewing = ref<DocumentItem | null>(null);
const withdrawing = ref<DocumentItem | null>(null);
const review = useForm({ status: 'verified', rejection_reason: '' });

function startUpload(item: ChecklistItem | null): void {
    preset.value = item?.category_id ?? null;
    replacing.value = !!item?.document;
    uploading.value = true;
}

function startReview(doc: DocumentItem, status: 'verified' | 'rejected'): void {
    review.reset();
    review.clearErrors();
    review.status = status;

    if (status === 'verified') {
        submitReview(doc);
    } else {
        reviewing.value = doc;
    }
}

function submitReview(doc: DocumentItem | null): void {
    if (!doc?.review_url) {
        return;
    }

    review.patch(doc.review_url, {
        preserveScroll: true,
        onSuccess: () => (reviewing.value = null),
        onError: (e) => {
            if (!e.rejection_reason) {
                toast.error(
                    Object.values(e)[0] ?? 'Could not save the review.',
                );
            }
        },
    });
}

function withdraw(): void {
    const doc = withdrawing.value;

    if (!doc?.delete_url) {
        return;
    }

    router.delete(doc.delete_url, {
        preserveScroll: true,
        onFinish: () => (withdrawing.value = null),
    });
}

function size(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

const statusIcon = {
    pending: 'hourglass_top',
    verified: 'verified',
    rejected: 'error',
} as const;
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-[220px] flex-1">
                <ProgressBar
                    :value="
                        checklist.required
                            ? (checklist.verified / checklist.required) * 100
                            : 100
                    "
                    :label="`Required documents verified: ${checklist.verified} of ${checklist.required}`"
                />
            </div>
            <Button
                v-if="canUpload && uploadUrl"
                variant="outline"
                @click="startUpload(null)"
            >
                <AppIcon name="upload" :size="18" /> Upload other document
            </Button>
        </div>

        <ul class="divide-y rounded-xl border bg-card">
            <li
                v-for="item in checklist.items"
                :key="item.category_id ?? `doc-${item.document?.id}`"
                class="p-4"
            >
                <div class="flex flex-wrap items-start gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-lg"
                        :class="
                            item.document
                                ? {
                                      pending: 'bg-warning-soft text-warning',
                                      verified: 'bg-success-soft text-success',
                                      rejected: 'bg-danger-soft text-danger',
                                  }[item.document.status]
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        <AppIcon
                            :name="
                                item.document
                                    ? statusIcon[item.document.status]
                                    : 'description'
                            "
                            :size="20"
                        />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="flex flex-wrap items-center gap-2 text-[13px] font-semibold"
                        >
                            {{ item.category }}
                            <StatusBadge
                                v-if="item.required"
                                label="Required"
                                tone="info"
                            />
                            <StatusBadge
                                v-if="item.document"
                                :label="item.document.status_label"
                                :tone="item.document.status_tone"
                            />
                            <StatusBadge
                                v-else-if="item.required"
                                label="Missing"
                                tone="neutral"
                            />
                        </p>
                        <p
                            v-if="!item.document && item.description"
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            {{ item.description }}
                        </p>
                        <template v-if="item.document">
                            <p
                                class="mt-1 truncate text-xs text-secondary-foreground"
                            >
                                <a
                                    :href="item.document.view_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="font-medium hover:text-primary hover:underline"
                                    >{{ item.document.original_name }}</a
                                >
                                · {{ size(item.document.size) }}
                                <template v-if="item.document.file_number">
                                    · {{ item.document.file_number }}</template
                                >
                                <template v-if="item.document.expires_at">
                                    · expires
                                    {{
                                        formatDate(item.document.expires_at)
                                    }}</template
                                >
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                Uploaded
                                {{ relativeTime(item.document.uploaded_at)
                                }}{{
                                    item.document.uploaded_by
                                        ? ` by ${item.document.uploaded_by}`
                                        : ''
                                }}
                                <template v-if="item.document.reviewed_by">
                                    · reviewed by
                                    {{ item.document.reviewed_by }}</template
                                >
                            </p>
                            <p
                                v-if="
                                    item.document.status === 'rejected' &&
                                    item.document.rejection_reason
                                "
                                class="mt-1.5 rounded-md bg-danger-soft px-2.5 py-1.5 text-xs text-danger"
                            >
                                <b>Reason:</b>
                                {{ item.document.rejection_reason }}
                            </p>
                        </template>
                    </div>
                    <!-- Own row on phones so the text keeps its width. -->
                    <div
                        class="flex w-full flex-wrap gap-1.5 pl-[52px] sm:w-auto sm:pl-0"
                    >
                        <template v-if="item.document">
                            <a
                                :href="item.document.download_url"
                                class="tiny-action"
                                ><AppIcon name="download" :size="14" />
                                Download</a
                            >
                            <template
                                v-if="
                                    item.document.can.review &&
                                    item.document.status !== 'verified'
                                "
                            >
                                <button
                                    type="button"
                                    class="tiny-action !text-success"
                                    @click="
                                        startReview(item.document, 'verified')
                                    "
                                >
                                    Verify
                                </button>
                                <button
                                    v-if="item.document.status !== 'rejected'"
                                    type="button"
                                    class="tiny-action !text-danger"
                                    @click="
                                        startReview(item.document, 'rejected')
                                    "
                                >
                                    Reject
                                </button>
                            </template>
                            <button
                                v-if="item.document.can.delete"
                                type="button"
                                class="tiny-action"
                                @click="withdrawing = item.document"
                            >
                                Withdraw
                            </button>
                        </template>
                        <button
                            v-if="canUpload && uploadUrl && item.category_id"
                            type="button"
                            class="tiny-action"
                            @click="startUpload(item)"
                        >
                            <AppIcon
                                :name="item.document ? 'sync' : 'upload'"
                                :size="14"
                            />
                            {{ item.document ? 'Replace' : 'Upload' }}
                        </button>
                        <button
                            v-if="item.history.length"
                            type="button"
                            class="tiny-action"
                            :aria-expanded="expanded === item.category_id"
                            @click="
                                expanded =
                                    expanded === item.category_id
                                        ? null
                                        : item.category_id
                            "
                        >
                            History ({{ item.history.length }})
                        </button>
                    </div>
                </div>
                <ul
                    v-if="expanded === item.category_id && item.history.length"
                    class="mt-3 ml-[52px] flex flex-col gap-1.5 border-l pl-3"
                >
                    <li
                        v-for="h in item.history"
                        :key="h.id"
                        class="text-xs text-muted-foreground"
                    >
                        <a
                            v-if="h.view_url"
                            :href="h.view_url"
                            target="_blank"
                            rel="noopener"
                            class="font-medium text-secondary-foreground hover:underline"
                            >{{ h.original_name }}</a
                        >
                        <span v-else class="line-through">{{
                            h.original_name
                        }}</span>
                        · {{ h.status_label }} · {{ relativeTime(h.uploaded_at)
                        }}{{ h.uploaded_by ? ` by ${h.uploaded_by}` : '' }}
                    </li>
                </ul>
            </li>
        </ul>

        <UploadDocumentDialog
            v-if="uploadUrl"
            v-model:open="uploading"
            :submit-url="uploadUrl"
            :categories="categories"
            :files="files"
            :limits="limits"
            :preset-category-id="preset"
            :replacing="replacing"
        />

        <ConfirmDialog
            :open="reviewing !== null"
            title="Reject document"
            description="The student will see this reason and can upload a new version."
            confirm-label="Reject"
            destructive
            :loading="review.processing"
            @update:open="(v) => !v && (reviewing = null)"
            @confirm="submitReview(reviewing)"
        >
            <Input
                v-model="review.rejection_reason"
                placeholder="e.g. The scan is blurry. Please upload a clearer copy."
                aria-label="Reason for rejection"
                :aria-invalid="!!review.errors.rejection_reason"
            />
            <p
                v-if="review.errors.rejection_reason"
                class="text-xs text-danger"
                role="alert"
            >
                {{ review.errors.rejection_reason }}
            </p>
        </ConfirmDialog>

        <ConfirmDialog
            :open="withdrawing !== null"
            title="Withdraw this document?"
            description="It is removed from review. Any earlier version becomes current again."
            confirm-label="Withdraw"
            destructive
            @update:open="(v) => !v && (withdrawing = null)"
            @confirm="withdraw"
        />
    </div>
</template>
