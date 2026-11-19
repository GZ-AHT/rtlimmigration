<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ArrowLink from '@/components/website/ArrowLink.vue';
import CountryCombobox from '@/components/website/CountryCombobox.vue';
import type { CountryChoice } from '@/components/website/CountryCombobox.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import { flag } from '@/lib/flag';
import type { LinkValue } from '@/types/website';

/**
 * Homepage skill migration search: pick (or type) a destination and open
 * its assessment introduction. Lists only published country assessments.
 */
const props = defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        placeholder: string;
        button: string;
        link: LinkValue;
    };
    records: { countries: CountryChoice[] } | null;
    dark?: boolean;
}>();

const slug = ref('');
const query = ref('');
const error = ref('');

function go(): void {
    const chosen = props.records?.countries.find((c) => c.slug === slug.value);

    if (chosen) {
        router.visit(chosen.url);
    } else if (query.value.trim()) {
        router.visit(
            `/skill-migration?q=${encodeURIComponent(query.value.trim())}`,
        );
    } else {
        error.value = 'Choose a destination country first.';
    }
}
</script>

<template>
    <div
        class="site-container grid w-full min-w-0 grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_540px] lg:items-center lg:gap-14 xl:grid-cols-[minmax(0,1fr)_620px]"
    >
        <div class="w-full min-w-0">
            <p
                v-if="data.eyebrow"
                class="text-[11px] leading-[14px] font-semibold tracking-wider uppercase"
                :class="dark ? 'text-site-coral' : 'text-site-red'"
            >
                {{ data.eyebrow }}
            </p>
            <h2
                class="mt-3 text-[26px] leading-[1.18] font-bold tracking-[-0.02em] break-words whitespace-pre-line sm:mt-4 sm:text-[36px] sm:leading-[42px] lg:text-[44px] lg:leading-[49px]"
                :class="dark ? 'text-white' : 'text-site-ink'"
            >
                {{ data.title }}
            </h2>
            <p
                v-if="data.description"
                class="mt-3 max-w-[520px] text-[15px] leading-relaxed break-words sm:mt-4 sm:text-[16px] sm:leading-[25px]"
                :class="dark ? 'text-[#cfd0d3]' : 'text-site-muted'"
            >
                {{ data.description }}
            </p>
        </div>

        <div
            class="w-full min-w-0 rounded-2xl bg-white p-5 shadow-[0_12px_30px_rgba(0,0,0,0.12)] sm:rounded-[20px] sm:p-6"
        >
            <form
                class="flex w-full min-w-0 flex-col gap-3"
                role="search"
                aria-label="Find a skill migration assessment"
                @submit.prevent="go"
            >
                <CountryCombobox
                    id="skill-search"
                    v-model="slug"
                    v-model:query="query"
                    :countries="records?.countries ?? []"
                    :placeholder="
                        data.placeholder || 'Search destination country...'
                    "
                    class="w-full min-w-0"
                    @enter="go"
                    @update:model-value="error = ''"
                />
                <button
                    type="submit"
                    class="flex h-12 w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-site-red px-4 text-[14px] font-semibold text-white transition-all hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none active:scale-[0.99] sm:h-[54px] sm:px-6 sm:text-[15px]"
                >
                    <span class="truncate">{{
                        data.button || 'Find Skill Migration Assessment'
                    }}</span>
                    <SiteArrow glyph="→" class="shrink-0" />
                </button>
            </form>
            <p
                v-if="error"
                class="mt-2.5 flex items-center gap-1.5 text-[13px] font-medium text-site-red"
                role="alert"
            >
                <svg
                    class="size-4 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <span>{{ error }}</span>
            </p>

            <div
                v-if="records?.countries.length"
                class="mt-5 w-full min-w-0 overflow-hidden"
            >
                <p
                    class="text-[11px] font-semibold tracking-wider text-site-muted uppercase"
                >
                    Popular destinations
                </p>
                <div
                    class="-mx-5 mt-2.5 no-scrollbar overflow-x-auto px-5 pb-1 sm:mx-0 sm:px-0 sm:pb-0"
                >
                    <ul class="flex snap-x snap-mandatory gap-2 sm:flex-wrap">
                        <li
                            v-for="c in records.countries"
                            :key="c.slug"
                            class="shrink-0 snap-start"
                        >
                            <a
                                :href="c.url"
                                class="inline-flex h-9.5 items-center gap-2 rounded-full border border-site-line bg-site-sand/30 px-3.5 text-[13px] font-medium text-site-ink transition-all hover:border-site-red hover:bg-site-blush-bg/50 hover:text-site-red active:scale-95 sm:h-10 sm:px-4 sm:text-[14px]"
                                @click.prevent="router.visit(c.url)"
                            >
                                <span
                                    aria-hidden="true"
                                    class="text-base select-none"
                                    >{{ flag(c.iso2) }}</span
                                ><span>{{ c.country }}</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <p v-else class="mt-4 text-[14px] text-site-muted">
                Assessments are on their way. Talk to an adviser in the
                meantime.
            </p>

            <ArrowLink
                v-if="data.link.url"
                :href="data.link.url"
                class="mt-4 inline-flex text-[14px]"
                >{{ data.link.label }}</ArrowLink
            >
        </div>
    </div>
</template>
