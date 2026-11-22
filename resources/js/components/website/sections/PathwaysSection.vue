<script setup lang="ts">
import ArrowLink from '@/components/website/ArrowLink.vue';
import SectionHeading from '@/components/website/SectionHeading.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import SiteLink from '@/components/website/SiteLink.vue';
import type { LinkValue, SiteImage } from '@/types/website';
import SiteArrow from '@/components/website/SiteArrow.vue';

/** Figma "Education pathways" (node 12:60). Every second card is dark, as in the design. */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        link: LinkValue;
        items: {
            image: SiteImage | null;
            eyebrow: string;
            title: string;
            text: string;
            link: LinkValue;
        }[];
    };
}>();
</script>

<template>
    <div class="site-container">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <SectionHeading
                :eyebrow="data.eyebrow"
                :title="data.title"
                :description="data.description"
                class="[&_h2]:sm:text-[48px] [&_h2]:sm:tracking-[-1.8px]"
            />
            <ArrowLink
                v-if="data.link.url"
                :href="data.link.url"
                class="shrink-0 text-[14px] lg:mb-[7px] lg:w-[210px]"
                >{{ data.link.label }}</ArrowLink
            >
        </div>

        <ul
            class="-mx-4 mt-9 no-scrollbar flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pt-1 pb-4 max-sm:mt-6 sm:-mx-6 sm:px-6 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 md:pt-0 md:pb-0 lg:mt-11 lg:grid-cols-3 lg:gap-6"
        >
            <li
                v-for="(item, i) in data.items"
                :key="i"
                class="w-[78vw] max-w-[340px] shrink-0 snap-start sm:w-[60vw] md:w-auto md:max-w-none"
            >
                <component
                    :is="item.link.url ? SiteLink : 'div'"
                    v-bind="item.link.url ? { href: item.link.url } : {}"
                    class="group flex h-full flex-col rounded-[22px] p-[14px] transition-shadow hover:shadow-[0_12px_30px_rgba(0,0,0,0.1)] lg:min-h-[370px]"
                    :class="i % 2 === 1 ? 'bg-site-navy' : 'bg-site-sand'"
                >
                    <SiteImg
                        v-if="item.image"
                        :image="item.image"
                        class="h-[190px] w-full rounded-[14px] max-sm:h-[140px]"
                    />
                    <div
                        class="px-[10px] pt-[21px] pb-3 max-sm:pt-4 max-sm:pb-2"
                    >
                        <p
                            v-if="item.eyebrow"
                            class="text-[11px] leading-[13px] font-semibold tracking-[0.8px] uppercase"
                            :class="
                                i % 2 === 1
                                    ? 'text-site-rose'
                                    : 'text-site-muted'
                            "
                        >
                            {{ item.eyebrow }}
                        </p>
                        <h3 class="mt-3 flex items-start justify-between gap-4">
                            <span
                                class="text-[28px] leading-[31.2px] font-bold max-sm:text-[24px] max-sm:leading-[28px]"
                                :class="
                                    i % 2 === 1 ? 'text-white' : 'text-site-ink'
                                "
                                >{{ item.title }}</span
                            >
                            <span
                                v-if="item.link.url"
                                aria-hidden="true"
                                class="text-[25px] leading-[30px] font-medium transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                                :class="
                                    i % 2 === 1
                                        ? 'text-white'
                                        : 'text-site-red-text'
                                "
                                ><SiteArrow
                            /></span>
                        </h3>
                        <p
                            v-if="item.text"
                            class="mt-[15px] text-[14px] leading-[22.4px] whitespace-pre-line max-sm:mt-2 max-sm:line-clamp-2"
                            :class="
                                i % 2 === 1
                                    ? 'text-[#d2d5db]'
                                    : 'text-site-muted'
                            "
                        >
                            {{ item.text }}
                        </p>
                    </div>
                </component>
            </li>
        </ul>
    </div>
</template>
