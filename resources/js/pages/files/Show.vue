<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, relativeTime } from '@/lib/format';
import type { ActivityEntry, FileRow, Option, Tone } from '@/types';

type FileDetail = FileRow & {
    service_id: number | null;
    country_id: number | null;
    notes: string | null;
    close_reason: string | null;
    opened_by: string | null;
    counselor: string | null;
    student: {
        id: number;
        name: string;
        number: string;
        email: string;
        url: string;
    };
    documents: {
        id: number;
        name: string;
        status_label: string;
        status_tone: Tone;
        view_url: string;
    }[];
    can: { update: boolean };
};

const props = defineProps<{
    file: FileDetail;
    activity: ActivityEntry[];
    options: { statuses: Option[]; services: Option[]; countries: Option[] };
    urls: { index: string; update: string; status: string; documents: string };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Student files', href: props.urls.index }],
});

const details = useForm({
    service_id: props.file.service_id ? String(props.file.service_id) : '',
    country_id: props.file.country_id ? String(props.file.country_id) : '',
    intake: props.file.intake ?? '',
    notes: props.file.notes ?? '',
});
const status = ref(props.file.status);
const closeReason = ref('');
const busy = ref(false);

function saveDetails(): void {
    details
        .transform((d) => ({
            service_id: d.service_id || null,
            country_id: d.country_id || null,
            intake: d.intake || null,
            notes: d.notes || null,
        }))
        .put(props.urls.update, { preserveScroll: true });
}

function saveStatus(): void {
    router.patch(
        props.urls.status,
        { status: status.value, close_reason: closeReason.value || null },
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Could not update status.'),
            onSuccess: () => (closeReason.value = ''),
            onFinish: () => (busy.value = false),
        },
    );
}
</script>

<template>
    <Head :title="`File ${file.file_number}`" />

    <PageHeader
        :eyebrow="`File · opened ${formatDateTime(file.opened_at)}`"
        :title="file.file_number"
        :description="
            [file.service, file.country, file.intake && `Intake ${file.intake}`]
                .filter(Boolean)
                .join(' · ') || 'No service chosen yet'
        "
    >
        <template #actions>
            <StatusBadge
                :label="file.status_label"
                :tone="file.status_tone"
                class="self-center"
            />
            <Button variant="outline" as-child
                ><Link :href="file.student.url"
                    ><AppIcon name="school" :size="18" /> Student</Link
                ></Button
            >
            <Button variant="outline" as-child
                ><Link :href="urls.documents"
                    ><AppIcon name="description" :size="18" /> Documents</Link
                ></Button
            >
        </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(300px,1fr)]">
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard title="File details">
                <form
                    v-if="file.can.update"
                    class="grid gap-3.5 sm:grid-cols-2"
                    @submit.prevent="saveDetails"
                >
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Service"
                        :error="details.errors.service_id"
                    >
                        <SelectInput
                            :id="id"
                            v-model="details.service_id"
                            :options="options.services"
                            placeholder="Choose"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Destination"
                        :error="details.errors.country_id"
                    >
                        <SelectInput
                            :id="id"
                            v-model="details.country_id"
                            :options="options.countries"
                            placeholder="Choose"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Intake"
                        :error="details.errors.intake"
                    >
                        <Input
                            :id="id"
                            v-model="details.intake"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <div class="sm:col-span-2">
                        <FormField
                            v-slot="{ id, invalid }"
                            label="Notes"
                            :error="details.errors.notes"
                        >
                            <TextArea
                                :id="id"
                                v-model="details.notes"
                                :rows="3"
                                :invalid="invalid"
                            />
                        </FormField>
                    </div>
                    <div class="flex justify-end sm:col-span-2">
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="details.processing || !details.isDirty"
                            ><Spinner v-if="details.processing" /> Save
                            details</Button
                        >
                    </div>
                </form>
                <p
                    v-else
                    class="text-[13px] whitespace-pre-line text-secondary-foreground"
                >
                    {{ file.notes ?? 'No notes.' }}
                </p>
            </PanelCard>

            <PanelCard title="Documents for this file">
                <EmptyState
                    v-if="!file.documents.length"
                    icon="description"
                    title="No documents linked to this file"
                    description="Choose this file when uploading a document to link it here."
                />
                <ul v-else class="divide-y">
                    <li
                        v-for="d in file.documents"
                        :key="d.id"
                        class="flex items-center justify-between gap-3 py-2.5 text-[13px] first:pt-0 last:pb-0"
                    >
                        <a
                            :href="d.view_url"
                            target="_blank"
                            rel="noopener"
                            class="font-medium hover:text-primary"
                            >{{ d.name }}</a
                        >
                        <StatusBadge
                            :label="d.status_label"
                            :tone="d.status_tone"
                        />
                    </li>
                </ul>
            </PanelCard>
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard title="Student">
                <p class="text-[13px] font-semibold">{{ file.student.name }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ file.student.number }} · {{ file.student.email }}
                </p>
                <p class="mt-3 text-xs text-muted-foreground">
                    Counselor:
                    <b class="text-foreground">{{
                        file.counselor ?? 'Unassigned'
                    }}</b>
                </p>
                <p class="text-xs text-muted-foreground">
                    Opened by:
                    <b class="text-foreground">{{ file.opened_by ?? '—' }}</b>
                </p>
            </PanelCard>

            <PanelCard v-if="file.can.update" title="Status">
                <form class="flex flex-col gap-2" @submit.prevent="saveStatus">
                    <SelectInput
                        v-model="status"
                        :options="options.statuses"
                        label="File status"
                    />
                    <Input
                        v-if="status === 'closed'"
                        v-model="closeReason"
                        placeholder="Reason for closing (required)"
                        aria-label="Reason for closing"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="busy || status === file.status"
                        >Update status</Button
                    >
                </form>
                <p
                    v-if="file.close_reason"
                    class="mt-3 text-xs text-muted-foreground"
                >
                    Closed because: {{ file.close_reason }}
                </p>
                <p class="mt-3 text-[11px] text-muted-foreground">
                    Visa stages are tracked separately (Phase 6).
                </p>
            </PanelCard>

            <PanelCard title="Activity">
                <EmptyState
                    v-if="!activity.length"
                    icon="history"
                    title="No changes yet"
                />
                <ol v-else class="relative ml-1.5 border-l">
                    <li
                        v-for="a in activity"
                        :key="a.id"
                        class="relative pb-3 pl-5 last:pb-0"
                    >
                        <span
                            class="absolute top-1 -left-[5px] size-2.5 rounded-full border-2 border-card bg-primary"
                        />
                        <p class="text-[13px]">{{ a.summary }}</p>
                        <p class="text-[11px] text-muted-foreground">
                            {{ a.causer ?? 'System' }} ·
                            {{ relativeTime(a.created_at) }}
                        </p>
                    </li>
                </ol>
            </PanelCard>
        </div>
    </div>
</template>
