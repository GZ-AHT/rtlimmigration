<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CountryCombobox from '@/components/website/CountryCombobox.vue';
import type { CountryChoice } from '@/components/website/CountryCombobox.vue';
import SeoHead from '@/components/website/SeoHead.vue';
import SiteButton from '@/components/website/SiteButton.vue';
import SkillProgress from '@/components/website/SkillProgress.vue';
import type { Seo } from '@/types/website';

/** The visitor's result: score against the country's guide, category scores and advice. */
const props = defineProps<{
    result: {
        name: string;
        country: string;
        iso2: string;
        title: string;
        slug: string | null;
        score: number;
        pass_percentage: number;
        passed: boolean;
        categories: { category: string; percent: number }[];
        band_title: string | null;
        band_text: string | null;
        preview: boolean;
    };
    countries: CountryChoice[];
    seo: Seo;
}>();

const score = computed(() => Math.round(props.result.score));
const firstName = computed(() => props.result.name.split(' ')[0]);
const other = ref('');
const otherQuery = ref('');
const others = computed(() =>
    props.countries.filter((c) => c.slug !== props.result.slug),
);

function tryOther(): void {
    const c = others.value.find((x) => x.slug === other.value);

    if (c) {
        router.visit(c.url);
    }
}
</script>

<template>
    <SeoHead :seo="seo" />
    <section class="bg-site-blush-bg">
        <div class="site-container pt-6 pb-14 lg:pt-[27px]">
            <SkillProgress
                :step="4"
                :country="result.country"
                :iso2="result.iso2"
                :slug="result.slug"
            />

            <div
                class="mt-8 grid gap-8 lg:grid-cols-[360px_1fr] lg:items-center lg:gap-14"
            >
                <div class="mx-auto flex flex-col items-center">
                    <div
                        class="grid size-[220px] place-items-center rounded-full"
                        :style="{
                            background: `conic-gradient(var(--color-site-red) ${score * 3.6}deg, #ffffff 0deg)`,
                        }"
                        role="img"
                        :aria-label="`Your score: ${score}%`"
                    >
                        <div
                            class="grid size-[176px] place-items-center rounded-full bg-site-blush-bg text-center"
                        >
                            <div>
                                <p
                                    class="text-[54px] leading-none font-bold tracking-[-2px] text-site-ink"
                                >
                                    {{ score }}%
                                </p>
                                <p
                                    class="mt-2 text-[12px] font-semibold text-site-muted uppercase"
                                >
                                    Guide: {{ result.pass_percentage }}%
                                </p>
                            </div>
                        </div>
                    </div>
                    <p
                        class="mt-5 rounded-full px-4 py-1.5 text-[13px] font-semibold"
                        :class="
                            result.passed
                                ? 'bg-[#e6f4ea] text-[#137333]'
                                : 'bg-[#fdecea] text-site-red'
                        "
                    >
                        {{
                            result.passed
                                ? 'Meets our guide score'
                                : 'Below our guide score for now'
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-[11px] font-semibold text-site-red uppercase"
                    >
                        Your result, {{ firstName }}
                    </p>
                    <h1
                        class="mt-3 text-[32px] leading-[1.1] font-bold tracking-[-0.02em] sm:text-[44px] sm:leading-[49px]"
                    >
                        {{
                            result.band_title ||
                            (result.passed
                                ? 'Promising profile'
                                : 'Needs a closer look')
                        }}
                    </h1>
                    <p
                        v-if="result.band_text"
                        class="mt-5 max-w-[640px] text-[17px] leading-[26px] text-site-muted"
                    >
                        {{ result.band_text }}
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <SiteButton href="/contact" size="lg"
                            >Book a consultation</SiteButton
                        >
                        <Link
                            v-if="result.slug"
                            :href="`/skill-migration/${result.slug}`"
                            class="text-[15px] font-semibold text-site-ink hover:text-site-red"
                            >Take it again</Link
                        >
                    </div>
                    <p
                        v-if="!result.preview"
                        class="mt-4 text-[13px] text-site-muted"
                    >
                        An RTL adviser may contact you about your result.
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
                <h2 class="text-[26px] font-bold">
                    Your profile, area by area
                </h2>
                <ul class="mt-6 flex flex-col gap-5">
                    <li v-for="c in result.categories" :key="c.category">
                        <div
                            class="flex justify-between text-[15px] font-semibold text-site-ink"
                        >
                            <span>{{ c.category }}</span
                            ><span>{{ Math.round(c.percent) }}%</span>
                        </div>
                        <div
                            class="mt-2 h-2.5 overflow-hidden rounded-full bg-site-sand"
                        >
                            <div
                                class="h-full rounded-full"
                                :class="
                                    c.percent >= result.pass_percentage
                                        ? 'bg-site-ink'
                                        : 'bg-site-red'
                                "
                                :style="{ width: `${c.percent}%` }"
                            />
                        </div>
                    </li>
                </ul>
                <p
                    class="mt-8 rounded-[14px] bg-site-sand p-4 text-[13px] leading-5 text-site-muted"
                >
                    This is an indicative self-check for {{ result.country }},
                    not an official points test. Visa decisions are made by the
                    immigration authorities.
                </p>
            </div>
            <aside
                v-if="others.length"
                class="self-start rounded-[20px] border border-site-line p-6"
            >
                <p class="text-[17px] font-semibold text-site-ink">
                    Compare another destination
                </p>
                <p class="mt-1 text-[13px] text-site-muted">
                    Each country has its own questions and guide score.
                </p>
                <form
                    class="mt-4 flex flex-col gap-3"
                    @submit.prevent="tryOther"
                >
                    <CountryCombobox
                        id="skill-other"
                        v-model="other"
                        v-model:query="otherQuery"
                        :countries="others"
                    />
                    <button
                        type="submit"
                        class="h-[50px] rounded-[10px] bg-site-ink px-5 text-[14px] font-semibold text-white hover:bg-black"
                    >
                        Start that assessment
                    </button>
                </form>
            </aside>
        </div>
    </section>
</template>
