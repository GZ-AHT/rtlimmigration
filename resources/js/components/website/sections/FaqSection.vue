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
        class="site-container grid gap-10 lg:grid-cols-[363px_805px] lg:justify-between"
    >
        <div class="lg:pt-1">
            <SectionHeading
                :eyebrow="data.eyebrow"
                :title="data.title"
                size="lg"
                class="[&_h2]:sm:tracking-[-1.8px]"
            />
            <p
                v-if="data.description"
                class="mt-[21px] text-[15px] leading-[21.75px] text-site-muted"
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

            <ul
                v-if="groups.length > 1 || data.all"
                class="-mx-4 mt-5.5 no-scrollbar flex snap-x snap-mandatory gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-col sm:overflow-visible sm:px-0 sm:pb-0"
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
                        class="relative flex h-11 items-center justify-between overflow-hidden rounded-xl pr-4 pl-4.5 text-left text-[14px] leading-[18.2px] whitespace-nowrap transition-colors sm:w-full"
                        :class="
                            !query && i === active
                                ? 'bg-site-navy font-semibold text-white'
                                : 'font-medium text-site-muted hover:bg-white'
                        "
                        @click="choose(i)"
                    >
                        <span
                            v-if="!query && i === active"
                            class="absolute inset-y-0 left-0 w-[3px] bg-site-red"
                            aria-hidden="true"
                        />
                        {{ g.name }}
                        <SiteArrow glyph="→" class="text-[18px]" />
                    </button>
                </li>
            </ul>
        </div>

        <div>
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
            <ul v-else class="flex flex-col gap-2 lg:pt-[3px]">
                <li
                    v-for="f in items"
                    :key="f.id"
                    class="rounded-[12px] border bg-white"
                    :class="
                        open === f.id
                            ? 'border-site-warm-line'
                            : 'border-transparent'
                    "
                >
                    <h3>
                        <button
                            :id="`faq-q-${f.id}`"
                            type="button"
                            class="flex w-full items-start justify-between gap-6 px-6 text-left"
                            :class="
                                open === f.id ? 'pt-[21px] pb-0' : 'py-[14px]'
                            "
                            :aria-expanded="open === f.id"
                            :aria-controls="`faq-a-${f.id}`"
                            @click="open = open === f.id ? null : f.id"
                        >
                            <span
                                :class="
                                    open === f.id
                                        ? 'text-[17px] leading-[24.65px] font-semibold text-site-ink'
                                        : 'text-[15px] leading-[19.5px] font-medium text-site-ink'
                                "
                                >{{ f.question }}</span
                            >
                            <span
                                aria-hidden="true"
                                class="w-[26px] shrink-0 text-[22px]"
                                :class="
                                    open === f.id
                                        ? 'leading-[24.65px] font-medium text-site-red'
                                        : 'leading-5 text-site-muted'
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
                        class="px-6 pt-[16px] pb-[24px] text-[15px] leading-6 whitespace-pre-line text-site-muted lg:max-w-[778px]"
                    >
                        {{ f.answer }}
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
