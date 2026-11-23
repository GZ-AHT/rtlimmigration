<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';
import { approve, destroy, index, reject } from '@/routes/admin/partner-applications';
import { show as showPartner } from '@/routes/admin/partners';
import type { Tone } from '@/types';

type Doc = {
    id: number;
    title: string;
    name: string;
    size: number;
    url: string;
};
type Application = {
    id: number;
    organisation: string;
    contact_name: string;
    email: string;
    phone: string | null;
    country: string | null;
    city: string | null;
    website: string | null;
    type: string;
    students_per_year: number | null;
    job_title: string | null;
    partnership_interest: string | null;
    years_in_operation: string | null;
    destinations: string[];
    message: string | null;
    status: string;
    status_label: string;
    status_tone: Tone;
    created_at: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    review_note: string | null;
    partner: { id: number; organisation: string } | null;
    documents: Doc[];
};

const props = defineProps<{ application: Application; canApprove: boolean }>();
const deleting = ref(false);

function remove(): void {
    router.delete(destroy(props.application.id).url, {
        onFinish: () => (deleting.value = false),
    });
}
defineOptions({
    layout: { breadcrumbs: [{ title: 'Partner applications', href: index() }] },
});

const decision = ref<'approve' | 'reject' | null>(null);
const approveForm = useForm({ commission_rate: '', note: '' });
const rejectForm = useForm({ note: '' });
const a = props.application;
</script>

<template>
    <Head :title="application.organisation" />
    <PageHeader eyebrow="Partner application" :title="application.organisation">
        <template #actions>
            <StatusBadge
                :label="application.status_label"
                :tone="application.status_tone"
            />
            <Button variant="outline" as-child
                ><Link :href="index()">All applications</Link></Button
            >
            <Button
                v-if="canApprove && !application.partner"
                variant="outline"
                @click="deleting = true"
                >Delete</Button
            >
        </template>
    </PageHeader>

    <ConfirmDialog
        :open="deleting"
        title="Delete this application?"
        description="The application and its uploaded documents are permanently removed."
        confirm-label="Delete application"
        destructive
        @update:open="(v) => !v && (deleting = false)"
        @confirm="remove"
    />

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,1fr)]">
        <PanelCard title="Application">
            <dl class="grid gap-x-6 gap-y-3 text-[13px] sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">Contact</dt>
                    <dd class="font-semibold">{{ a.contact_name }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Type</dt>
                    <dd>{{ a.type }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Email</dt>
                    <dd>
                        <a
                            :href="`mailto:${a.email}`"
                            class="text-primary hover:underline"
                            >{{ a.email }}</a
                        >
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Phone</dt>
                    <dd>{{ a.phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Location</dt>
                    <dd>
                        {{
                            [a.city, a.country].filter(Boolean).join(', ') ||
                            '—'
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Website</dt>
                    <dd>
                        <a
                            v-if="a.website"
                            :href="a.website"
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            class="text-primary hover:underline"
                            >{{ a.website }}</a
                        ><span v-else>—</span>
                    </dd>
                </div>
                <div v-if="a.job_title">
                    <dt class="text-muted-foreground">Job title</dt>
                    <dd>{{ a.job_title }}</dd>
                </div>
                <div v-if="a.partnership_interest">
                    <dt class="text-muted-foreground">Partnership interest</dt>
                    <dd>{{ a.partnership_interest }}</dd>
                </div>
                <div v-if="a.years_in_operation">
                    <dt class="text-muted-foreground">Years in operation</dt>
                    <dd>{{ a.years_in_operation }}</dd>
                </div>
                <div v-if="a.destinations.length">
                    <dt class="text-muted-foreground">Study destinations</dt>
                    <dd>{{ a.destinations.join(', ') }}</dd>
                </div>
                <div v-if="a.students_per_year !== null">
                    <dt class="text-muted-foreground">Students per year</dt>
                    <dd>{{ a.students_per_year }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Received</dt>
                    <dd>{{ formatDateTime(a.created_at) }}</dd>
                </div>
            </dl>
            <div
                v-if="a.message"
                class="mt-4 rounded-lg bg-muted p-3 text-[13px] whitespace-pre-line"
            >
                {{ a.message }}
            </div>
            <div v-if="a.documents.length" class="mt-4">
                <p class="mb-1.5 text-[13px] font-semibold">Documents</p>
                <ul class="flex flex-col gap-1 text-[13px]">
                    <li v-for="d in a.documents" :key="d.id">
                        <a :href="d.url" class="text-primary hover:underline">{{
                            d.title
                        }}</a>
                        <span class="text-muted-foreground"
                            >({{ d.name }})</span
                        >
                    </li>
                </ul>
            </div>
        </PanelCard>

        <PanelCard title="Decision" class="self-start">
            <div
                v-if="a.status !== 'pending'"
                class="flex flex-col gap-2 text-[13px]"
            >
                <p>
                    {{ a.status_label }} by {{ a.reviewer ?? 'unknown' }} ·
                    {{ formatDateTime(a.reviewed_at) }}
                </p>
                <p
                    v-if="a.review_note"
                    class="rounded-md bg-muted p-2 whitespace-pre-line"
                >
                    {{ a.review_note }}
                </p>
                <Link
                    v-if="a.partner"
                    :href="showPartner(a.partner.id)"
                    class="font-semibold text-primary hover:underline"
                    >Open partner profile →</Link
                >
            </div>
            <p
                v-else-if="!canApprove"
                class="text-[13px] text-muted-foreground"
            >
                Waiting for someone with approval rights.
            </p>
            <div v-else class="flex flex-col gap-3">
                <div v-if="decision === null" class="flex gap-2">
                    <Button class="flex-1" @click="decision = 'approve'"
                        >Approve</Button
                    >
                    <Button
                        class="flex-1"
                        variant="outline"
                        @click="decision = 'reject'"
                        >Reject</Button
                    >
                </div>
                <form
                    v-else-if="decision === 'approve'"
                    class="flex flex-col gap-3"
                    @submit.prevent="
                        approveForm
                            .transform((d) => ({
                                ...d,
                                commission_rate: d.commission_rate || null,
                            }))
                            .post(approve(a.id).url)
                    "
                >
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Commission rate (%)"
                        :error="approveForm.errors.commission_rate"
                    >
                        <Input
                            :id="id"
                            v-model="approveForm.commission_rate"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Note (optional)"
                        :error="approveForm.errors.note"
                    >
                        <TextArea
                            :id="id"
                            v-model="approveForm.note"
                            :rows="3"
                            :maxlength="1000"
                        />
                    </FormField>
                    <div class="flex gap-2">
                        <Button type="submit" :disabled="approveForm.processing"
                            >Approve and create partner</Button
                        ><Button
                            type="button"
                            variant="ghost"
                            @click="decision = null"
                            >Back</Button
                        >
                    </div>
                </form>
                <form
                    v-else
                    class="flex flex-col gap-3"
                    @submit.prevent="
                        rejectForm.post(reject(a.id).url, {
                            preserveScroll: true,
                        })
                    "
                >
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Reason"
                        required
                        :error="rejectForm.errors.note"
                    >
                        <TextArea
                            :id="id"
                            v-model="rejectForm.note"
                            :rows="3"
                            :maxlength="1000"
                            :invalid="invalid"
                        />
                    </FormField>
                    <div class="flex gap-2">
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="rejectForm.processing"
                            >Reject</Button
                        ><Button
                            type="button"
                            variant="ghost"
                            @click="decision = null"
                            >Back</Button
                        >
                    </div>
                </form>
            </div>
        </PanelCard>
    </div>
</template>
