<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import SeoHead from '@/components/website/SeoHead.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import SkillProgress from '@/components/website/SkillProgress.vue';
import { submit as submitRoute } from '@/routes/skill-migration';
import type { Seo } from '@/types/website';

/**
 * The country's questions, one at a time. Answers are kept in this tab
 * (survives a reload) and sent once; scoring happens on the server.
 */
type Question = {
    id: number;
    text: string;
    help: string | null;
    category: string;
    options: { id: number; label: string }[];
};
const props = defineProps<{
    submission: {
        token: string;
        name: string;
        country: string;
        iso2: string;
        title: string;
        slug: string | null;
        preview: boolean;
    };
    questions: Question[];
    seo: Seo;
}>();

const KEY = `skill-answers-${props.submission.token}`;
const answers = ref<Record<number, number>>({});
const index = ref(0);
const sending = ref(false);
const error = ref('');

onMounted(() => {
    try {
        answers.value = JSON.parse(
            sessionStorage.getItem(KEY) ?? '{}',
        ) as Record<number, number>;
        const firstOpen = props.questions.findIndex(
            (q) => !answers.value[q.id],
        );
        index.value = firstOpen === -1 ? props.questions.length - 1 : firstOpen;
    } catch {
        // Storage unavailable: start from the first question.
    }
});

watch(
    answers,
    (v) => {
        try {
            sessionStorage.setItem(KEY, JSON.stringify(v));
        } catch {
            // Private mode: answers just won't survive a reload.
        }
    },
    { deep: true },
);

const question = computed(() => props.questions[index.value]);
const answered = computed(
    () => props.questions.filter((q) => answers.value[q.id]).length,
);
const last = computed(() => index.value === props.questions.length - 1);
const percent = computed(() =>
    Math.round((answered.value / props.questions.length) * 100),
);

function pick(optionId: number): void {
    answers.value = { ...answers.value, [question.value.id]: optionId };
    error.value = '';
}

function next(): void {
    if (!answers.value[question.value.id]) {
        error.value = 'Choose an answer to continue.';

        return;
    }

    if (last.value) {
        send();
    } else {
        index.value++;
    }
}

function send(): void {
    const missing = props.questions.findIndex((q) => !answers.value[q.id]);

    if (missing !== -1) {
        index.value = missing;
        error.value = 'Please answer this question.';

        return;
    }

    router.post(
        submitRoute(props.submission.token).url,
        { answers: answers.value },
        {
            onStart: () => (sending.value = true),
            onFinish: () => (sending.value = false),
            onSuccess: () => {
                try {
                    sessionStorage.removeItem(KEY);
                } catch {
                    // ignore
                }
            },
            onError: (e) =>
                (error.value =
                    e.answers ?? 'Something went wrong. Please try again.'),
        },
    );
}

function key(event: KeyboardEvent, i: number): void {
    const options = question.value.options;

    if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
        event.preventDefault();
        pick(options[(i + 1) % options.length].id);
        (event.currentTarget as HTMLElement).parentElement
            ?.querySelectorAll<HTMLElement>('[role=radio]')
            [(i + 1) % options.length]?.focus();
    } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
        event.preventDefault();
        const j = (i - 1 + options.length) % options.length;
        pick(options[j].id);
        (event.currentTarget as HTMLElement).parentElement
            ?.querySelectorAll<HTMLElement>('[role=radio]')
            [j]?.focus();
    }
}
</script>

<template>
    <SeoHead :seo="seo" />
    <div v-if="submission.preview" class="bg-site-ink text-[13px] text-white">
        <div class="site-container py-2">
            Preview run: no lead will be created.
        </div>
    </div>

    <section class="min-h-[70vh] bg-site-blush-bg">
        <div class="site-container pt-6 pb-16 lg:pt-[27px]">
            <SkillProgress
                :step="3"
                :country="submission.country"
                :iso2="submission.iso2"
                :slug="submission.slug"
            />

            <div class="mx-auto mt-8 max-w-[760px]">
                <div
                    class="flex items-center justify-between text-[13px] font-medium text-site-muted"
                >
                    <span
                        >Question {{ index + 1 }} of
                        {{ questions.length }}</span
                    >
                    <span>{{ percent }}% complete</span>
                </div>
                <div
                    class="mt-2 h-2 overflow-hidden rounded-full bg-white"
                    role="progressbar"
                    :aria-valuenow="percent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="Progress"
                >
                    <div
                        class="h-full rounded-full bg-site-red transition-all"
                        :style="{ width: `${percent}%` }"
                    />
                </div>

                <div
                    v-if="question"
                    class="mt-6 rounded-[20px] bg-white p-6 shadow-[0_12px_30px_rgba(0,0,0,0.06)] sm:p-8"
                >
                    <p
                        class="text-[11px] font-semibold text-site-red uppercase"
                    >
                        {{ question.category }}
                    </p>
                    <h1
                        id="question-text"
                        class="mt-3 text-[22px] leading-[30px] font-bold text-site-ink sm:text-[26px] sm:leading-[34px]"
                    >
                        {{ question.text }}
                    </h1>
                    <p
                        v-if="question.help"
                        class="mt-2 text-[14px] text-site-muted"
                    >
                        {{ question.help }}
                    </p>

                    <div
                        class="mt-6 flex flex-col gap-3"
                        role="radiogroup"
                        aria-labelledby="question-text"
                    >
                        <button
                            v-for="(o, i) in question.options"
                            :key="o.id"
                            type="button"
                            role="radio"
                            :aria-checked="answers[question.id] === o.id"
                            :tabindex="
                                answers[question.id] === o.id ||
                                (!answers[question.id] && i === 0)
                                    ? 0
                                    : -1
                            "
                            class="flex min-h-[56px] items-center gap-4 rounded-[12px] border-2 px-5 py-3 text-left text-[15px] font-medium transition-colors focus-visible:ring-4 focus-visible:ring-site-red/20 focus-visible:outline-none"
                            :class="
                                answers[question.id] === o.id
                                    ? 'border-site-red bg-site-blush-bg text-site-ink'
                                    : 'border-site-line text-site-ink hover:border-site-ink'
                            "
                            @click="pick(o.id)"
                            @keydown="key($event, i)"
                        >
                            <span
                                class="grid size-5 shrink-0 place-items-center rounded-full border-2"
                                :class="
                                    answers[question.id] === o.id
                                        ? 'border-site-red'
                                        : 'border-site-field-border'
                                "
                                aria-hidden="true"
                            >
                                <span
                                    v-if="answers[question.id] === o.id"
                                    class="size-2.5 rounded-full bg-site-red"
                                />
                            </span>
                            {{ o.label }}
                        </button>
                    </div>

                    <p
                        v-if="error"
                        class="mt-4 text-[14px] text-site-red"
                        role="alert"
                    >
                        {{ error }}
                    </p>

                    <div class="mt-8 flex items-center justify-between gap-3">
                        <button
                            type="button"
                            class="inline-flex h-[50px] items-center gap-2 rounded-site-btn border border-site-line px-5 text-[14px] font-semibold text-site-ink hover:border-site-ink disabled:invisible"
                            :disabled="index === 0"
                            @click="index--"
                        >
                            <SiteArrow glyph="←" /> Back
                        </button>
                        <button
                            type="button"
                            :disabled="sending"
                            class="inline-flex h-[50px] items-center gap-2.5 rounded-site-btn bg-site-red px-6 text-[15px] font-semibold text-white hover:bg-site-red-dark disabled:opacity-60"
                            @click="next"
                        >
                            <template v-if="sending">Calculating…</template>
                            <template v-else
                                >{{ last ? 'See my result' : 'Next' }}
                                <SiteArrow glyph="→"
                            /></template>
                        </button>
                    </div>
                </div>
                <p class="mt-4 text-center text-[12px] text-site-muted">
                    {{ submission.title }} · Your answers are only used for this
                    assessment.
                </p>
            </div>
        </div>
    </section>
</template>
