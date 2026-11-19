<script setup lang="ts">
import { computed } from 'vue';
import ArrowLink from '@/components/website/ArrowLink.vue';
import BannerMedia from '@/components/website/BannerMedia.vue';
import PathwayFinder from '@/components/website/PathwayFinder.vue';
import SiteButton from '@/components/website/SiteButton.vue';
import { overlayStyle } from '@/components/website/banner';
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
            class="absolute inset-0"
            :style="overlay"
            aria-hidden="true"
        />
        <div
            class="relative site-container flex w-full min-w-0 flex-col pt-8 pb-6 sm:pt-12 sm:pb-8 lg:pt-[57px] lg:pb-[33px]"
            :class="HEIGHTS[data.height] ?? HEIGHTS.standard"
        >
            <div
                class="w-full max-w-[650px] min-w-0"
                :class="centered && 'mx-auto text-center'"
            >
                <p
                    v-if="data.eyebrow"
                    class="text-[10px] font-bold tracking-[1px] text-white uppercase sm:text-[12px] sm:leading-[15px]"
                >
                    {{ data.eyebrow }}
                </p>
                <h1
                    class="mt-2.5 text-[28px] leading-[1.12] font-bold tracking-[-0.02em] whitespace-pre-line text-white sm:mt-6 sm:text-[48px] sm:leading-[1.05] lg:mt-6.25 lg:text-[66px] lg:leading-[73px] lg:tracking-[-0.5px]"
                >
                    {{ data.title
                    }}<template v-if="data.title_accent"
                        >{{ '\n' }}{{ data.title_accent }}</template
                    >
                </h1>
                <p
                    v-if="data.description"
                    class="mt-2.5 line-clamp-2 text-[14px] leading-relaxed text-white/90 sm:mt-6 sm:line-clamp-none sm:max-w-[540px] sm:text-[17px] sm:leading-[29px] lg:mt-7 lg:whitespace-pre-line"
                    :class="centered && 'mx-auto'"
                >
                    {{ data.description }}
                </p>
                <div
                    class="mt-5 flex flex-col gap-3 sm:mt-8 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-7 lg:mt-7.5"
                    :class="centered && 'justify-center'"
                >
                    <SiteButton
                        v-if="data.primary.url"
                        :href="data.primary.url"
                        class="w-full justify-center! rounded-[4px]! sm:w-auto sm:min-w-[240px] sm:justify-start!"
                        >{{ data.primary.label }}</SiteButton
                    >
                    <ArrowLink
                        v-if="data.secondary.url"
                        :href="data.secondary.url"
                        arrow="→"
                        tone="white"
                        class="hidden text-[14px] sm:inline-flex sm:text-[15px]"
                        >{{ data.secondary.label }}</ArrowLink
                    >
                </div>
            </div>

            <p
                v-if="data.image_caption"
                class="mt-4 hidden text-[10px] font-semibold tracking-wider text-white uppercase sm:mt-10 sm:block sm:text-[11px] sm:leading-[14px] xl:absolute xl:top-[436px] xl:right-[215px] xl:mt-0"
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
                class="mt-6 sm:mt-10 lg:mt-auto"
            />
        </div>
    </BannerMedia>
</template>
