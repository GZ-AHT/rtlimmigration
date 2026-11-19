<script setup lang="ts">
import AppIcon from '@/components/panel/AppIcon.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import type { LinkValue } from '@/types/website';

/**
 * Figma Contact "Meet us in Dhaka": address beside a map (OpenStreetMap,
 * loaded only when scrolled near), a Google Maps link, and tips for the
 * first conversation.
 */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        note: string;
        map_link: LinkValue;
        tips_title: string;
        tips: { text: string }[];
        tips_note: string;
    };
    records: { address: string; embed: string | null; map_url: string } | null;
}>();
</script>

<template>
    <div class="site-container">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_744px] lg:gap-10">
            <div class="lg:pt-1">
                <p
                    v-if="data.eyebrow"
                    class="text-[11px] leading-3.5 font-semibold text-site-red uppercase"
                >
                    {{ data.eyebrow }}
                </p>
                <h2
                    class="mt-4 text-[28px] leading-[1.15] font-bold whitespace-pre-line text-site-ink sm:text-[30px] sm:leading-9 lg:mt-3"
                >
                    {{ data.title }}
                </h2>
                <p
                    v-if="records?.address"
                    class="mt-5 inline-flex items-start gap-2 text-[17px] leading-6 font-semibold text-site-ink lg:mt-3.75"
                >
                    <AppIcon
                        name="location_on"
                        :size="20"
                        class="mt-0.5 shrink-0 text-site-red"
                    />
                    <span>{{ records.address }}</span>
                </p>
                <p
                    v-if="data.note"
                    class="mt-1.25 text-[14px] leading-5 text-site-muted"
                >
                    {{ data.note }}
                </p>
            </div>
            <div
                class="relative h-60 overflow-hidden rounded-2xl bg-site-line lg:h-50"
            >
                <iframe
                    v-if="records?.embed"
                    :src="records.embed"
                    :title="`Map: ${records.address}`"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    class="absolute inset-0 size-full border-0"
                />
                <a
                    v-if="records?.map_url"
                    :href="records.map_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="absolute top-4 left-4 flex h-13 items-center gap-2.5 rounded-xl bg-white px-4 text-[15px] font-semibold text-site-red shadow-sm hover:underline sm:w-62"
                >
                    <AppIcon
                        name="map"
                        :size="18"
                        class="shrink-0 text-site-red"
                    />
                    <span>{{
                        data.map_link.label || 'Open in Google Maps'
                    }}</span>
                    <SiteArrow class="ml-auto" />
                </a>
            </div>
        </div>

        <div v-if="data.tips.length || data.tips_title" class="mt-8 lg:mt-3.75">
            <p
                v-if="data.tips_title"
                class="text-[11px] leading-3.5 font-semibold text-site-red uppercase"
            >
                {{ data.tips_title }}
            </p>
            <ol class="mt-5 grid gap-3 md:grid-cols-3 lg:mt-4.75">
                <li
                    v-for="(tip, i) in data.tips"
                    :key="i"
                    class="flex gap-4 text-[14px] leading-5 font-medium text-site-ink"
                >
                    <span
                        class="pt-px text-[11px] font-semibold text-site-red"
                        >{{ String(i + 1).padStart(2, '0') }}</span
                    >
                    {{ tip.text }}
                </li>
            </ol>
            <p
                v-if="data.tips_note"
                class="mt-3 text-[12px] leading-4 text-site-muted"
            >
                {{ data.tips_note }}
            </p>
        </div>
    </div>
</template>
