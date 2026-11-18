<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ArrowLink from '@/components/website/ArrowLink.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import type { LinkValue, Option } from '@/types/website';

/**
 * "Find your pathway" (roadmap Q8), shared by both homepage banners.
 * card: Figma homepage "Hero and service finder" (node 12:32).
 * bar:  the full-width banner design ("Hero and service finder.png").
 * A tab without a link searches universities and courses; a tab with a
 * link opens that page.
 */
const props = withDefaults(
    defineProps<{
        tabs: { label: string; url: string }[];
        help: LinkValue;
        records: { countries: Option[]; levels: Option[]; action: string };
        variant?: 'card' | 'bar';
    }>(),
    { variant: 'card' },
);

const list = computed(() =>
    props.tabs.length ? props.tabs : [{ label: 'Find a course', url: '' }],
);
const tab = ref(0);
const country = ref('');
const level = ref('');
const activeTab = computed(() => list.value[tab.value] ?? list.value[0]);
const bar = computed(() => props.variant === 'bar');
// Figma bar design: the tabs are 219, 198 and 174px wide, then fit their text.
const BAR_WIDTHS = ['lg:min-w-[219px]', 'lg:min-w-[198px]', 'lg:min-w-[174px]'];

function find(): void {
    const target =
        activeTab.value.url || props.records.action || '/universities';
    const query: Record<string, string> = {};

    if (country.value) {
        query.country = country.value;
    }

    if (!activeTab.value.url && level.value) {
        query.level = level.value;
    }

    if (/^https?:\/\//i.test(target)) {
        window.location.href = target;

        return;
    }

    router.get(target, query);
}

const fieldLabel =
    'block text-[11px] leading-[13px] font-semibold text-site-muted uppercase';
const select = computed(() =>
    [
        'mt-[10px] h-[30px] w-full cursor-pointer appearance-none bg-transparent pr-8 font-medium text-site-ink outline-none focus-visible:underline',
        bar.value
            ? 'text-[17px] leading-[22px] lg:max-w-[230px]'
            : 'text-[16px] leading-[20.8px]',
    ].join(' '),
);
</script>

<template>
    <form
        class="relative bg-white"
        :class="
            bar
                ? 'overflow-hidden rounded-[6px] pb-6 shadow-[0_12px_30px_rgba(0,0,0,0.18)] lg:h-[148px] lg:pb-0'
                : 'rounded-[20px] border border-site-warm-line px-3 pt-[9px] pb-6 shadow-[0_12px_30px_rgba(0,0,0,0.12)] lg:h-[160px]'
        "
        aria-label="Find your pathway"
        @submit.prevent="find"
    >
        <div
            class="flex items-center gap-2"
            :class="
                bar ? 'h-[50px]' : 'border-b border-site-tab-line pb-[10px]'
            "
        >
            <div
                class="flex flex-1 overflow-x-auto"
                :class="bar ? 'h-full' : '-mx-1 gap-1 px-1'"
                role="tablist"
                aria-label="Visa type"
            >
                <button
                    v-for="(t, i) in list"
                    :key="t.label"
                    type="button"
                    role="tab"
                    :aria-selected="i === tab"
                    class="shrink-0 text-left text-[15px] leading-[19.5px] whitespace-nowrap transition-colors"
                    :class="[
                        bar
                            ? ['h-full px-[26px]', BAR_WIDTHS[i]]
                            : [
                                  'h-10 rounded-[10px] px-[22px] lg:min-w-[180px]',
                                  i === tab && 'lg:min-w-[204px]',
                              ],
                        i === tab
                            ? 'bg-site-red font-semibold text-white'
                            : 'font-medium text-site-ink hover:bg-site-sand',
                    ]"
                    @click="tab = i"
                >
                    {{ t.label }}
                </button>
            </div>
            <div
                v-if="help.url"
                class="hidden shrink-0 lg:block"
                :class="bar ? 'w-[150px] pr-6 lg:mr-[34px]' : 'pr-[10px]'"
            >
                <ArrowLink
                    :href="help.url"
                    arrow="→"
                    weight="normal"
                    class="text-[13px] font-medium"
                    >{{ help.label }}</ArrowLink
                >
            </div>
        </div>
        <div v-if="bar" class="mx-6 h-px bg-site-tab-line" aria-hidden="true" />

        <div
            class="grid gap-5 lg:gap-0"
            :class="
                bar
                    ? 'px-6 pt-5 lg:grid-cols-[401px_1fr_237px] lg:items-start lg:pt-[22px]'
                    : 'px-3 pt-5 lg:grid-cols-[400px_1fr_237px] lg:items-center lg:pt-[10px]'
            "
        >
            <div
                class="relative lg:border-r lg:border-site-tab-line"
                :class="bar ? 'lg:pt-px lg:pb-[3px]' : 'lg:pt-[10px] lg:pr-6'"
            >
                <label for="finder-country" :class="fieldLabel"
                    >Where do you want to go?</label
                >
                <select id="finder-country" v-model="country" :class="select">
                    <option value="">Choose your destination</option>
                    <option
                        v-for="c in records.countries"
                        :key="c.value"
                        :value="c.value"
                    >
                        {{ c.label }}
                    </option>
                </select>
                <img
                    src="/images/website/chevron-down.svg"
                    alt=""
                    width="24"
                    height="24"
                    class="pointer-events-none absolute size-[18px]"
                    :class="
                        bar
                            ? 'right-8 bottom-[8px] lg:right-auto lg:left-[205px]'
                            : 'right-8 bottom-[6px]'
                    "
                />
            </div>
            <div
                v-if="!activeTab.url"
                class="relative"
                :class="
                    bar ? 'lg:pt-px lg:pl-[29px]' : 'lg:pt-[10px] lg:pl-[29px]'
                "
            >
                <label for="finder-level" :class="fieldLabel"
                    >What would you like to study?</label
                >
                <select
                    id="finder-level"
                    v-model="level"
                    :class="select"
                    class="lg:max-w-[380px]"
                >
                    <option value="">Choose your study level</option>
                    <option
                        v-for="l in records.levels"
                        :key="l.value"
                        :value="l.value"
                    >
                        {{ l.label }}
                    </option>
                </select>
                <img
                    src="/images/website/chevron-down.svg"
                    alt=""
                    width="24"
                    height="24"
                    class="pointer-events-none absolute size-[18px]"
                    :class="
                        bar
                            ? 'right-8 bottom-[8px] lg:right-auto lg:left-[234px]'
                            : 'bottom-[6px] left-[calc(100%-2rem)] lg:left-[min(calc(100%-2rem),390px)]'
                    "
                />
            </div>
            <div v-else class="hidden lg:block" />
            <button
                type="submit"
                class="flex h-[50px] items-center justify-center gap-2.5 bg-site-red px-5 text-[15px] font-semibold text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none lg:justify-start"
                :class="bar ? 'rounded-[4px]' : 'rounded-[12px] lg:mt-[15px]'"
            >
                Find my pathway <SiteArrow glyph="→" />
            </button>
        </div>
    </form>
</template>
