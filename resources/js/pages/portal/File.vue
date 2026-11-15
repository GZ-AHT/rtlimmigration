<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import ProgressBar from '@/components/panel/ProgressBar.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { file as fileRoute } from '@/routes/portal';
import { index as documentsIndex } from '@/routes/portal/documents';
import { edit as profileEdit } from '@/routes/portal/profile';
import type { Completeness, FileRow } from '@/types';

defineProps<{
    student: { name: string; student_number: string };
    counselor: {
        name: string;
        email: string;
        phone: string | null;
        designation: string | null;
    } | null;
    files: FileRow[];
    completeness: Completeness;
    documents: { required: number; verified: number };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'My file', href: fileRoute() }] },
});
</script>

<template>
    <Head title="My file" />

    <PageHeader
        eyebrow="Student portal"
        title="My file"
        :description="`Student number ${student.student_number}`"
    />

    <div class="mb-4 grid gap-4 md:grid-cols-3">
        <PanelCard title="Profile">
            <ProgressBar :value="completeness.percent" label="Complete" />
            <Button
                v-if="completeness.percent < 100"
                variant="outline"
                size="sm"
                class="mt-3"
                as-child
            >
                <Link :href="profileEdit()">Complete my profile</Link>
            </Button>
        </PanelCard>
        <PanelCard title="Documents">
            <ProgressBar
                :value="
                    documents.required
                        ? (documents.verified / documents.required) * 100
                        : 100
                "
                label="Required verified"
            />
            <Button variant="outline" size="sm" class="mt-3" as-child>
                <Link :href="documentsIndex()">Upload documents</Link>
            </Button>
        </PanelCard>
        <PanelCard title="My counselor">
            <template v-if="counselor">
                <p class="text-[13px] font-semibold">{{ counselor.name }}</p>
                <p
                    v-if="counselor.designation"
                    class="text-xs text-muted-foreground"
                >
                    {{ counselor.designation }}
                </p>
                <p class="mt-2 text-xs">
                    <a
                        :href="`mailto:${counselor.email}`"
                        class="hover:text-primary"
                        >{{ counselor.email }}</a
                    >
                </p>
                <p v-if="counselor.phone" class="text-xs">
                    <a
                        :href="`tel:${counselor.phone}`"
                        class="hover:text-primary"
                        >{{ counselor.phone }}</a
                    >
                </p>
            </template>
            <p v-else class="text-[13px] text-muted-foreground">
                A counselor will be assigned to you soon.
            </p>
        </PanelCard>
    </div>

    <PanelCard
        title="My files"
        description="Your counselor opens a file for each application."
    >
        <EmptyState
            v-if="!files.length"
            icon="folder_open"
            title="No file opened yet"
            description="Your counselor will open your file once your consultation is complete."
        />
        <ul v-else class="divide-y">
            <li
                v-for="f in files"
                :key="f.id"
                class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-10 place-items-center rounded-lg bg-accent text-accent-foreground"
                        ><AppIcon name="folder_open" :size="20"
                    /></span>
                    <div>
                        <p class="text-[13px] font-semibold">
                            {{ f.file_number }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                [
                                    f.service,
                                    f.country,
                                    f.intake && `Intake ${f.intake}`,
                                ]
                                    .filter(Boolean)
                                    .join(' · ') || 'Details to be confirmed'
                            }}
                            · opened {{ formatDate(f.opened_at) }}
                        </p>
                    </div>
                </div>
                <StatusBadge :label="f.status_label" :tone="f.status_tone" />
            </li>
        </ul>
        <p class="mt-4 text-[11px] text-muted-foreground">
            Application and visa progress will appear here once processing
            starts.
        </p>
    </PanelCard>
</template>
