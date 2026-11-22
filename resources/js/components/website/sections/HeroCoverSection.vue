<script setup lang="ts">
import { computed } from 'vue';
import ArrowLink from '@/components/website/ArrowLink.vue';
import BannerMedia from '@/components/website/BannerMedia.vue';
import PathwayFinder from '@/components/website/PathwayFinder.vue';
import SiteButton from '@/components/website/SiteButton.vue';
import {
    deviceHeightClasses,
    mobileOverlayStyle,
    overlayStyle,
} from '@/components/website/banner';
import type { BannerData } from '@/components/website/banner';
import type { Option } from '@/types/website';

/**
 * Homepage banner, Design 2 ("New full-width banner"), from the owner's
 * reference "Hero and service finder.png" (1440 × 690): white text over a
 * full-width photo or video with a dark overlay, and the pathway finder as
 * a bar along the bottom.
 */
const props = defineProps<{
    data: BannerData;
    records: { countries: Option[]; levels: Option[]; action: string } | null;
}>();

const overlay = computed(() => overlayStyle(props.data));
const mobileOverlay = computed(() => mobileOverlayStyle(props.data));
const centered = computed(() => props.data.text_align === 'center');
const HEIGHTS: Record<string, string> = {
    standard: 'lg:min-h-[690px]',
    compact: 'lg:min-h-[560px]',
    tall: 'lg:min-h-[820px]',
    screen: 'lg:min-h-[calc(100svh-132px)]',
};
</script>

<template>
    <BannerMedia
        :image="data.image"
        :video="data.video"
        :media-type="data.media_type"
        :mobile-video="data.mobile_video"
        eager
        class="isolate"
    >
        <div
            v-if="overlay"
            class="absolute inset-0 max-lg:hidden"
            :style="overlay"
            aria-hidden="true"
        />
        <div
            v-if="mobileOverlay"
            class="absolute inset-0 lg:hidden"
            :style="mobileOverlay"
            aria-hidden="true"
        />
        <div
            class="relative site-container flex flex-col pt-12 pb-8 max-lg:gap-6 max-sm:pt-6 max-sm:pb-5 sm:max-lg:pt-9 sm:max-lg:pb-6 lg:pt-[57px] lg:pb-[33px]"
            :class="[
                HEIGHTS[data.height] ?? HEIGHTS.standard,
                deviceHeightClasses(data),
            ]"
        >
            <div
                class="max-w-[650px]"
                :class="centered && 'mx-auto text-center'"
            >
                <p
                    v-if="data.eyebrow"
                    class="text-[12px] leading-[15px] font-semibold text-white uppercase"
                >
                    {{ data.eyebrow }}
                </p>
                <h1
                    class="mt-6 text-[34px] leading-[1.08] font-bold tracking-[-0.03em] whitespace-pre-line text-white max-lg:[text-shadow:0_1px_14px_rgb(0_0_0/0.35)] max-sm:mt-2 max-sm:text-[28px] sm:text-[48px] sm:leading-[1.05] sm:max-lg:text-[40px] lg:mt-6.25 lg:text-[66px] lg:leading-[73px] lg:tracking-[-0.5px]"
                >
                    {{ data.title
                    }}<template v-if="data.title_accent"
                        >{{ '\n' }}{{ data.title_accent }}</template
                    >
                </h1>
                <p
                    v-if="data.description"
                    class="mt-6 max-w-[540px] text-[16px] leading-[1.7] text-white max-lg:[text-shadow:0_1px_10px_rgb(0_0_0/0.35)] max-sm:mt-2 max-sm:line-clamp-2 max-sm:text-[14px] max-sm:leading-[1.5] sm:text-[17px] sm:leading-[29px] sm:max-lg:line-clamp-3 sm:max-lg:text-[16px] lg:mt-7 lg:whitespace-pre-line"
                    :class="centered && 'mx-auto'"
                >
                    {{ data.description }}
                </p>
                <div
                    class="mt-8 flex flex-col gap-4 max-sm:mt-4 max-sm:flex-row max-sm:flex-wrap max-sm:items-center max-sm:gap-x-5 max-sm:gap-y-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-7 sm:max-lg:mt-5 lg:mt-7.5"
                    :class="centered && 'justify-center'"
                >
                    <SiteButton
                        v-if="data.primary.url"
                        :href="data.primary.url"
                        class="rounded-[4px]! max-lg:h-11! max-lg:px-5! max-lg:text-[14px]! max-sm:justify-center! sm:w-auto sm:justify-start! lg:min-w-[240px]"
                        >{{ data.primary.label }}</SiteButton
                    >
                    <ArrowLink
                        v-if="data.secondary.url"
                        :href="data.secondary.url"
                        arrow="→"
                        tone="white"
                        class="text-[14px] lg:text-[15px]"
                        >{{ data.secondary.label }}</ArrowLink
                    >
                </div>
            </div>

            <p
                v-if="data.image_caption"
                class="mt-10 text-[11px] leading-[14px] font-semibold text-white uppercase max-sm:hidden xl:absolute xl:top-[436px] xl:right-[215px] xl:mt-0"
                :class="centered && 'text-center xl:static xl:mt-10'"
            >
                {{ data.image_caption }}
            </p>

            <PathwayFinder
                v-if="data.show_finder && records"
                :tabs="data.finder_tabs"
                :help="data.finder_help"
                :records="records"
                variant="bar"
                class="mt-10 max-lg:mt-auto lg:mt-auto"
            />
        </div>
    </BannerMedia>
</template>
