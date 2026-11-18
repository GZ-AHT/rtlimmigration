<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import RichTextEditor from '@/components/cms/RichTextEditor.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { flag } from '@/lib/flag';
import { destroy, index, update } from '@/routes/admin/skill-assessments';
import { index as questionsIndex } from '@/routes/admin/skill-assessments/questions';

/**
 * A country's assessment: details and publishing, which bank questions it
 * uses (order and weight), and recommendations by score.
 */
type BankQuestion = {
    id: number;
    text: string;
    category: string;
    category_id: number | null;
    is_active: boolean;
    options: number;
    max_points: number;
};
type Band = { min_percent: number; title: string; text: string };

const props = defineProps<{
    assessment: {
        id: number;
        country: string;
        iso2: string;
        country_active: boolean;
        slug: string;
        title: string;
        summary: string;
        intro: string;
        pass_percentage: number;
        minutes: number | null;
        status: string;
        bands: Band[];
        questions: { id: number; weight: number }[];
        url: string;
    };
    bank: BankQuestion[];
    categories: { id: number; name: string }[];
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Skill migration', href: index() },
            { title: 'Edit assessment' },
        ],
    },
});

const form = useForm({
    slug: props.assessment.slug,
    title: props.assessment.title,
    summary: props.assessment.summary,
    intro: props.assessment.intro,
    pass_percentage: props.assessment.pass_percentage,
    minutes: (props.assessment.minutes ?? '') as number | string,
    status: props.assessment.status,
    bands: props.assessment.bands.map((b) => ({ ...b })),
    questions: props.assessment.questions.map((q) => ({ ...q })),
});

const bankById = computed(() => new Map(props.bank.map((q) => [q.id, q])));
const usable = (q: BankQuestion | undefined) =>
    !!q && q.is_active && q.options >= 2;
const totalPoints = computed(() =>
    form.questions.reduce(
        (sum, q) =>
            sum +
            (usable(bankById.value.get(q.id))
                ? (bankById.value.get(q.id)?.max_points ?? 0) * q.weight
                : 0),
        0,
    ),
);

// Adding questions from the bank.
const filterCategory = ref('');
const filterText = ref('');
const available = computed(() =>
    props.bank.filter(
        (q) =>
            !form.questions.some((x) => x.id === q.id) &&
            (!filterCategory.value ||
                String(q.category_id ?? '') === filterCategory.value) &&
            (!filterText.value ||
                q.text.toLowerCase().includes(filterText.value.toLowerCase())),
    ),
);

function addQuestion(id: number): void {
    form.questions.push({ id, weight: 1 });
}

function move(i: number, by: number): void {
    const j = i + by;

    if (j < 0 || j >= form.questions.length) {
        return;
    }

    const list = [...form.questions];
    [list[i], list[j]] = [list[j], list[i]];
    form.questions = list;
}

function save(): void {
    form.put(update(props.assessment.id).url, { preserveScroll: true });
}

const deleting = ref(false);
const err = (k: string) =>
    (form.errors as Record<string, string | undefined>)[k];
const weights = [1, 2, 3, 4, 5].map((w) => ({
    value: String(w),
    label: `× ${w}`,
}));
</script>

<template>
    <Head :title="`Assessment: ${assessment.country}`" />
    <PageHeader
        eyebrow="Skill migration"
        :title="`${flag(assessment.iso2)} ${assessment.country}`"
        :description="
            assessment.country_active
                ? undefined
                : 'This country is inactive, so the assessment is hidden from the website even when published.'
        "
    >
        <template #actions>
            <StatusBadge
                :label="form.status === 'published' ? 'Published' : 'Draft'"
                :tone="form.status === 'published' ? 'success' : 'neutral'"
            />
            <Button variant="outline" as-child>
                <a :href="assessment.url" target="_blank" rel="noopener"
                    ><AppIcon name="visibility" :size="16" />
                    {{
                        assessment.status === 'published' ? 'View' : 'Preview'
                    }}</a
                >
            </Button>
            <Button :disabled="form.processing" @click="save">Save</Button>
        </template>
    </PageHeader>

    <form
        class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]"
        @submit.prevent="save"
    >
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard title="Introduction page">
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Title"
                        required
                        :error="err('title')"
                        class="sm:col-span-2"
                    >
                        <Input
                            :id="id"
                            v-model="form.title"
                            maxlength="160"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Summary (search results and cards)"
                        :error="err('summary')"
                        class="sm:col-span-2"
                    >
                        <TextArea
                            :id="id"
                            v-model="form.summary"
                            :rows="2"
                            :maxlength="300"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Introduction"
                        :error="err('intro')"
                        class="sm:col-span-2"
                        hint="Explain the destination and add a note that this is an indicative self-check."
                    >
                        <RichTextEditor
                            :id="id"
                            v-model="form.intro"
                            :invalid="invalid"
                        />
                    </FormField>
                </div>
            </PanelCard>

            <PanelCard
                :title="`Questions (${form.questions.length})`"
                description="Order and weight for this country. A weight of × 2 makes a question count twice as much. Edit questions and answers in the Question bank."
            >
                <template #actions>
                    <Button variant="outline" size="sm" as-child
                        ><Link
                            :href="
                                questionsIndex({
                                    query: {
                                        assessment: String(assessment.id),
                                    },
                                })
                            "
                            >Question bank</Link
                        ></Button
                    >
                </template>
                <p v-if="err('questions')" class="mb-2 text-xs text-danger">
                    {{ err('questions') }}
                </p>
                <p
                    v-if="!form.questions.length"
                    class="py-4 text-center text-[13px] text-muted-foreground"
                >
                    No questions yet. Add some from the list below.
                </p>
                <ol class="flex flex-col gap-2">
                    <li
                        v-for="(q, i) in form.questions"
                        :key="q.id"
                        class="flex items-center gap-3 rounded-lg border border-border p-2.5"
                    >
                        <span
                            class="w-6 text-center text-[12px] font-semibold text-muted-foreground"
                            >{{ i + 1 }}</span
                        >
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-[13px] font-medium"
                                :class="
                                    !usable(bankById.get(q.id)) &&
                                    'text-muted-foreground line-through'
                                "
                            >
                                {{
                                    bankById.get(q.id)?.text ??
                                    'Deleted question'
                                }}
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                {{ bankById.get(q.id)?.category }} · up to
                                {{
                                    (bankById.get(q.id)?.max_points ?? 0) *
                                    q.weight
                                }}
                                points
                                <template v-if="!usable(bankById.get(q.id))">
                                    · not shown (inactive or fewer than two
                                    answers)</template
                                >
                            </p>
                        </div>
                        <div class="w-20">
                            <SelectInput
                                :model-value="String(q.weight)"
                                :options="weights"
                                label="Weight"
                                @update:model-value="
                                    (v) => (q.weight = Number(v))
                                "
                            />
                        </div>
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded hover:bg-muted"
                            :disabled="i === 0"
                            aria-label="Move up"
                            @click="move(i, -1)"
                        >
                            <AppIcon name="arrow_upward" :size="16" />
                        </button>
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded hover:bg-muted"
                            :disabled="i === form.questions.length - 1"
                            aria-label="Move down"
                            @click="move(i, 1)"
                        >
                            <AppIcon name="arrow_downward" :size="16" />
                        </button>
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded text-muted-foreground hover:bg-muted hover:text-danger"
                            aria-label="Remove from this country"
                            @click="form.questions.splice(i, 1)"
                        >
                            <AppIcon name="close" :size="16" />
                        </button>
                    </li>
                </ol>

                <div class="mt-5 rounded-lg bg-muted/50 p-3">
                    <p class="text-[13px] font-semibold">
                        Add from the question bank
                    </p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <SelectInput
                            v-model="filterCategory"
                            :options="
                                categories.map((c) => ({
                                    value: String(c.id),
                                    label: c.name,
                                }))
                            "
                            placeholder="All categories"
                            label="Category"
                        />
                        <Input
                            v-model="filterText"
                            placeholder="Search questions…"
                            aria-label="Search questions"
                        />
                    </div>
                    <ul class="mt-2 max-h-72 overflow-y-auto">
                        <li
                            v-for="q in available"
                            :key="q.id"
                            class="flex items-center gap-3 border-b border-border py-2 last:border-0"
                        >
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-[13px]"
                                    :class="
                                        !usable(q) && 'text-muted-foreground'
                                    "
                                >
                                    {{ q.text }}
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ q.category }} ·
                                    {{ q.options }} answers<template
                                        v-if="!q.is_active"
                                    >
                                        · inactive</template
                                    >
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="addQuestion(q.id)"
                                ><AppIcon name="add" :size="16" /> Add</Button
                            >
                        </li>
                        <li
                            v-if="!available.length"
                            class="py-3 text-center text-[12px] text-muted-foreground"
                        >
                            No more questions match. Create new ones in the
                            Question bank.
                        </li>
                    </ul>
                </div>
            </PanelCard>

            <PanelCard
                title="Recommendations"
                description="Shown on the result page. The visitor gets the one with the highest minimum their score reaches."
            >
                <template #actions>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="
                            form.bands.push({
                                min_percent: 0,
                                title: '',
                                text: '',
                            })
                        "
                        ><AppIcon name="add" :size="16" /> Add</Button
                    >
                </template>
                <p
                    v-if="!form.bands.length"
                    class="py-3 text-center text-[13px] text-muted-foreground"
                >
                    No recommendations. Add at least one with a minimum of 0%.
                </p>
                <div class="flex flex-col gap-3">
                    <div
                        v-for="(b, i) in form.bands"
                        :key="i"
                        class="grid gap-2 rounded-lg border border-border p-3 sm:grid-cols-[110px_1fr_auto]"
                    >
                        <FormField
                            v-slot="{ id }"
                            label="From score (%)"
                            :error="err(`bands.${i}.min_percent`)"
                        >
                            <Input
                                :id="id"
                                v-model.number="b.min_percent"
                                type="number"
                                min="0"
                                max="100"
                            />
                        </FormField>
                        <FormField
                            v-slot="{ id }"
                            label="Title"
                            :error="err(`bands.${i}.title`)"
                        >
                            <Input :id="id" v-model="b.title" maxlength="120" />
                        </FormField>
                        <button
                            type="button"
                            class="mt-6 grid size-9 place-items-center self-start rounded text-muted-foreground hover:bg-muted hover:text-danger"
                            aria-label="Remove recommendation"
                            @click="form.bands.splice(i, 1)"
                        >
                            <AppIcon name="delete" :size="18" />
                        </button>
                        <FormField
                            v-slot="{ id }"
                            label="Advice"
                            :error="err(`bands.${i}.text`)"
                            class="sm:col-span-3"
                        >
                            <TextArea
                                :id="id"
                                v-model="b.text"
                                :rows="2"
                                :maxlength="1000"
                            />
                        </FormField>
                    </div>
                </div>
            </PanelCard>
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard title="Publishing">
                <div class="flex flex-col gap-3.5">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Status"
                        :error="err('status')"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.status"
                            :options="[
                                { value: 'draft', label: 'Draft (hidden)' },
                                { value: 'published', label: 'Published' },
                            ]"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Web address"
                        required
                        :error="err('slug')"
                    >
                        <div
                            class="flex items-center gap-1 text-[13px] text-muted-foreground"
                        >
                            <span>/skill-migration/</span
                            ><Input
                                :id="id"
                                v-model="form.slug"
                                maxlength="60"
                                :aria-invalid="invalid"
                            />
                        </div>
                    </FormField>
                    <p class="text-[12px] text-muted-foreground">
                        Preview works for drafts too. Test runs never create
                        leads.
                    </p>
                </div>
            </PanelCard>
            <PanelCard title="Scoring">
                <div class="flex flex-col gap-3.5">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Pass mark (%)"
                        required
                        :error="err('pass_percentage')"
                        hint="Score at or above this counts as meeting the guide."
                    >
                        <Input
                            :id="id"
                            v-model.number="form.pass_percentage"
                            type="number"
                            min="1"
                            max="100"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Estimated minutes"
                        :error="err('minutes')"
                    >
                        <Input
                            :id="id"
                            v-model.number="form.minutes"
                            type="number"
                            min="1"
                            max="120"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <p class="text-[12px] text-muted-foreground">
                        Maximum score: {{ totalPoints }} points. A visitor’s
                        result is the share of these points their answers earn.
                    </p>
                </div>
            </PanelCard>
            <PanelCard>
                <Button
                    type="button"
                    variant="outline"
                    class="w-full text-danger"
                    @click="deleting = true"
                    ><AppIcon name="delete" :size="16" /> Delete
                    assessment</Button
                >
            </PanelCard>
        </div>
    </form>

    <ConfirmDialog
        :open="deleting"
        title="Delete this assessment?"
        :description="`The ${assessment.country} assessment will be removed from the website. Past submissions are kept.`"
        confirm-label="Delete"
        destructive
        @update:open="(v: boolean) => (deleting = v)"
        @confirm="router.delete(destroy(assessment.id).url)"
    />
</template>
