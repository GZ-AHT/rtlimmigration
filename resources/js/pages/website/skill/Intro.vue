<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import CountryCombobox from '@/components/website/CountryCombobox.vue';
import type { CountryChoice } from '@/components/website/CountryCombobox.vue';
import FormField from '@/components/website/FormField.vue';
import SeoHead from '@/components/website/SeoHead.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import SkillProgress from '@/components/website/SkillProgress.vue';
import { fieldClass } from '@/components/website/form';
import { flag } from '@/lib/flag';
import { start } from '@/routes/skill-migration';
import type { Seo } from '@/types/website';

/** A country's skill migration assessment: introduction, then contact details to start. */
const props = defineProps<{
    assessment: CountryChoice & {
        title: string;
        summary: string;
        intro: string;
        pass_percentage: number;
        minutes: number | null;
        questions: number;
        categories: string[];
        preview: boolean;
    };
    countries: CountryChoice[];
    seo: Seo;
}>();

const starting = ref(false);
const form = useForm({
    name: '',
    email: '',
    phone: '',
    consent: false,
    website: '',
});
const switchTo = ref(props.assessment.slug);
const switchQuery = ref('');

function begin(): void {
    form.submit(start(props.assessment.slug), { preserveScroll: true });
}

function change(): void {
    const target = props.countries.find((c) => c.slug === switchTo.value);

    if (target && target.slug !== props.assessment.slug) {
        router.visit(target.url);
    }
}

const err = (k: string) =>
    (form.errors as Record<string, string | undefined>)[k];
</script>

<template>
    <SeoHead :seo="seo" />
    <div v-if="assessment.preview" class="bg-site-ink text-[13px] text-white">
        <div class="site-container py-2">
            Preview: this assessment isn’t published, so visitors can’t see it.
            Test runs don’t create leads.
        </div>
    </div>

    <section class="bg-site-blush-bg">
        <div class="site-container pt-6 pb-12 lg:pt-[27px] lg:pb-14">
            <SkillProgress
                :step="2"
                :country="assessment.country"
                :iso2="assessment.iso2"
                :slug="assessment.slug"
            />
            <div
                class="mt-6 flex flex-col items-start gap-3 sm:flex-row sm:gap-4"
            >
                <span
                    class="text-[44px] leading-none sm:text-[52px]"
                    aria-hidden="true"
                    >{{ flag(assessment.iso2) }}</span
                >
                <div>
                    <h1
                        class="text-[26px] leading-[1.15] font-bold tracking-[-0.02em] break-words sm:text-[36px] sm:leading-[44px] md:text-[48px] md:leading-[54px]"
                    >
                        {{ assessment.title }}
                    </h1>
                    <p
                        v-if="assessment.summary"
                        class="mt-4 max-w-[720px] text-[17px] leading-6 text-site-muted"
                    >
                        {{ assessment.summary }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 lg:py-[60px]">
        <div
            class="site-container grid gap-10 lg:grid-cols-[minmax(0,1fr)_420px] lg:gap-14"
        >
            <div>
                <dl class="grid grid-cols-3 gap-3">
                    <div class="rounded-[14px] bg-site-sand p-4">
                        <dt
                            class="text-[11px] font-semibold text-site-muted uppercase"
                        >
                            Questions
                        </dt>
                        <dd
                            class="mt-1 text-[20px] font-bold whitespace-nowrap text-site-ink sm:text-[26px]"
                        >
                            {{ assessment.questions }}
                        </dd>
                    </div>
                    <div class="rounded-[14px] bg-site-sand p-4">
                        <dt
                            class="text-[11px] font-semibold text-site-muted uppercase"
                        >
                            Time
                        </dt>
                        <dd
                            class="mt-1 text-[20px] font-bold whitespace-nowrap text-site-ink sm:text-[26px]"
                        >
                            {{
                                assessment.minutes
                                    ? `~${assessment.minutes} min`
                                    : '—'
                            }}
                        </dd>
                    </div>
                    <div class="rounded-[14px] bg-site-sand p-4">
                        <dt
                            class="text-[11px] font-semibold text-site-muted uppercase"
                        >
                            Guide score
                        </dt>
                        <dd
                            class="mt-1 text-[20px] font-bold whitespace-nowrap text-site-red sm:text-[26px]"
                        >
                            {{ assessment.pass_percentage }}%
                        </dd>
                    </div>
                </dl>

                <!-- eslint-disable-next-line vue/no-v-html -- sanitised server-side by App\Support\Content\RichText -->
                <div
                    v-if="assessment.intro"
                    class="site-prose mt-8"
                    v-html="assessment.intro"
                />

                <div v-if="assessment.categories.length" class="mt-8">
                    <p
                        class="text-[11px] font-semibold text-site-red uppercase"
                    >
                        What we’ll ask about
                    </p>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        <li
                            v-for="c in assessment.categories"
                            :key="c"
                            class="rounded-full border border-site-line px-3 py-1.5 text-[13px] font-medium text-site-ink"
                        >
                            {{ c }}
                        </li>
                    </ul>
                </div>

                <form
                    v-if="countries.length > 1"
                    class="mt-10 rounded-[16px] border border-site-line p-5"
                    @submit.prevent="change"
                >
                    <p class="text-[15px] font-semibold text-site-ink">
                        Looking at a different country?
                    </p>
                    <p class="mt-1 text-[13px] text-site-muted">
                        Each destination has its own questions and guide score.
                    </p>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <CountryCombobox
                            id="skill-switch"
                            v-model="switchTo"
                            v-model:query="switchQuery"
                            :countries="countries"
                            class="flex-1"
                        />
                        <button
                            type="submit"
                            class="h-[54px] rounded-[10px] border border-site-ink px-5 text-[14px] font-semibold text-site-ink hover:bg-site-ink hover:text-white"
                        >
                            Change country
                        </button>
                    </div>
                </form>
            </div>

            <aside class="lg:sticky lg:top-36 lg:self-start">
                <div class="rounded-[20px] bg-site-ink p-6 text-white sm:p-8">
                    <template v-if="assessment.questions === 0">
                        <p class="text-[22px] leading-7 font-bold">
                            Coming soon
                        </p>
                        <p
                            class="mt-3 text-[14px] leading-[21px] text-site-on-dark"
                        >
                            The {{ assessment.country }} assessment isn’t ready
                            yet. Choose another country, or talk to an adviser
                            now.
                        </p>
                        <a
                            href="/contact"
                            class="mt-6 flex h-[50px] items-center justify-center gap-2.5 rounded-site-btn bg-site-red font-semibold text-white"
                            >Talk to an adviser <SiteArrow
                        /></a>
                    </template>
                    <template v-else-if="!starting">
                        <p
                            class="text-[11px] font-semibold text-site-coral uppercase"
                        >
                            Free ·
                            {{
                                assessment.minutes
                                    ? `about ${assessment.minutes} minutes`
                                    : 'quick'
                            }}
                        </p>
                        <p class="mt-3 text-[24px] leading-[30px] font-bold">
                            Ready to check your profile for
                            {{ assessment.country }}?
                        </p>
                        <p
                            class="mt-3 text-[14px] leading-[21px] text-site-on-dark"
                        >
                            Answer {{ assessment.questions }} multiple-choice
                            questions. You’ll see your result straight away.
                        </p>
                        <button
                            type="button"
                            class="mt-6 flex h-[54px] w-full items-center justify-center gap-2.5 rounded-site-btn bg-site-red text-[15px] font-semibold hover:bg-site-red-dark"
                            @click="starting = true"
                        >
                            Start Assessment <SiteArrow glyph="→" />
                        </button>
                    </template>
                    <form v-else novalidate @submit.prevent="begin">
                        <p class="text-[22px] leading-7 font-bold">
                            A little about you
                        </p>
                        <p class="mt-2 text-[13px] text-site-on-dark">
                            So we can share your result and an adviser can
                            follow up.
                        </p>
                        <div
                            class="mt-5 flex flex-col gap-4 [&_label]:text-white"
                        >
                            <FormField
                                id="sa-name"
                                label="Full name *"
                                :error="err('name')"
                            >
                                <input
                                    id="sa-name"
                                    v-model="form.name"
                                    v-focus
                                    type="text"
                                    autocomplete="name"
                                    :class="fieldClass"
                                    :aria-invalid="!!err('name') || undefined"
                                />
                            </FormField>
                            <FormField
                                id="sa-email"
                                label="Email address *"
                                :error="err('email')"
                            >
                                <input
                                    id="sa-email"
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="email"
                                    :class="fieldClass"
                                    :aria-invalid="!!err('email') || undefined"
                                />
                            </FormField>
                            <FormField
                                id="sa-phone"
                                label="Phone / WhatsApp *"
                                :error="err('phone')"
                            >
                                <input
                                    id="sa-phone"
                                    v-model="form.phone"
                                    type="tel"
                                    autocomplete="tel"
                                    placeholder="Include country code"
                                    :class="fieldClass"
                                    :aria-invalid="!!err('phone') || undefined"
                                />
                            </FormField>
                        </div>
                        <div class="absolute -left-[9999px]" aria-hidden="true">
                            <input
                                v-model="form.website"
                                type="text"
                                tabindex="-1"
                                autocomplete="off"
                            />
                        </div>
                        <label
                            class="mt-4 flex items-start gap-3 text-[12px] leading-[18px] text-site-on-dark"
                        >
                            <input
                                v-model="form.consent"
                                type="checkbox"
                                class="mt-0.5 size-4 shrink-0 accent-site-red"
                            />
                            I agree that RTL may contact me about my result. I
                            have read the Privacy Policy.
                        </label>
                        <p
                            v-if="err('consent')"
                            class="mt-1.5 text-xs text-site-blush"
                        >
                            {{ err('consent') }}
                        </p>
                        <p
                            v-if="err('assessment')"
                            class="mt-3 text-xs text-site-blush"
                            role="alert"
                        >
                            {{ err('assessment') }}
                        </p>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="mt-5 flex h-[54px] w-full items-center justify-center gap-2.5 rounded-site-btn bg-site-red text-[15px] font-semibold hover:bg-site-red-dark disabled:opacity-60"
                        >
                            {{
                                form.processing
                                    ? 'Starting…'
                                    : 'Begin the questions'
                            }}
                            <SiteArrow v-if="!form.processing" glyph="→" />
                        </button>
                    </form>
                </div>
            </aside>
        </div>
    </section>
</template>
