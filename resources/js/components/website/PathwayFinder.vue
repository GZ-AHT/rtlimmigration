<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ArrowLink from '@/components/website/ArrowLink.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import type { LinkValue, Option } from '@/types/website';

/**
 * "Find your pathway" (roadmap Q8), shared by both homepage banners.
 * card: Figma homepage "Hero and service finder" (node 12:32).
 * bar:  the full-width banner design ("Hero and service finder.png").
 * A tab without a link searches universities and courses; a tab with a
 * link opens that page.
 *
 * Mobile (< lg): modern compact search trigger pill that opens a sleek
 * bottom drawer sheet, minimizing clutter and vertical height on phones.
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

const sheetOpen = ref(false);
const mounted = ref(false);

onMounted(() => {
    mounted.value = true;
});

const chosenCountry = computed(() =>
    props.records.countries.find((c) => c.value === country.value),
);

function chooseMobileTab(i: number): void {
    tab.value = i;
}

function submitMobile(): void {
    sheetOpen.value = false;
    find();
}

function onKey(e: KeyboardEvent): void {
    if (e.key === 'Escape') {
        sheetOpen.value = false;
    }
}

watch(sheetOpen, (val) => {
    if (typeof document !== 'undefined') {
        if (val) {
            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', onKey);
        } else {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', onKey);
        }
    }
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
        window.removeEventListener('keydown', onKey);
    }
});

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
    <div class="w-full min-w-0">
        <!-- Mobile (< lg): Compact App-Like Search Trigger Pill -->
        <div class="block w-full min-w-0 lg:hidden">
            <button
                type="button"
                class="group relative flex w-full items-center justify-between gap-3 rounded-2xl border border-white/40 bg-white/95 p-3.5 shadow-[0_12px_28px_rgba(0,0,0,0.14)] backdrop-blur-md transition-all active:scale-[0.98]"
                @click="sheetOpen = true"
            >
                <span class="flex min-w-0 items-center gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-site-red/10 text-site-red transition-colors group-hover:bg-site-red group-hover:text-white"
                    >
                        <svg
                            class="size-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                    </span>
                    <span class="block min-w-0 text-left">
                        <span
                            class="block truncate text-[14px] font-bold text-site-ink"
                        >
                            Where do you want to study?
                        </span>
                        <span
                            class="block truncate text-[12px] font-medium text-site-muted"
                        >
                            {{ activeTab.label }} ·
                            {{
                                chosenCountry
                                    ? chosenCountry.label
                                    : 'Choose destination & level'
                            }}
                        </span>
                    </span>
                </span>
                <span
                    class="grid size-9 shrink-0 place-items-center rounded-xl bg-site-red text-white shadow-sm transition-transform group-hover:translate-x-0.5"
                >
                    <SiteArrow glyph="→" class="text-[14px]" />
                </span>
            </button>
        </div>

        <!-- Desktop (>= lg): Figma Card or Bar (Exact Figma layout preserved) -->
        <form
            class="relative hidden w-full min-w-0 bg-white lg:block"
            :class="
                bar
                    ? 'overflow-hidden rounded-[6px] pb-6 shadow-[0_12px_30px_rgba(0,0,0,0.18)] lg:h-[148px] lg:pb-0'
                    : 'rounded-[20px] border border-site-warm-line px-3 pt-[9px] pb-6 shadow-[0_12px_30px_rgba(0,0,0,0.12)] lg:h-[160px]'
            "
            aria-label="Find your pathway"
            @submit.prevent="find"
        >
            <div
                class="flex w-full min-w-0 items-center gap-2"
                :class="
                    bar ? 'h-[50px]' : 'border-b border-site-tab-line pb-[10px]'
                "
            >
                <div
                    class="no-scrollbar flex min-w-0 flex-1 overflow-x-auto"
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
                        class="shrink-0 text-left text-[13px] leading-[18px] whitespace-nowrap transition-colors sm:text-[15px] sm:leading-[19.5px]"
                        :class="[
                            bar
                                ? ['h-full px-4 sm:px-[26px]', BAR_WIDTHS[i]]
                                : [
                                      'h-9 rounded-[10px] px-3.5 sm:h-10 sm:px-[22px] lg:min-w-[180px]',
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
            <div
                v-if="bar"
                class="mx-6 h-px bg-site-tab-line"
                aria-hidden="true"
            />

            <div
                class="grid w-full min-w-0 gap-5 lg:gap-0"
                :class="
                    bar
                        ? 'px-6 pt-5 lg:grid-cols-[401px_1fr_237px] lg:items-start lg:pt-[22px]'
                        : 'px-3 pt-5 lg:grid-cols-[400px_1fr_237px] lg:items-center lg:pt-[10px]'
                "
            >
                <div
                    class="relative min-w-0 lg:border-r lg:border-site-tab-line"
                    :class="
                        bar ? 'lg:pt-px lg:pb-[3px]' : 'lg:pt-[10px] lg:pr-6'
                    "
                >
                    <label for="finder-country" :class="fieldLabel">
                        <span class="sm:hidden">Destination</span>
                        <span class="hidden sm:inline"
                            >Where do you want to go?</span
                        >
                    </label>
                    <select
                        id="finder-country"
                        v-model="country"
                        :class="select"
                    >
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
                    class="relative min-w-0"
                    :class="
                        bar
                            ? 'lg:pt-px lg:pl-[29px]'
                            : 'lg:pt-[10px] lg:pl-[29px]'
                    "
                >
                    <label for="finder-level" :class="fieldLabel">
                        <span class="sm:hidden">Study level</span>
                        <span class="hidden sm:inline"
                            >What would you like to study?</span
                        >
                    </label>
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
                    class="flex h-[50px] w-full min-w-0 items-center justify-center gap-2.5 bg-site-red px-5 text-[15px] font-semibold text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none lg:w-auto lg:justify-start"
                    :class="
                        bar ? 'rounded-[4px]' : 'rounded-[12px] lg:mt-[15px]'
                    "
                >
                    <span>Find my pathway</span>
                    <SiteArrow glyph="→" class="shrink-0" />
                </button>
            </div>
        </form>

        <!-- Mobile Bottom Sheet Drawer (< lg) -->
        <Teleport v-if="mounted" to="body">
            <Transition
                enter-active-class="transition-opacity duration-300 ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-200 ease-in"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="sheetOpen"
                    class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs"
                    aria-hidden="true"
                    @click="sheetOpen = false"
                />
            </Transition>

            <Transition
                enter-active-class="transition-transform duration-300 ease-out"
                enter-from-class="translate-y-full"
                leave-active-class="transition-transform duration-200 ease-in"
                leave-to-class="translate-y-full"
            >
                <div
                    v-if="sheetOpen"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Find your pathway"
                    class="safe-bottom fixed inset-x-0 bottom-0 z-50 max-h-[88vh] overflow-y-auto rounded-t-[28px] border-t border-site-warm-line bg-white px-5 pt-3 pb-8 shadow-2xl sm:px-6"
                >
                    <!-- Drag handle bar -->
                    <div
                        class="mx-auto h-1.5 w-12 rounded-full bg-site-warm-line/80"
                    />

                    <!-- Sheet header -->
                    <div
                        class="mt-3 flex items-center justify-between border-b border-site-line/60 pb-3"
                    >
                        <div>
                            <h3 class="text-[17px] font-bold text-site-ink">
                                Find your pathway
                            </h3>
                            <p class="text-[12px] text-site-muted">
                                Choose visa type, destination and study level
                            </p>
                        </div>
                        <button
                            type="button"
                            aria-label="Close"
                            class="grid size-8 place-items-center rounded-full text-site-muted hover:bg-site-sand hover:text-site-ink active:scale-95"
                            @click="sheetOpen = false"
                        >
                            <svg
                                class="size-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>

                    <!-- Form contents -->
                    <form
                        class="mt-4 flex flex-col gap-4"
                        @submit.prevent="submitMobile"
                    >
                        <!-- Visa Type Pills -->
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-wider text-site-muted uppercase"
                            >
                                Visa Service
                            </p>
                            <div
                                class="-mx-5 mt-2 no-scrollbar overflow-x-auto px-5 pb-1"
                            >
                                <ul class="flex snap-x snap-mandatory gap-2">
                                    <li
                                        v-for="(t, i) in list"
                                        :key="t.label"
                                        class="shrink-0 snap-start"
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex h-9.5 items-center rounded-xl px-3.5 text-[13px] font-semibold transition-all active:scale-95"
                                            :class="
                                                i === tab
                                                    ? 'bg-site-red text-white shadow-sm'
                                                    : 'border border-site-line bg-site-sand/40 text-site-ink hover:bg-site-sand'
                                            "
                                            @click="chooseMobileTab(i)"
                                        >
                                            {{ t.label }}
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Destination select field -->
                        <div
                            class="rounded-xl border border-site-field-border bg-site-surface/50 p-3.5 focus-within:border-site-red focus-within:ring-2 focus-within:ring-site-red/15"
                        >
                            <label
                                for="mobile-finder-country"
                                class="block text-[11px] font-semibold tracking-wider text-site-muted uppercase"
                            >
                                Destination country
                            </label>
                            <div class="relative mt-1">
                                <select
                                    id="mobile-finder-country"
                                    v-model="country"
                                    class="h-9 w-full cursor-pointer appearance-none bg-transparent pr-8 text-[15px] font-semibold text-site-ink outline-none"
                                >
                                    <option value="">Choose destination</option>
                                    <option
                                        v-for="c in records.countries"
                                        :key="c.value"
                                        :value="c.value"
                                    >
                                        {{ c.label }}
                                    </option>
                                </select>
                                <svg
                                    class="pointer-events-none absolute top-1/2 right-1 size-4.5 -translate-y-1/2 text-site-muted"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <polyline points="6 9 12 15 18 9" />
                                </svg>
                            </div>
                        </div>

                        <!-- Study Level select field -->
                        <div
                            v-if="!activeTab.url"
                            class="rounded-xl border border-site-field-border bg-site-surface/50 p-3.5 focus-within:border-site-red focus-within:ring-2 focus-within:ring-site-red/15"
                        >
                            <label
                                for="mobile-finder-level"
                                class="block text-[11px] font-semibold tracking-wider text-site-muted uppercase"
                            >
                                What would you like to study?
                            </label>
                            <div class="relative mt-1">
                                <select
                                    id="mobile-finder-level"
                                    v-model="level"
                                    class="h-9 w-full cursor-pointer appearance-none bg-transparent pr-8 text-[15px] font-semibold text-site-ink outline-none"
                                >
                                    <option value="">Choose study level</option>
                                    <option
                                        v-for="l in records.levels"
                                        :key="l.value"
                                        :value="l.value"
                                    >
                                        {{ l.label }}
                                    </option>
                                </select>
                                <svg
                                    class="pointer-events-none absolute top-1/2 right-1 size-4.5 -translate-y-1/2 text-site-muted"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <polyline points="6 9 12 15 18 9" />
                                </svg>
                            </div>
                        </div>

                        <!-- Submit action button -->
                        <button
                            type="submit"
                            class="mt-1 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-site-red text-[15px] font-semibold text-white shadow-md transition-colors hover:bg-site-red-dark active:scale-[0.99]"
                        >
                            <span>Find my pathway</span>
                            <SiteArrow glyph="→" />
                        </button>

                        <!-- Help link -->
                        <div v-if="help.url" class="text-center">
                            <ArrowLink
                                :href="help.url"
                                arrow="→"
                                weight="normal"
                                class="text-[13px] text-site-muted hover:text-site-red"
                                @click="sheetOpen = false"
                            >
                                {{ help.label }}
                            </ArrowLink>
                        </div>
                    </form>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
