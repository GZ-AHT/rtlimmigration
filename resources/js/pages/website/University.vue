<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SeoHead from '@/components/website/SeoHead.vue';
import SiteButton from '@/components/website/SiteButton.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import type {
    CourseCard,
    Seo,
    SiteImage,
    UniversityCard,
} from '@/types/website';

/**
 * University profile with its courses (REQ §15, §21.8). Not in Figma:
 * built from the About page header and the article card style.
 */
type Course = CourseCard & {
    intakes: string[];
    deadline: string | null;
    scholarship: string | null;
    requirements: string | null;
};
const props = defineProps<{
    university: UniversityCard & {
        type: string | null;
        website: string | null;
        address: string | null;
        established: number | null;
        ranking: number | null;
        description: string;
        admission_requirements: string;
        scholarships: string;
        gallery: SiteImage[];
        courses: Course[];
    };
    seo: Seo;
}>();

const levels = computed(() => [
    ...new Set(props.university.courses.map((c) => c.level)),
]);
const level = ref('');
const courses = computed(() =>
    level.value
        ? props.university.courses.filter((c) => c.level === level.value)
        : props.university.courses,
);
const facts = computed(() =>
    [
        [
            'Location',
            [props.university.city, props.university.country]
                .filter(Boolean)
                .join(', '),
        ],
        ['Type', props.university.type],
        ['Established', props.university.established],
        [
            'Ranking',
            props.university.ranking ? `#${props.university.ranking}` : null,
        ],
    ].filter(([, v]) => v),
);
</script>

<template>
    <SeoHead :seo="seo" />

    <header class="bg-site-blush-bg">
        <div
            class="site-container grid gap-10 pt-8 pb-12 lg:grid-cols-[1fr_440px] lg:pt-[43px]"
        >
            <div>
                <nav aria-label="Breadcrumb">
                    <ol
                        class="flex flex-wrap gap-3 text-[11px] leading-[17px] font-semibold text-site-muted uppercase"
                    >
                        <li>
                            <Link href="/" class="hover:text-site-red"
                                >Home</Link
                            >
                        </li>
                        <li aria-hidden="true">/</li>
                        <li>
                            <Link
                                href="/universities"
                                class="hover:text-site-red"
                                >Universities</Link
                            >
                        </li>
                    </ol>
                </nav>
                <div class="mt-8 flex items-center gap-4">
                    <span
                        v-if="university.logo"
                        class="grid size-16 shrink-0 place-items-center rounded-[14px] bg-white p-2"
                    >
                        <SiteImg
                            :image="university.logo"
                            :alt="''"
                            class="max-h-full w-auto object-contain"
                        />
                    </span>
                    <p
                        class="text-[11px] leading-[17px] font-semibold text-site-red uppercase"
                    >
                        {{
                            [university.city, university.country]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </p>
                </div>
                <h1
                    class="mt-5 text-[28px] leading-[1.15] font-bold tracking-[-0.03em] break-words text-site-ink sm:text-[42px] sm:leading-[48px] md:text-[52px] md:leading-[58px]"
                >
                    {{ university.name }}
                </h1>
                <p
                    v-if="university.summary"
                    class="mt-6 max-w-[670px] text-[18px] leading-[1.6] text-site-muted"
                >
                    {{ university.summary }}
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-6">
                    <SiteButton href="/contact">Talk to an adviser</SiteButton>
                    <a
                        v-if="university.website"
                        :href="university.website"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-[14px] font-semibold text-site-ink hover:text-site-red"
                        >Official website <SiteArrow class="ml-1"
                    /></a>
                </div>
            </div>
            <SiteImg
                v-if="university.banner"
                :image="university.banner"
                eager
                class="aspect-[4/3] w-full rounded-[24px]"
            />
        </div>
    </header>

    <div
        class="site-container grid gap-12 py-12 lg:grid-cols-[1fr_320px] lg:py-16"
    >
        <div class="min-w-0">
            <!-- eslint-disable-next-line vue/no-v-html -- sanitised server-side by App\Support\Content\RichText -->
            <div
                v-if="university.description"
                class="site-prose"
                v-html="university.description"
            />

            <section class="mt-12" aria-labelledby="courses-heading">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 id="courses-heading" class="text-[28px] font-bold">
                        Courses
                        <span class="text-site-muted"
                            >({{ courses.length }})</span
                        >
                    </h2>
                    <div
                        v-if="levels.length > 1"
                        class="flex flex-wrap gap-2"
                        role="group"
                        aria-label="Filter by level"
                    >
                        <button
                            v-for="l in ['', ...levels]"
                            :key="l"
                            type="button"
                            class="h-9 rounded-full border px-3 text-[13px] font-medium"
                            :class="
                                level === l
                                    ? 'border-site-ink bg-site-ink text-white'
                                    : 'border-site-line text-site-ink hover:border-site-ink'
                            "
                            :aria-pressed="level === l"
                            @click="level = l"
                        >
                            {{ l || 'All levels' }}
                        </button>
                    </div>
                </div>
                <p
                    v-if="!courses.length"
                    class="mt-6 rounded-[16px] bg-site-sand p-6 text-site-muted"
                >
                    Course details will be added soon. Ask an adviser about
                    current programmes.
                </p>
                <ul v-else class="mt-6 flex flex-col gap-3">
                    <li
                        v-for="c in courses"
                        :key="c.id"
                        class="rounded-[16px] bg-site-sand p-5"
                    >
                        <details class="group">
                            <summary
                                class="flex cursor-pointer list-none items-start justify-between gap-4"
                            >
                                <span>
                                    <span
                                        class="block text-[11px] font-semibold text-site-red uppercase"
                                        >{{ c.level
                                        }}<template v-if="c.duration">
                                            · {{ c.duration }}</template
                                        ></span
                                    >
                                    <span
                                        class="mt-1 block text-[17px] leading-6 font-semibold text-site-ink"
                                        >{{ c.name }}</span
                                    >
                                    <span
                                        class="mt-1 block text-[13px] text-site-muted"
                                    >
                                        <template v-if="c.tuition"
                                            >Tuition {{ c.tuition }}</template
                                        >
                                        <template v-if="c.intakes.length">
                                            · Intakes:
                                            {{ c.intakes.join(', ') }}</template
                                        >
                                    </span>
                                </span>
                                <span
                                    aria-hidden="true"
                                    class="text-[22px] text-site-muted group-open:text-site-red"
                                >
                                    <span class="group-open:hidden">+</span
                                    ><span class="hidden group-open:inline"
                                        >−</span
                                    >
                                </span>
                            </summary>
                            <dl
                                class="mt-4 grid gap-3 border-t border-site-line pt-4 text-[14px] sm:grid-cols-2"
                            >
                                <div v-if="c.subject">
                                    <dt class="font-semibold text-site-ink">
                                        Subject
                                    </dt>
                                    <dd class="text-site-muted">
                                        {{ c.subject }}
                                    </dd>
                                </div>
                                <div v-if="c.deadline">
                                    <dt class="font-semibold text-site-ink">
                                        Application deadline
                                    </dt>
                                    <dd class="text-site-muted">
                                        {{ c.deadline }}
                                    </dd>
                                </div>
                                <div
                                    v-if="c.requirements"
                                    class="sm:col-span-2"
                                >
                                    <dt class="font-semibold text-site-ink">
                                        Requirements
                                    </dt>
                                    <dd
                                        class="whitespace-pre-line text-site-muted"
                                    >
                                        {{ c.requirements }}
                                    </dd>
                                </div>
                                <div v-if="c.scholarship" class="sm:col-span-2">
                                    <dt class="font-semibold text-site-ink">
                                        Scholarships
                                    </dt>
                                    <dd
                                        class="whitespace-pre-line text-site-muted"
                                    >
                                        {{ c.scholarship }}
                                    </dd>
                                </div>
                            </dl>
                        </details>
                    </li>
                </ul>
            </section>

            <section v-if="university.admission_requirements" class="mt-12">
                <h2 class="text-[24px] font-bold">Admission requirements</h2>
                <p
                    class="mt-4 text-[16px] leading-[1.7] whitespace-pre-line text-site-muted"
                >
                    {{ university.admission_requirements }}
                </p>
            </section>
            <section v-if="university.scholarships" class="mt-12">
                <h2 class="text-[24px] font-bold">Scholarships</h2>
                <p
                    class="mt-4 text-[16px] leading-[1.7] whitespace-pre-line text-site-muted"
                >
                    {{ university.scholarships }}
                </p>
            </section>
            <section v-if="university.gallery.length" class="mt-12">
                <h2 class="text-[24px] font-bold">Gallery</h2>
                <ul
                    class="-mx-4 mt-4 no-scrollbar flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0 sm:pb-0"
                >
                    <li
                        v-for="(img, i) in university.gallery"
                        :key="i"
                        class="w-[60vw] max-w-[240px] shrink-0 snap-start sm:w-auto sm:max-w-none"
                    >
                        <SiteImg
                            :image="img"
                            class="aspect-[4/3] w-full rounded-[12px]"
                        />
                    </li>
                </ul>
            </section>
        </div>

        <aside class="flex flex-col gap-6 lg:sticky lg:top-36 lg:self-start">
            <dl v-if="facts.length" class="rounded-[20px] bg-site-sand p-5">
                <div
                    v-for="[label, value] in facts"
                    :key="String(label)"
                    class="border-b border-site-line py-3 first:pt-0 last:border-0 last:pb-0"
                >
                    <dt
                        class="text-[11px] font-semibold text-site-muted uppercase"
                    >
                        {{ label }}
                    </dt>
                    <dd class="mt-1 text-[15px] font-medium text-site-ink">
                        {{ value }}
                    </dd>
                </div>
            </dl>
            <div class="rounded-[20px] bg-site-ink p-5 text-white">
                <p class="text-[17px] leading-6 font-semibold">
                    Interested in {{ university.name }}?
                </p>
                <p class="mt-2 text-[13px] text-site-on-dark">
                    An adviser can check your eligibility and guide your
                    application.
                </p>
                <SiteButton href="/contact" class="mt-4 w-full"
                    >Book a consultation</SiteButton
                >
            </div>
        </aside>
    </div>
</template>
