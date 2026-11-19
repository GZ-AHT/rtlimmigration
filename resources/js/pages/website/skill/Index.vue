<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import CountryCombobox from '@/components/website/CountryCombobox.vue';
import type { CountryChoice } from '@/components/website/CountryCombobox.vue';
import SeoHead from '@/components/website/SeoHead.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import SkillProgress from '@/components/website/SkillProgress.vue';
import { flag } from '@/lib/flag';
import type { Seo } from '@/types/website';

/** All published skill migration assessments, searchable by country. */
const props = defineProps<{
    countries: (CountryChoice & { title: string; summary: string })[];
    search: string;
    seo: Seo;
}>();

const slug = ref('');
const query = ref(props.search);

function go(): void {
    const chosen = props.countries.find((c) => c.slug === slug.value);

    if (chosen) {
        router.visit(chosen.url);
    } else {
        router.get(
            '/skill-migration',
            query.value.trim() ? { q: query.value.trim() } : {},
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <SeoHead :seo="seo" />
    <section class="bg-site-blush-bg">
        <div class="site-container pt-6 pb-12 lg:pt-[27px] lg:pb-14">
            <SkillProgress :step="1" />
            <p class="mt-8 text-[11px] font-semibold text-site-red uppercase">
                Skill migration assessment
            </p>
            <h1
                class="mt-4 text-[28px] leading-[1.12] font-bold tracking-[-0.02em] break-words sm:text-[44px] sm:leading-[50px] md:text-[56px] md:leading-[58px]"
            >
                Where can your skills take you?
            </h1>
            <p
                class="mt-5 max-w-[640px] text-[16px] leading-6 text-site-muted sm:text-[17px]"
            >
                Choose a destination. Each country has its own questions and
                scoring, so you’ll see how your profile compares for that
                country.
            </p>
            <form
                class="mt-8 flex max-w-[720px] flex-col gap-3 sm:flex-row"
                role="search"
                @submit.prevent="go"
            >
                <CountryCombobox
                    id="skill-index-search"
                    @enter="go"
                    v-model="slug"
                    v-model:query="query"
                    :countries="countries"
                    placeholder="Search your skill migration destination..."
                    class="flex-1"
                />
                <button
                    type="submit"
                    class="flex h-[54px] w-full shrink-0 items-center justify-center gap-2.5 rounded-[10px] bg-site-red px-5 text-[14px] font-semibold text-white hover:bg-site-red-dark sm:w-auto sm:text-[15px]"
                >
                    Find Skill Migration Assessment <SiteArrow glyph="→" />
                </button>
            </form>
        </div>
    </section>

    <section class="py-14 lg:py-[60px]">
        <div class="site-container">
            <div
                v-if="!countries.length"
                class="rounded-[20px] bg-site-sand p-8"
            >
                <p class="text-[17px] font-semibold">
                    {{
                        search
                            ? `We don’t have an assessment for “${search}” yet.`
                            : 'Assessments are on their way.'
                    }}
                </p>
                <p class="mt-2 text-site-muted">
                    Talk to an adviser about skilled migration to any country.
                </p>
                <div
                    class="mt-4 flex flex-wrap gap-4 text-[14px] font-semibold"
                >
                    <Link
                        v-if="search"
                        href="/skill-migration"
                        class="text-site-red hover:underline"
                        >See all destinations</Link
                    >
                    <Link href="/contact" class="text-site-red hover:underline"
                        >Talk to an adviser</Link
                    >
                </div>
            </div>
            <ul v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <li v-for="c in countries" :key="c.slug">
                    <Link
                        :href="c.url"
                        class="group flex h-full flex-col rounded-[20px] bg-site-sand p-6 transition-shadow hover:shadow-[0_12px_30px_rgba(0,0,0,0.1)]"
                    >
                        <span
                            class="text-[40px] leading-none"
                            aria-hidden="true"
                            >{{ flag(c.iso2) }}</span
                        >
                        <span
                            class="mt-4 text-[11px] font-semibold text-site-red uppercase"
                            >{{ c.country }}</span
                        >
                        <span
                            class="mt-2 text-[21px] leading-[27px] font-semibold text-site-ink"
                            >{{ c.title }}</span
                        >
                        <span
                            class="mt-3 text-[14px] leading-[21px] text-site-muted"
                            >{{ c.summary }}</span
                        >
                        <span
                            class="mt-auto inline-flex items-center gap-2 pt-5 text-[14px] font-semibold text-site-red"
                        >
                            View assessment
                            <SiteArrow
                                glyph="→"
                                class="transition-transform group-hover:translate-x-0.5"
                            />
                        </span>
                    </Link>
                </li>
            </ul>
        </div>
    </section>
</template>
