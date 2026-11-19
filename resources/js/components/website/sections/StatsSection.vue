<script setup lang="ts">
import ArrowLink from '@/components/website/ArrowLink.vue';
import type { LinkValue } from '@/types/website';

/** Figma "Why RTL" (node 12:83): key figures with separators. */
defineProps<{
    data: {
        items: { value: string; label: string }[];
        note: string;
        link: LinkValue;
    };
    dark?: boolean;
}>();
</script>

<template>
    <div class="site-container w-full min-w-0">
        <dl
            class="grid w-full min-w-0 grid-cols-2 gap-y-10 lg:grid-cols-4 lg:pt-[5px]"
        >
            <div
                v-for="(item, i) in data.items"
                :key="i"
                class="lg:border-l lg:border-[#ded5d1] lg:pl-6 lg:first:border-0 lg:first:pl-0"
                :class="i % 2 === 1 && 'border-l border-[#ded5d1] pl-5 lg:pl-6'"
            >
                <dt class="sr-only">{{ item.label }}</dt>
                <dd>
                    <span
                        class="block text-[40px] leading-none font-bold tracking-[-2px] text-site-red sm:text-[54px] sm:leading-[54.05px]"
                        >{{ item.value }}</span
                    >
                    <span
                        class="mt-4 block text-[15px] leading-[21px] font-medium lg:mt-[21px]"
                        :class="dark ? 'text-white' : 'text-site-ink'"
                        >{{ item.label }}</span
                    >
                </dd>
            </div>
        </dl>
        <div
            v-if="data.note || data.link.url"
            class="mt-10 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between lg:mt-[52px]"
        >
            <p class="text-[11px] leading-[14.3px] text-site-muted">
                {{ data.note }}
            </p>
            <ArrowLink
                v-if="data.link.url"
                :href="data.link.url"
                tone="ink"
                class="text-[13px] sm:w-[258px]"
                >{{ data.link.label }}</ArrowLink
            >
        </div>
    </div>
</template>
