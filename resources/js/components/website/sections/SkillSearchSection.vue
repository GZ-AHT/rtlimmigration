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
        class="site-container grid gap-8 lg:grid-cols-[minmax(0,1fr)_620px] lg:items-center lg:gap-14"
    >
        <div>
            <p
                v-if="data.eyebrow"
                class="text-[11px] leading-[14px] font-semibold uppercase"
                :class="dark ? 'text-site-coral' : 'text-site-red'"
            >
                {{ data.eyebrow }}
            </p>
            <h2
                class="mt-4 text-[32px] leading-[1.1] font-bold tracking-[-0.03em] whitespace-pre-line sm:text-[44px] sm:leading-[49px]"
                :class="dark ? 'text-white' : 'text-site-ink'"
            >
                {{ data.title }}
            </h2>
            <p
                v-if="data.description"
                class="mt-5 max-w-[520px] text-[16px] leading-[25px]"
                :class="dark ? 'text-[#cfd0d3]' : 'text-site-muted'"
            >
                {{ data.description }}
            </p>
        </div>

        <div
            class="rounded-[20px] bg-white p-4 shadow-[0_12px_30px_rgba(0,0,0,0.18)] sm:p-6"
        >
            <form
                class="flex flex-col gap-3"
                role="search"
                aria-label="Find a skill migration assessment"
                @submit.prevent="go"
            >
                <CountryCombobox
                    id="skill-search"
                    @enter="go"
                    v-model="slug"
                    v-model:query="query"
                    :countries="records?.countries ?? []"
                    :placeholder="
                        data.placeholder ||
                        'Search your skill migration destination...'
                    "
                    class="flex-1"
                    @update:model-value="error = ''"
                />
                <button
                    type="submit"
                    class="flex h-[54px] shrink-0 items-center justify-center gap-2.5 rounded-[10px] bg-site-red px-5 text-[15px] font-semibold text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none"
                >
                    {{ data.button || 'Find Skill Migration Assessment' }}
                    <SiteArrow glyph="→" />
                </button>
            </form>
            <p v-if="error" class="mt-2 text-[13px] text-site-red" role="alert">
                {{ error }}
            </p>

            <div v-if="records?.countries.length" class="mt-5">
                <p class="text-[11px] font-semibold text-site-muted uppercase">
                    Popular destinations
                </p>
                <ul class="mt-3 flex flex-wrap gap-2">
                    <li v-for="c in records.countries" :key="c.slug">
                        <a
                            :href="c.url"
                            class="inline-flex h-10 items-center gap-2 rounded-full border border-site-line px-4 text-[14px] font-medium text-site-ink transition-colors hover:border-site-red hover:text-site-red"
                            @click.prevent="router.visit(c.url)"
                        >
                            <span aria-hidden="true">{{ flag(c.iso2) }}</span
                            >{{ c.country }}
                        </a>
                    </li>
                </ul>
            </div>
            <p v-else class="mt-4 text-[14px] text-site-muted">
                Assessments are on their way. Talk to an adviser in the
                meantime.
            </p>

            <ArrowLink
                v-if="data.link.url"
                :href="data.link.url"
                class="mt-5 text-[14px]"
                >{{ data.link.label }}</ArrowLink
            >
        </div>
    </div>
</template>
