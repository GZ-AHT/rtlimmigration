<script setup lang="ts">
import SectionHeading from '@/components/website/SectionHeading.vue';

/** Figma About "Mission and vision" (node 12:298): a dark and a blush panel, then the values. */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        cards: {
            eyebrow: string;
            title: string;
            text: string;
            text2: string;
        }[];
        values: { label: string }[];
    };
    dark?: boolean;
}>();
</script>

<template>
    <div class="site-container">
        <SectionHeading
            :eyebrow="data.eyebrow"
            :title="data.title"
            :dark="dark"
            class="[&_h2]:mt-[14px] [&_h2]:sm:text-[40px] [&_h2]:sm:leading-[48px] [&_h2]:sm:tracking-normal"
            :class="dark && '[&>p:first-child]:text-[#ff9ca2]'"
        />
        <div
            class="-mx-4 mt-10 no-scrollbar flex snap-x snap-mandatory gap-6 overflow-x-auto px-4 pt-1 pb-4 sm:-mx-6 sm:px-6 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 md:pt-0 md:pb-0"
        >
            <article
                v-for="(card, i) in data.cards"
                :key="i"
                class="w-[85vw] max-w-[400px] shrink-0 snap-start rounded-[8px] px-6 pt-7.5 pb-10 sm:w-[70vw] sm:px-8 md:w-auto md:max-w-none lg:min-h-100.5"
                :class="i === 0 ? 'bg-site-panel' : 'bg-site-blush-panel'"
            >
                <p
                    class="text-[11px] leading-[17px] font-semibold whitespace-pre uppercase"
                    :class="i === 0 ? 'text-[#ff9ca2]' : 'text-site-red'"
                >
                    {{ card.eyebrow }}
                </p>
                <h3
                    class="mt-[23px] text-[28px] leading-[1.15] font-bold whitespace-pre-line sm:text-[32px] sm:leading-[37px]"
                    :class="i === 0 ? 'text-white' : 'text-site-ink'"
                >
                    {{ card.title }}
                </h3>
                <p
                    v-if="card.text"
                    class="mt-5 text-[16px] leading-[26px]"
                    :class="i === 0 ? 'text-[#d5d5d9]' : 'text-site-muted'"
                >
                    {{ card.text }}
                </p>
                <p
                    v-if="card.text2"
                    class="mt-[11px] text-[15px] leading-6"
                    :class="i === 0 ? 'text-[#d5d5d9]' : 'text-site-muted'"
                >
                    {{ card.text2 }}
                </p>
            </article>
        </div>
        <ul
            v-if="data.values.length"
            class="mt-[30px] grid grid-cols-2 gap-4 lg:grid-cols-4"
        >
            <li
                v-for="(v, i) in data.values"
                :key="i"
                class="text-[10px] leading-[15px] font-semibold uppercase"
                :class="[
                    dark ? 'text-[#d5d5d9]' : 'text-site-muted',
                    i % 2 === 1 && 'lg:pl-[40px]',
                    i === data.values.length - 1 && 'lg:text-right',
                ]"
            >
                {{ v.label }}
            </li>
        </ul>
    </div>
</template>
