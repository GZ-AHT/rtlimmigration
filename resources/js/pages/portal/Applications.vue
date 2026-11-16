<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StageTrack from '@/components/applications/StageTrack.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import ProgressBar from '@/components/panel/ProgressBar.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime } from '@/lib/format';
import { index, status } from '@/routes/portal/applications';
import { index as documentsIndex } from '@/routes/portal/documents';
import type {
    ApplicationRow,
    VisaChecklistItem,
    StageStep,
    VisaRequirementItem,
} from '@/types';

defineProps<{
    applications: (ApplicationRow & {
        progress: number;
        stages: StageStep[];
        checklist: VisaChecklistItem[];
        requirements: VisaRequirementItem[];
    })[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Visa application', href: index() }] },
});
</script>

<template>
    <Head title="Visa application" />

    <PageHeader
        eyebrow="Student portal"
        title="Visa application"
        description="Your applications, the steps each one goes through and what is needed from you."
    >
        <template #actions>
            <Button variant="outline" as-child>
                <Link :href="status()">
                    <AppIcon name="timeline" :size="18" /> Status updates
                </Link>
            </Button>
        </template>
    </PageHeader>

    <PanelCard v-if="!applications.length">
        <EmptyState
            icon="flight_takeoff"
            title="No application yet"
            description="Your counselor starts your application once your file and first documents are ready."
        />
    </PanelCard>

    <div v-else class="flex flex-col gap-4">
        <PanelCard v-for="a in applications" :key="a.id">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-display text-lg font-extrabold">
                        {{ a.visa_type
                        }}<template v-if="a.country">
                            · {{ a.country }}</template
                        >
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {{ a.application_number }}
                        <template v-if="a.organisation">
                            · {{ a.organisation }}</template
                        >
                        <template v-if="a.title"> · {{ a.title }}</template>
                    </p>
                </div>
                <StatusBadge :label="a.status_label" :tone="a.status_tone" />
            </div>

            <div
                v-if="a.decision"
                class="mt-4 rounded-lg p-3 text-[13px]"
                :class="
                    a.decision === 'approved'
                        ? 'bg-success-soft text-success'
                        : 'bg-danger-soft text-danger'
                "
            >
                <p class="font-bold">
                    {{
                        a.decision === 'approved'
                            ? 'Your visa has been approved.'
                            : 'Your visa application was not approved.'
                    }}
                </p>
                <p v-if="a.rejection_reason" class="mt-1 whitespace-pre-line">
                    Reason given: {{ a.rejection_reason }}
                </p>
                <p class="mt-1 text-foreground">
                    Your counselor will go through the next steps with you.
                </p>
            </div>

            <p
                v-if="
                    a.office_visit_requested_at && !a.office_visit_completed_at
                "
                class="mt-3 flex items-center gap-2 rounded-lg bg-warning-soft p-3 text-[13px] text-warning"
            >
                <AppIcon name="apartment" :size="18" />
                <span
                    >Please visit our office<template v-if="a.office_visit_at">
                        on
                        <b>{{ formatDateTime(a.office_visit_at) }}</b></template
                    >. Contact your counselor if you need another time.</span
                >
            </p>

            <dl
                v-if="a.scheduled_at || a.result || a.reference_number"
                class="mt-4 grid gap-3 text-[13px] sm:grid-cols-3"
            >
                <div v-if="a.scheduled_at">
                    <dt class="text-xs text-muted-foreground">
                        {{ a.labels.schedule }}
                    </dt>
                    <dd class="font-semibold">
                        {{ formatDateTime(a.scheduled_at) }}
                    </dd>
                </div>
                <div v-if="a.result">
                    <dt class="text-xs text-muted-foreground">Result</dt>
                    <dd class="font-semibold">{{ a.result }}</dd>
                </div>
                <div v-if="a.reference_number">
                    <dt class="text-xs text-muted-foreground">
                        Reference number
                    </dt>
                    <dd class="font-semibold">{{ a.reference_number }}</dd>
                </div>
            </dl>

            <div
                class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]"
            >
                <section>
                    <h3
                        class="mb-3 text-xs font-bold text-secondary-foreground"
                    >
                        Progress
                    </h3>
                    <ProgressBar
                        :value="a.progress"
                        label="Through the process"
                        class="mb-4"
                    />
                    <StageTrack :stages="a.stages" show-descriptions />
                </section>

                <div class="flex flex-col gap-5">
                    <section v-if="a.checklist.length">
                        <h3
                            class="mb-3 text-xs font-bold text-secondary-foreground"
                        >
                            Documents we need
                        </h3>
                        <ul class="flex flex-col gap-2.5">
                            <li
                                v-for="c in a.checklist"
                                :key="c.category"
                                class="flex items-center justify-between gap-3 text-[13px]"
                            >
                                <span>{{ c.category }}</span>
                                <StatusBadge :label="c.label" :tone="c.tone" />
                            </li>
                        </ul>
                        <Button
                            size="sm"
                            variant="outline"
                            class="mt-3"
                            as-child
                        >
                            <Link :href="documentsIndex()"
                                ><AppIcon name="upload" :size="16" /> Upload
                                documents</Link
                            >
                        </Button>
                    </section>

                    <section v-if="a.requirements.length">
                        <h3
                            class="mb-3 text-xs font-bold text-secondary-foreground"
                        >
                            Requirements
                        </h3>
                        <ul class="flex flex-col gap-3 text-[13px]">
                            <li v-for="r in a.requirements" :key="r.title">
                                <p class="font-semibold">{{ r.title }}</p>
                                <p
                                    v-if="r.description"
                                    class="text-xs text-secondary-foreground"
                                >
                                    {{ r.description }}
                                </p>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
            <p class="mt-4 text-[11px] text-muted-foreground">
                Started {{ formatDate(a.created_at) }}
            </p>
        </PanelCard>
    </div>
</template>
