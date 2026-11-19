<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import ArrowLink from '@/components/website/ArrowLink.vue';
import SectionHeading from '@/components/website/SectionHeading.vue';
import SitePagination from '@/components/website/SitePagination.vue';
import UniversityCard from '@/components/website/UniversityCard.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import type {
    LinkValue,
    Option,
    Pagination,
    UniversityCard as Uni,
} from '@/types/website';

/**
 * Featured universities, or (with "Searchable list" on) the pathway
 * finder results: filter by destination, study level and keyword
 * (roadmap Q8, REQ §15).
 */
const props = defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        link: LinkValue;
        all: boolean;
    };
    records: {
        items: Uni[];
        pagination?: Pagination;
        countries?: Option[];
        levels?: Option[];
        filters?: { country: string; level: string; q: string };
    } | null;
}>();

const filters = reactive({
    ...(props.records?.filters ?? { country: '', level: '', q: '' }),
});

function apply(): void {
    const query = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => String(v).trim() !== ''),
    );

    router.get(window.location.pathname, query, {
        preserveScroll: true,
        preserveState: true,
    });
}

function reset(): void {
    Object.assign(filters, { country: '', level: '', q: '' });
    apply();
}

const field =
    'h-[50px] w-full rounded-[12px] border border-site-field-border bg-white px-4 text-[15px] text-site-ink outline-none focus:border-site-red focus:ring-3 focus:ring-site-red/15';
</script>

<template>
    <div class="site-container">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <SectionHeading
                :eyebrow="data.eyebrow"
                :title="data.title"
                :description="data.description"
            />
            <ArrowLink
                v-if="data.link.url && !data.all"
                :href="data.link.url"
                class="shrink-0 text-[13px] lg:mb-[5px]"
                >{{ data.link.label }}</ArrowLink
            >
        </div>

        <form
            v-if="data.all && records"
            class="mt-8 grid gap-3 rounded-[20px] border border-site-warm-line bg-white p-4 shadow-[0_12px_30px_rgba(0,0,0,0.06)] md:grid-cols-2 lg:grid-cols-[1fr_1fr_1.3fr_auto]"
            role="search"
            aria-label="Find universities and courses"
            @submit.prevent="apply"
        >
            <div>
                <label
                    for="uni-country"
                    class="mb-1.5 block text-[11px] font-semibold text-site-muted uppercase"
                    >Destination</label
                >
                <select
                    id="uni-country"
                    v-model="filters.country"
                    :class="field"
                >
                    <option value="">All destinations</option>
                    <option
                        v-for="c in records.countries"
                        :key="c.value"
                        :value="c.value"
                    >
                        {{ c.label }}
                    </option>
                </select>
            </div>
            <div>
                <label
                    for="uni-level"
                    class="mb-1.5 block text-[11px] font-semibold text-site-muted uppercase"
                    >Study level</label
                >
                <select id="uni-level" v-model="filters.level" :class="field">
                    <option value="">All levels</option>
                    <option
                        v-for="l in records.levels"
                        :key="l.value"
                        :value="l.value"
                    >
                        {{ l.label }}
                    </option>
                </select>
            </div>
            <div>
                <label
                    for="uni-q"
                    class="mb-1.5 block text-[11px] font-semibold text-site-muted uppercase"
                    >Course or university</label
                >
                <input
                    id="uni-q"
                    v-model="filters.q"
                    type="search"
                    placeholder="e.g. Business, Nursing, Toronto"
                    :class="field"
                />
            </div>
            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    class="h-[50px] flex-1 rounded-[12px] bg-site-red px-5 text-[15px] font-semibold whitespace-nowrap text-white hover:bg-site-red-dark lg:flex-none"
                >
                    Search <SiteArrow glyph="→" />
                </button>
            </div>
        </form>

        <p
            v-if="data.all && records?.pagination"
            class="mt-6 text-[14px] text-site-muted"
            role="status"
        >
            {{ records.pagination.total }}
            {{ records.pagination.total === 1 ? 'university' : 'universities' }}
            found
            <button
                v-if="filters.country || filters.level || filters.q"
                type="button"
                class="ml-2 font-semibold text-site-red hover:underline"
                @click="reset"
            >
                Clear filters
            </button>
        </p>

        <div
            v-if="!records?.items.length"
            class="mt-8 rounded-[20px] bg-site-sand p-8"
        >
            <p class="text-[17px] font-semibold text-site-ink">
                {{
                    data.all
                        ? 'No universities match your search yet.'
                        : 'Our partner universities will appear here soon.'
                }}
            </p>
            <p class="mt-2 text-site-muted">
                Tell us what you’d like to study and an adviser will suggest
                options that fit.
            </p>
            <ArrowLink href="/contact" class="mt-4 text-[14px]"
                >Talk to an adviser</ArrowLink
            >
        </div>
        <ul
            v-else
            :class="[
                'mt-8',
                data.all
                    ? 'grid gap-6 md:grid-cols-2 lg:grid-cols-3'
                    : '-mx-4 no-scrollbar flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pt-1 pb-4 sm:-mx-6 sm:px-6 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 md:pt-0 md:pb-0 lg:grid-cols-3 lg:gap-6',
            ]"
        >
            <li
                v-for="u in records.items"
                :key="u.id"
                :class="
                    !data.all &&
                    'w-[82vw] max-w-[340px] shrink-0 snap-start sm:w-[60vw] md:w-auto md:max-w-none'
                "
            >
                <UniversityCard :university="u" />
            </li>
        </ul>

        <SitePagination
            v-if="data.all && records?.pagination"
            :pagination="records.pagination"
        />
    </div>
</template>
