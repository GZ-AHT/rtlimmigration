<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ArrowLink from '@/components/website/ArrowLink.vue';
import BannerMedia from '@/components/website/BannerMedia.vue';
import PathwayFinder from '@/components/website/PathwayFinder.vue';
import SiteButton from '@/components/website/SiteButton.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import { overlayStyle } from '@/components/website/banner';
import type { BannerData } from '@/components/website/banner';
import type { Option, SiteImage } from '@/types/website';

/**
 * Homepage banner, Design 1 ("Existing banner"): Figma "Hero and service
 * finder" (node 12:22). The photo can be a video; extra slides turn it into
 * a slider (REQ §21.7). With the default settings it is exactly the Figma
 * design.
 */
type Slide = { image: SiteImage | null; title: string; description: string };
const props = defineProps<{
    data: BannerData & { slides: Slide[]; mobile_media: 'show' | 'hide' };
    records: { countries: Option[]; levels: Option[]; action: string } | null;
}>();

// Slide 0 is the hero itself (photo or video); extra slides rotate every 7 seconds.
const slides = computed<Slide[]>(() => [
    { image: props.data.image, title: '', description: props.data.description },
    ...props.data.slides.filter((s) => s.image || s.title),
]);
const current = ref(0);
const slide = computed(() => slides.value[current.value] ?? slides.value[0]);
const hasMedia = computed(
    () =>
        !!slide.value.image ||
        (current.value === 0 &&
            props.data.media_type === 'video' &&
            !!props.data.video),
);
const overlay = computed(() => overlayStyle(props.data));
const centered = computed(() => props.data.text_align === 'center');
let timer: number | undefined;

function go(index: number): void {
    current.value = (index + slides.value.length) % slides.value.length;
}

onMounted(() => {
    if (
        slides.value.length > 1 &&
        !window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        timer = window.setInterval(() => go(current.value + 1), 7000);
    }
});
onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <div
        class="site-container w-full min-w-0 pt-4 pb-8 sm:pt-6 sm:pb-10 lg:pt-9"
        :class="
            data.height === 'screen' &&
            'flex flex-col justify-center lg:min-h-[calc(100svh-132px)]'
        "
    >
        <div
            class="grid w-full min-w-0 items-start gap-6 sm:gap-10 lg:grid-cols-[minmax(0,650px)_minmax(0,554px)] lg:justify-between lg:gap-8"
        >
            <div
                class="w-full min-w-0 lg:pt-[15px]"
                :class="centered && 'text-center lg:self-center'"
            >
                <p
                    v-if="data.eyebrow"
                    class="text-[10px] font-bold tracking-[1px] text-site-red-text uppercase sm:text-[11px] sm:leading-[15.6px] sm:tracking-[1.2px]"
                >
                    {{ data.eyebrow }}
                </p>
                <h1
                    class="mt-2.5 text-[28px] leading-[1.12] font-bold tracking-[-0.02em] whitespace-pre-line text-site-ink sm:mt-6 sm:text-[54px] sm:leading-[1.05] sm:tracking-[-0.04em] lg:mt-8.25 lg:text-[82px] lg:leading-[86px] lg:tracking-[-3.4px]"
                >
                    <template v-if="current === 0 || !slide.title"
                        >{{ data.title
                        }}<template v-if="data.title_accent"
                            >{{ '\n'
                            }}<span class="text-site-red">{{
                                data.title_accent
                            }}</span></template
                        ></template
                    >
                    <template v-else>{{ slide.title }}</template>
                </h1>
                <p
                    v-if="slide.description"
                    class="mt-2.5 line-clamp-2 max-w-[530px] text-[14px] leading-relaxed whitespace-pre-line text-site-muted sm:mt-6 sm:line-clamp-none sm:text-[17px] sm:leading-[28.8px] lg:mt-8"
                    :class="centered && 'mx-auto'"
                >
                    {{ slide.description }}
                </p>
                <div
                    class="mt-5 flex flex-col gap-3 sm:mt-8 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-7 lg:mt-10"
                    :class="centered && 'justify-center'"
                >
                    <SiteButton
                        v-if="data.primary.url"
                        :href="data.primary.url"
                        size="lg"
                        class="w-full justify-center sm:w-auto sm:min-w-[240px]"
                        >{{ data.primary.label }}</SiteButton
                    >
                    <ArrowLink
                        v-if="data.secondary.url"
                        :href="data.secondary.url"
                        arrow="→"
                        tone="ink"
                        class="hidden text-[14px] sm:inline-flex sm:text-[15px]"
                        >{{ data.secondary.label }}</ArrowLink
                    >
                </div>
            </div>

            <div
                v-if="hasMedia"
                class="relative"
                :class="data.mobile_media === 'hide' && 'hidden lg:block'"
            >
                <Transition
                    mode="out-in"
                    enter-active-class="transition-opacity duration-500"
                    enter-from-class="opacity-0"
                    leave-active-class="transition-opacity duration-300"
                    leave-to-class="opacity-0"
                >
                    <BannerMedia
                        v-if="current === 0"
                        key="banner"
                        :image="data.image"
                        :video="data.video"
                        :media-type="data.media_type"
                        :mobile-video="data.mobile_video"
                        eager
                        class="aspect-[16/10] w-full rounded-2xl sm:aspect-[554/526] lg:rounded-[28px]"
                    >
                        <div
                            v-if="overlay"
                            class="absolute inset-0"
                            :style="overlay"
                            aria-hidden="true"
                        />
                    </BannerMedia>
                    <SiteImg
                        v-else-if="slide.image"
                        :key="current"
                        :image="slide.image"
                        eager
                        class="aspect-[16/10] w-full rounded-2xl sm:aspect-[554/526] lg:rounded-[28px]"
                    />
                </Transition>
                <p
                    v-if="data.image_caption"
                    class="absolute top-3 left-3 rounded-lg bg-site-navy px-2.5 py-2 text-[10px] font-semibold tracking-[0.8px] text-white uppercase sm:top-4 sm:left-4 sm:rounded-[12px] sm:px-3 sm:py-[13px] sm:text-[11px] sm:leading-[14.3px] sm:tracking-[1px]"
                >
                    {{ data.image_caption }}
                </p>
                <div
                    v-if="slides.length > 1"
                    class="absolute right-0 bottom-4 left-0 flex justify-center gap-2"
                >
                    <button
                        v-for="(s, i) in slides"
                        :key="i"
                        type="button"
                        class="h-2 rounded-full bg-white transition-all"
                        :class="i === current ? 'w-6' : 'w-2 opacity-60'"
                        :aria-label="`Show slide ${i + 1}`"
                        :aria-current="i === current"
                        @click="go(i)"
                    />
                </div>
            </div>
        </div>

        <PathwayFinder
            v-if="data.show_finder && records"
            :tabs="data.finder_tabs"
            :help="data.finder_help"
            :records="records"
            class="mt-6 sm:mt-10 lg:mt-[58px]"
        />
    </div>
</template>
