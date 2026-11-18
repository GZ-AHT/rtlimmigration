<script setup lang="ts">
import { computed, ref } from 'vue';
import ArrowLink from '@/components/website/ArrowLink.vue';
import SectionHeading from '@/components/website/SectionHeading.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import type { LinkValue, TestimonialCard } from '@/types/website';
import SiteArrow from '@/components/website/SiteArrow.vue';

/**
 * Figma "Student success" (node 12:118): one large quote, with previous /
 * next buttons when there are several. "Show every testimonial" (the
 * reviews page) lays them all out as a grid instead.
 */
const props = defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        link: LinkValue;
        all: boolean;
    };
    records: { items: TestimonialCard[] } | null;
}>();

const items = computed(() => props.records?.items ?? []);
const index = ref(0);
const current = computed(() => items.value[index.value]);
const byline = (t: TestimonialCard) =>
    [t.role, t.country].filter(Boolean).join(' · ');

function step(by: number): void {
    index.value = (index.value + by + items.value.length) % items.value.length;
}
</script>

<template>
    <div v-if="data.all" class="site-container">
        <SectionHeading
            :eyebrow="data.eyebrow"
            :title="data.title"
            :description="data.description"
        />
        <p v-if="!items.length" class="mt-10 text-site-muted">
            Student reviews will appear here soon.
        </p>
        <ul v-else class="mt-10 columns-1 gap-6 md:columns-2 lg:mt-12">
            <li
                v-for="t in items"
                :key="t.id"
                class="mb-6 break-inside-avoid rounded-[24px] bg-site-sand p-7 sm:p-8"
            >
                <span
                    aria-hidden="true"
                    class="block text-[56px] leading-[48px] font-bold text-site-red"
                    >“</span
                >
                <blockquote
                    class="mt-2 text-[18px] leading-[28px] font-medium text-site-ink"
                >
                    {{ t.quote }}
                </blockquote>
                <div class="mt-6 flex items-center gap-3">
                    <SiteImg
                        v-if="t.photo"
                        :image="t.photo"
                        :alt="''"
                        class="size-11 rounded-full"
                    />
                    <div>
                        <p
                            class="text-[11px] leading-[14.3px] font-semibold text-site-red uppercase"
                        >
                            {{ t.name }}
                        </p>
                        <p
                            v-if="byline(t)"
                            class="mt-1 text-[12px] leading-[15.6px] text-site-muted"
                        >
                            {{ byline(t) }}
                        </p>
                    </div>
                </div>
            </li>
        </ul>
    </div>

    <div
        v-else
        class="site-container grid gap-10 lg:grid-cols-[475px_1fr] lg:gap-[65px]"
    >
        <div class="lg:pt-[25px]">
            <SectionHeading
                :eyebrow="data.eyebrow"
                :title="data.title"
                class="[&_h2]:sm:text-[44px] [&_h2]:sm:leading-[49px]"
            />
            <p
                v-if="data.description"
                class="mt-6 max-w-[450px] text-[16px] leading-[25.6px] whitespace-pre-line text-site-muted"
            >
                {{ data.description }}
            </p>
            <ArrowLink
                v-if="data.link.url"
                :href="data.link.url"
                class="mt-9 text-[15px]"
                >{{ data.link.label }}</ArrowLink
            >
        </div>

        <figure
            v-if="current"
            class="relative min-h-[332px] rounded-[24px] bg-site-sand px-6 pt-[7px] pb-8 sm:px-8"
        >
            <span
                aria-hidden="true"
                class="block text-[70px] leading-[70px] font-bold text-site-red sm:-ml-1"
                >“</span
            >
            <Transition
                mode="out-in"
                enter-active-class="transition-opacity duration-300"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-200"
                leave-to-class="opacity-0"
            >
                <div :key="current.id">
                    <blockquote
                        class="mt-[6px] max-w-[644px] text-[19px] leading-[28px] font-medium text-site-ink sm:text-[23px] sm:leading-[32px]"
                    >
                        {{ current.quote }}
                    </blockquote>
                    <figcaption class="mt-10 flex items-center gap-3 lg:mt-14">
                        <SiteImg
                            v-if="current.photo"
                            :image="current.photo"
                            :alt="''"
                            class="size-11 rounded-full"
                        />
                        <span>
                            <span
                                class="block text-[11px] leading-[14.3px] font-semibold text-site-red uppercase"
                                >{{ current.name }}</span
                            >
                            <span
                                v-if="byline(current)"
                                class="mt-[10px] block text-[12px] leading-[15.6px] text-site-muted"
                                >{{ byline(current) }}</span
                            >
                        </span>
                    </figcaption>
                </div>
            </Transition>
            <div
                v-if="items.length > 1"
                class="absolute right-6 bottom-7 flex gap-2 sm:right-8"
            >
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-full border border-site-warm-line bg-white text-site-ink hover:border-site-red hover:text-site-red"
                    aria-label="Previous review"
                    @click="step(-1)"
                >
                    <SiteArrow glyph="←" />
                </button>
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-full border border-site-warm-line bg-white text-site-ink hover:border-site-red hover:text-site-red"
                    aria-label="Next review"
                    @click="step(1)"
                >
                    <SiteArrow glyph="→" />
                </button>
            </div>
        </figure>
    </div>
</template>
