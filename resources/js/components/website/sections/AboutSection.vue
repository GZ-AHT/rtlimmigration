<script setup lang="ts">
import ArrowLink from '@/components/website/ArrowLink.vue';
import SectionHeading from '@/components/website/SectionHeading.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import type { LinkValue, SiteImage } from '@/types/website';

/** Figma "About and guidance" (node 12:97): photo with caption, text and numbered steps. */
defineProps<{
    data: {
        image: SiteImage | null;
        caption_eyebrow: string;
        caption: string;
        eyebrow: string;
        title: string;
        description: string;
        steps: { title: string; text: string }[];
        link: LinkValue;
    };
    dark?: boolean;
}>();
</script>

<template>
    <div
        class="site-container grid w-full min-w-0 grid-cols-1 items-center gap-10 lg:grid-cols-[588px_1fr] lg:gap-[76px]"
    >
        <div v-if="data.image" class="relative w-full min-w-0">
            <SiteImg
                :image="data.image"
                class="aspect-[588/496] w-full rounded-[24px]"
            />
            <div
                v-if="data.caption || data.caption_eyebrow"
                class="absolute right-4 bottom-4 left-4 rounded-[16px] bg-white px-[22px] py-4 sm:right-6 sm:bottom-7 sm:left-6"
            >
                <p
                    class="text-[11px] leading-[14.3px] font-semibold text-site-red uppercase"
                >
                    {{ data.caption_eyebrow }}
                </p>
                <p
                    class="mt-2 text-[15px] leading-[19.5px] font-medium text-site-ink"
                >
                    {{ data.caption }}
                </p>
            </div>
        </div>
        <div class="w-full min-w-0" :class="!data.image && 'lg:col-span-2'">
            <SectionHeading
                :eyebrow="data.eyebrow"
                :title="data.title"
                :dark="dark"
                size="lg"
                class="[&_h2]:mt-4"
            />
            <p
                v-if="data.description"
                class="mt-[22px] max-w-[568px] text-[16px] leading-[25px]"
                :class="dark ? 'text-[#cfd0d3]' : 'text-site-muted'"
            >
                {{ data.description }}
            </p>
            <ol
                v-if="data.steps.length"
                class="mt-[18px] flex flex-col gap-[21px]"
            >
                <li
                    v-for="(step, i) in data.steps"
                    :key="i"
                    class="grid grid-cols-[40px_1fr] gap-[15px]"
                >
                    <span
                        class="grid h-8 place-items-center rounded-[10px] text-[12px] leading-[17.4px] font-semibold"
                        :class="
                            dark
                                ? 'bg-site-chip text-site-coral'
                                : 'bg-site-sand text-site-red'
                        "
                        >{{ String(i + 1).padStart(2, '0') }}</span
                    >
                    <span>
                        <span
                            class="block text-[15px] leading-[21.75px] font-semibold"
                            :class="dark ? 'text-white' : 'text-site-ink'"
                            >{{ step.title }}</span
                        >
                        <span
                            class="block text-[13px] leading-[16.9px]"
                            :class="
                                dark ? 'text-site-soft-gray' : 'text-site-muted'
                            "
                            >{{ step.text }}</span
                        >
                    </span>
                </li>
            </ol>
            <ArrowLink
                v-if="data.link.url"
                :href="data.link.url"
                :tone="dark ? 'white' : 'red'"
                class="mt-[42px] text-[15px]"
                >{{ data.link.label }}</ArrowLink
            >
        </div>
    </div>
</template>
