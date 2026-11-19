<script setup lang="ts">
import { computed, ref } from 'vue';
import SectionHeading from '@/components/website/SectionHeading.vue';
import type { FaqGroup } from '@/types/website';
import SiteArrow from '@/components/website/SiteArrow.vue';

/**
 * Figma "Frequently asked questions" (node 12:189): categories on the
 * left, expanding answers on the right. "Show every FAQ" (the FAQ page)
 * adds search across all questions (REQ §21.8).
 */
const props = defineProps<{
    data: { eyebrow: string; title: string; description: string; all: boolean };
    records: { groups: FaqGroup[] } | null;
}>();

const groups = computed(() => props.records?.groups ?? []);
const active = ref(0);
const open = ref<number | null>(groups.value[0]?.items[0]?.id ?? null);
const query = ref('');

const items = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (q) {
        return groups.value
            .flatMap((g) => g.items)
            .filter((f) =>
                `${f.question} ${f.answer}`.toLowerCase().includes(q),
            );
    }

    return groups.value[active.value]?.items ?? [];
});

function choose(index: number): void {
    active.value = index;
    query.value = '';
    open.value = groups.value[index]?.items[0]?.id ?? null;
}
</script>

<template>
    <div
        class="site-container grid w-full min-w-0 grid-cols-1 gap-8 lg:grid-cols-[363px_minmax(0,1fr)] lg:justify-between lg:gap-10 xl:grid-cols-[363px_805px]"
    >
        <div class="w-full min-w-0 lg:pt-1">
            <SectionHeading
                :eyebrow="data.eyebrow"
                :title="data.title"
                size="lg"
                class="[&_h2]:sm:tracking-[-1.8px]"
            />
            <p
                v-if="data.description"
                class="mt-4 text-[15px] leading-relaxed text-site-muted sm:mt-[21px] sm:leading-[21.75px]"
            >
                {{ data.description }}
            </p>

            <div v-if="data.all" class="mt-6">
                <label for="faq-search" class="sr-only">Search questions</label>
                <input
                    id="faq-search"
                    v-model="query"
                    type="search"
                    placeholder="Search questions"
                    class="h-11 w-full rounded-[12px] border border-site-field-border bg-white px-4 text-[14px] outline-none focus:border-site-red focus:ring-3 focus:ring-site-red/15"
                />
            </div>

            <div
                v-if="groups.length > 1 || data.all"
                class="mt-5 w-full min-w-0 sm:mt-5.5"
            >
                <div
                    class="-mx-4 no-scrollbar overflow-x-auto px-4 pb-2 sm:mx-0 sm:overflow-visible sm:px-0 sm:pb-0"
                >
                    <ul
                        class="flex snap-x snap-mandatory gap-2 sm:flex-col"
                        role="tablist"
                        aria-label="Question topics"
                    >
                        <li
                            v-for="(g, i) in groups"
                            :key="g.slug"
                            class="shrink-0 snap-start sm:shrink sm:snap-none"
                        >
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="!query && i === active"
                                class="relative flex h-10 items-center justify-between gap-3 overflow-hidden rounded-xl px-4 text-left text-[13.5px] leading-[18.2px] whitespace-nowrap transition-colors active:scale-98 sm:h-11 sm:w-full sm:pr-4 sm:pl-4.5 sm:text-[14px]"
                                :class="
                                    !query && i === active
                                        ? 'bg-site-navy font-semibold text-white shadow-sm'
                                        : 'border border-site-line/70 bg-white/80 font-medium text-site-muted hover:bg-white sm:border-0 sm:bg-transparent'
                                "
                                @click="choose(i)"
                            >
                                <span
                                    v-if="!query && i === active"
                                    class="absolute inset-y-0 left-0 hidden w-[3px] bg-site-red sm:block"
                                    aria-hidden="true"
                                />
                                <span>{{ g.name }}</span>
                                <SiteArrow
                                    glyph="→"
                                    class="hidden shrink-0 text-[16px] sm:inline-block"
                                />
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="w-full min-w-0">
            <p
                v-if="!items.length"
                class="rounded-[12px] bg-white p-6 text-site-muted"
            >
                {{
                    query
                        ? 'No questions match your search.'
                        : 'Questions and answers will appear here soon.'
                }}
            </p>
            <ul v-else class="flex w-full min-w-0 flex-col gap-2.5 lg:pt-[3px]">
                <li
                    v-for="f in items"
                    :key="f.id"
                    class="w-full min-w-0 rounded-[14px] border bg-white shadow-[0_2px_8px_rgba(0,0,0,0.03)] transition-all"
                    :class="
                        open === f.id
                            ? 'border-site-warm-line shadow-[0_4px_16px_rgba(0,0,0,0.06)]'
                            : 'border-transparent'
                    "
                >
                    <h3>
                        <button
                            :id="`faq-q-${f.id}`"
                            type="button"
                            class="flex w-full items-start justify-between gap-4 px-4.5 py-4 text-left sm:gap-6 sm:px-6 sm:py-4.5"
                            :class="open === f.id ? 'pb-1' : ''"
                            :aria-expanded="open === f.id"
                            :aria-controls="`faq-a-${f.id}`"
                            @click="open = open === f.id ? null : f.id"
                        >
                            <span
                                class="min-w-0 flex-1 text-[15px] leading-snug font-semibold break-words text-site-ink sm:text-[17px]"
                                >{{ f.question }}</span
                            >
                            <span
                                aria-hidden="true"
                                class="grid size-7 shrink-0 place-items-center rounded-full text-base font-semibold transition-colors"
                                :class="
                                    open === f.id
                                        ? 'bg-site-red/10 text-site-red'
                                        : 'bg-site-sand text-site-muted'
                                "
                                >{{ open === f.id ? '−' : '+' }}</span
                            >
                        </button>
                    </h3>
                    <div
                        v-show="open === f.id"
                        :id="`faq-a-${f.id}`"
                        role="region"
                        :aria-labelledby="`faq-q-${f.id}`"
                        class="px-4.5 pt-2 pb-5 text-[14px] leading-relaxed break-words whitespace-pre-line text-site-muted sm:px-6 sm:pt-3 sm:pb-6 sm:text-[15px] lg:max-w-[778px]"
                    >
                        {{ f.answer }}
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
