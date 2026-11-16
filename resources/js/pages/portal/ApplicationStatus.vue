<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ApplicationTimeline from '@/components/applications/ApplicationTimeline.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import ProgressBar from '@/components/panel/ProgressBar.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { index, status } from '@/routes/portal/applications';
import type { ApplicationHistoryEntry, ApplicationRow } from '@/types';

defineProps<{
    applications: (ApplicationRow & {
        progress: number;
        history: ApplicationHistoryEntry[];
    })[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Application status', href: status() }] },
});
</script>

<template>
    <Head title="Application status" />

    <PageHeader
        eyebrow="Student portal"
        title="Application status"
        description="Every update on your application, newest first, with any documents your counselor attached."
    >
        <template #actions>
            <Button variant="outline" as-child>
                <Link :href="index()">
                    <AppIcon name="flight_takeoff" :size="18" /> Application
                    details
                </Link>
            </Button>
        </template>
    </PageHeader>

    <PanelCard v-if="!applications.length">
        <EmptyState
            icon="timeline"
            title="No updates yet"
            description="Updates appear here as soon as your counselor starts your application."
        />
    </PanelCard>

    <div v-else class="flex flex-col gap-4">
        <PanelCard v-for="a in applications" :key="a.id">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-display text-lg font-extrabold">
                        {{ a.visa_type
                        }}<template v-if="a.country">
                            · {{ a.country }}</template
                        >
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {{ a.application_number }} · now at
                        <b class="text-foreground">{{ a.stage ?? '—' }}</b>
                    </p>
                </div>
                <StatusBadge :label="a.status_label" :tone="a.status_tone" />
            </div>
            <ProgressBar
                :value="a.progress"
                label="Through the process"
                class="mb-5"
            />
            <ApplicationTimeline :history="a.history" />
        </PanelCard>
    </div>
</template>
