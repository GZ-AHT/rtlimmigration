<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { index as assessmentsIndex } from '@/routes/admin/skill-assessments';
import { index } from '@/routes/admin/skill-assessments/submissions';

/** One visitor's assessment: answers, points, category scores and the advice they saw. */
defineProps<{
    submission: {
        id: number;
        name: string;
        email: string;
        phone: string;
        country: string;
        title: string;
        status: string;
        preview: boolean;
        score: number | null;
        passed: boolean | null;
        created_at: string | null;
        pass_percentage: number;
        categories: { category: string; percent: number }[];
        band_title: string | null;
        band_text: string | null;
        lead_url: string | null;
        result_url: string | null;
        answers: {
            question: string;
            category: string;
            answer: string | null;
            points: number | null;
            max: number;
        }[];
    };
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Skill migration', href: assessmentsIndex() },
            { title: 'Submissions', href: index() },
            { title: 'Submission' },
        ],
    },
});
</script>

<template>
    <Head :title="`Assessment: ${submission.name}`" />
    <PageHeader
        eyebrow="Skill migration submission"
        :title="submission.name"
        :description="`${submission.title} · ${formatDateTime(submission.created_at)}`"
    >
        <template #actions>
            <StatusBadge
                v-if="submission.preview"
                label="Staff preview"
                tone="info"
            />
            <Button v-if="submission.lead_url" variant="outline" as-child
                ><Link :href="submission.lead_url">Open lead</Link></Button
            >
            <Button v-if="submission.result_url" variant="outline" as-child
                ><a :href="submission.result_url" target="_blank" rel="noopener"
                    >Result page</a
                ></Button
            >
        </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
        <PanelCard title="Answers" flush>
            <table class="w-full text-[13px]">
                <thead
                    class="text-left text-[11px] text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-2 font-semibold">Question</th>
                        <th class="px-4 py-2 font-semibold">Answer</th>
                        <th class="px-4 py-2 text-right font-semibold">
                            Points
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(a, i) in submission.answers"
                        :key="i"
                        class="border-t border-border align-top"
                    >
                        <td class="px-4 py-2.5">
                            <span class="block font-medium">{{
                                a.question
                            }}</span
                            ><span class="text-[11px] text-muted-foreground">{{
                                a.category
                            }}</span>
                        </td>
                        <td class="px-4 py-2.5">{{ a.answer ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                            {{ a.points ?? '—' }} / {{ a.max }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </PanelCard>

        <div class="flex flex-col gap-4">
            <PanelCard title="Result">
                <template v-if="submission.status === 'completed'">
                    <p class="font-display text-4xl font-extrabold">
                        {{ Math.round(submission.score ?? 0) }}%
                    </p>
                    <p class="mt-1 text-[13px] text-muted-foreground">
                        Pass mark {{ submission.pass_percentage }}%
                    </p>
                    <StatusBadge
                        class="mt-2"
                        :label="
                            submission.passed ? 'Meets guide' : 'Below guide'
                        "
                        :tone="submission.passed ? 'success' : 'warning'"
                    />
                    <p
                        v-if="submission.band_title"
                        class="mt-4 text-[13px] font-semibold"
                    >
                        {{ submission.band_title }}
                    </p>
                    <p
                        v-if="submission.band_text"
                        class="mt-1 text-[13px] text-muted-foreground"
                    >
                        {{ submission.band_text }}
                    </p>
                    <ul class="mt-4 flex flex-col gap-2">
                        <li
                            v-for="c in submission.categories"
                            :key="c.category"
                            class="text-[12px]"
                        >
                            <div class="flex justify-between">
                                <span>{{ c.category }}</span
                                ><span class="font-semibold"
                                    >{{ Math.round(c.percent) }}%</span
                                >
                            </div>
                            <div
                                class="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{ width: `${c.percent}%` }"
                                />
                            </div>
                        </li>
                    </ul>
                </template>
                <p v-else class="text-[13px] text-muted-foreground">
                    The visitor started but hasn’t finished.
                </p>
            </PanelCard>
            <PanelCard title="Contact">
                <dl class="flex flex-col gap-2 text-[13px]">
                    <div>
                        <dt class="text-muted-foreground">Destination</dt>
                        <dd class="font-medium">{{ submission.country }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Email</dt>
                        <dd>
                            <a
                                :href="`mailto:${submission.email}`"
                                class="text-primary hover:underline"
                                >{{ submission.email }}</a
                            >
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Phone</dt>
                        <dd>{{ submission.phone }}</dd>
                    </div>
                </dl>
            </PanelCard>
        </div>
    </div>
</template>
