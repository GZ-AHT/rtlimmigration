<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ArrowLink from '@/components/website/ArrowLink.vue';
import JournalCard from '@/components/website/JournalCard.vue';
import PostCard from '@/components/website/PostCard.vue';
import SectionHeading from '@/components/website/SectionHeading.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import SiteButton from '@/components/website/SiteButton.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import type { LinkValue, Pagination, PostCard as Post } from '@/types/website';

/**
 * Homepage: Figma "Latest insights" (node 12:166).
 * Blog page ("Full blog listing"): Figma "RTL Bangladesh — Blog", with the
 * featured article, search, category pills, article cards and pages.
 */
const props = defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        link: LinkValue;
        all: boolean;
        featured_label: string;
        featured_note: string;
        featured_button: string;
    };
    records: {
        items: Post[];
        featured?: Post | null;
        pagination?: Pagination;
        categories?: { name: string; slug: string }[];
        filters?: { category: string; q: string };
    } | null;
}>();

const search = ref(props.records?.filters?.q ?? '');

function filter(category: string | null): void {
    const query: Record<string, string> = {};
    const cat = category ?? props.records?.filters?.category ?? '';

    if (cat) {
        query.category = cat;
    }

    if (search.value.trim()) {
        query.q = search.value.trim();
    }

    router.get(window.location.pathname, query, {
        preserveScroll: true,
        preserveState: true,
    });
}

const pill = (active: boolean) =>
    [
        'inline-flex h-10 items-center rounded-full border px-4 text-[13px] font-medium transition-colors',
        active
            ? 'border-site-red bg-site-red text-white'
            : 'border-site-line bg-white text-site-ink hover:border-site-ink',
    ].join(' ');
</script>

<template>
    <!-- Blog page -->
    <div v-if="data.all" class="site-container">
        <article
            v-if="records?.featured"
            class="group relative grid overflow-hidden rounded-[12px] bg-site-ink lg:h-[406px] lg:grid-cols-[616px_1fr]"
        >
            <SiteImg
                v-if="records.featured.image"
                :image="records.featured.image"
                eager
                :alt="''"
                class="block aspect-[16/10] size-full lg:aspect-auto"
            />
            <div class="px-6 py-8 sm:px-10 lg:pt-[37px] lg:pb-0">
                <p
                    class="text-[11px] leading-[14px] font-semibold text-site-coral uppercase"
                >
                    {{
                        [data.featured_label, records.featured.label]
                            .filter(Boolean)
                            .join(' / ')
                    }}
                </p>
                <h2
                    class="mt-6 max-w-[350px] text-[28px] leading-[1.2] font-bold text-white sm:text-[36px] sm:leading-[43px] lg:mt-[34px]"
                >
                    {{ records.featured.title }}
                </h2>
                <p
                    v-if="records.featured.excerpt"
                    class="mt-5 max-w-[420px] text-[16px] leading-[23px] text-site-on-dark lg:mt-[29px]"
                >
                    {{ records.featured.excerpt }}
                </p>
                <p
                    v-if="data.featured_note"
                    class="mt-6 text-[12px] leading-4 text-white lg:mt-[33px]"
                >
                    {{ data.featured_note }}
                </p>
                <SiteButton
                    :href="records.featured.url"
                    class="mt-7 min-w-[212px] justify-start! lg:mt-[29px]"
                    >{{ data.featured_button || 'Read the story' }}</SiteButton
                >
            </div>
        </article>

        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            :class="records?.featured ? 'mt-12 lg:mt-[48px]' : ''"
        >
            <h2
                class="text-[28px] leading-[1.2] font-bold text-site-ink sm:text-[32px]"
            >
                {{ data.title }}
            </h2>
            <form
                role="search"
                class="w-full sm:w-[304px]"
                @submit.prevent="filter(null)"
            >
                <label for="blog-search" class="sr-only">Search articles</label>
                <input
                    id="blog-search"
                    v-model="search"
                    type="search"
                    placeholder="Search articles…"
                    class="h-11 w-full rounded-[4px] border border-site-line bg-white px-[15px] text-[14px] text-site-ink outline-none placeholder:text-site-muted focus:border-site-red focus:ring-3 focus:ring-site-red/15"
                />
            </form>
        </div>

        <ul
            v-if="records?.categories?.length"
            class="-mx-4 mt-6 no-scrollbar flex gap-2.5 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:px-0 sm:pb-0 lg:mt-5.5"
            aria-label="Categories"
        >
            <li class="shrink-0">
                <Link
                    href="?"
                    preserve-scroll
                    :class="pill(!records.filters?.category)"
                    :aria-current="
                        !records.filters?.category ? 'true' : undefined
                    "
                    >All articles</Link
                >
            </li>
            <li v-for="c in records.categories" :key="c.slug" class="shrink-0">
                <button
                    type="button"
                    :class="pill(records.filters?.category === c.slug)"
                    :aria-pressed="records.filters?.category === c.slug"
                    @click="filter(c.slug)"
                >
                    {{ c.name }}
                </button>
            </li>
        </ul>

        <p
            v-if="!records?.items.length"
            class="mt-10 rounded-[8px] border border-site-line p-8 text-site-muted"
        >
            {{
                records?.filters?.q || records?.filters?.category
                    ? 'No articles match your search.'
                    : 'New articles are on their way.'
            }}
        </p>
        <ul
            v-else
            class="mt-8 grid gap-6 md:grid-cols-2 lg:mt-[36px] lg:grid-cols-3 lg:gap-y-[30px]"
        >
            <li v-for="post in records.items" :key="post.id">
                <JournalCard :post="post" />
            </li>
        </ul>

        <nav
            v-if="records?.pagination"
            class="mt-10 flex items-center justify-between gap-4 lg:mt-[49px]"
            aria-label="Pages"
        >
            <p class="text-[13px] text-site-muted">
                Showing {{ records.items.length }}
                {{ records.items.length === 1 ? 'article' : 'articles'
                }}<template v-if="records.pagination.last > 1">
                    of {{ records.pagination.total }}</template
                >
            </p>
            <div class="flex items-center gap-2">
                <Link
                    v-if="records.pagination.prev"
                    :href="records.pagination.prev"
                    preserve-scroll
                    class="mr-3 inline-flex items-center gap-2 text-[13px] font-semibold text-site-red hover:underline"
                >
                    <SiteArrow glyph="←" /> Previous
                </Link>
                <template v-for="p in records.pagination.pages" :key="p.number">
                    <span
                        v-if="p.number === records.pagination.current"
                        class="grid size-11 place-items-center rounded-[4px] bg-site-ink text-[14px] font-semibold text-white"
                        aria-current="page"
                        >{{ p.number }}</span
                    >
                    <Link
                        v-else
                        :href="p.url"
                        preserve-scroll
                        class="grid size-11 place-items-center rounded-[4px] border border-site-line text-[14px] font-semibold text-site-ink hover:border-site-ink"
                        >{{ p.number }}</Link
                    >
                </template>
                <Link
                    v-if="records.pagination.next"
                    :href="records.pagination.next"
                    preserve-scroll
                    class="ml-[22px] inline-flex items-center gap-2 text-[13px] font-semibold text-site-red hover:underline"
                >
                    Next <SiteArrow glyph="→" />
                </Link>
                <span
                    v-else
                    class="ml-[22px] inline-flex items-center gap-2 text-[13px] font-semibold text-site-red"
                    aria-disabled="true"
                    >Next <SiteArrow glyph="→"
                /></span>
            </div>
        </nav>
    </div>

    <!-- Homepage -->
    <div v-else class="site-container">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <SectionHeading
                :eyebrow="data.eyebrow"
                :title="data.title"
                :description="data.description"
            />
            <ArrowLink
                v-if="data.link.url"
                :href="data.link.url"
                class="shrink-0 text-[13px] lg:mb-[5px] lg:w-[175px]"
                >{{ data.link.label }}</ArrowLink
            >
        </div>
        <p
            v-if="!records?.items.length"
            class="mt-10 rounded-[20px] bg-site-sand p-8 text-site-muted"
        >
            New articles are on their way.
        </p>
        <ul
            v-else
            class="-mx-4 mt-9 no-scrollbar flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pt-1 pb-4 sm:-mx-6 sm:px-6 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 md:pt-0 md:pb-0 lg:mt-8.25 lg:grid-cols-3 lg:gap-6"
        >
            <li
                v-for="post in records.items"
                :key="post.id"
                class="w-[82vw] max-w-[340px] shrink-0 snap-start sm:w-[60vw] md:w-auto md:max-w-none"
            >
                <PostCard :post="post" />
            </li>
        </ul>
    </div>
</template>
