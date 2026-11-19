<script setup lang="ts">
import { ref } from 'vue';
import SectionHeading from '@/components/website/SectionHeading.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import type { SiteImage } from '@/types/website';

/** Figma About "Success gallery" (node 12:331): tall image cards; click to enlarge. */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        items: { image: SiteImage | null; caption: string }[];
    };
    dark?: boolean;
}>();

const zoomed = ref<{ image: SiteImage; caption: string } | null>(null);
</script>

<template>
    <div class="site-container">
        <SectionHeading
            :eyebrow="data.eyebrow"
            :title="data.title"
            :description="data.description"
            :dark="dark"
            class="[&_h2]:sm:text-[39px] [&_h2]:sm:leading-[47px] [&_h2]:sm:tracking-normal"
        />
        <ul
            class="-mx-4 mt-9 no-scrollbar flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pt-1 pb-4 sm:-mx-6 sm:px-6 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 md:pt-0 md:pb-0 lg:mt-6.25 lg:grid-cols-4 lg:gap-6"
        >
            <li
                v-for="(item, i) in data.items"
                :key="i"
                class="w-[68vw] max-w-[260px] shrink-0 snap-start sm:w-[45vw] md:w-auto md:max-w-none"
            >
                <button
                    v-if="item.image"
                    type="button"
                    class="flex w-full flex-col rounded-[6px] bg-white px-3 pt-3 pb-4 text-left transition-shadow hover:shadow-[0_12px_30px_rgba(0,0,0,0.1)] sm:px-[26px]"
                    :aria-label="`Enlarge ${item.caption || item.image.alt}`"
                    @click="
                        zoomed = { image: item.image, caption: item.caption }
                    "
                >
                    <SiteImg
                        :image="item.image"
                        class="aspect-[242/344] w-full rounded-[3px]"
                    />
                    <span
                        class="mt-[9px] text-[12px] leading-[17px] font-semibold text-site-ink sm:-mx-[9px]"
                        >{{ item.caption }}</span
                    >
                </button>
            </li>
        </ul>

        <div
            v-if="zoomed"
            class="fixed inset-0 z-50 grid place-items-center bg-site-ink/80 p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="zoomed.caption || 'Image'"
            @click.self="zoomed = null"
            @keydown.esc="zoomed = null"
        >
            <figure class="relative max-h-full">
                <SiteImg
                    :image="zoomed.image"
                    eager
                    class="max-h-[85vh] w-auto rounded-[8px] object-contain"
                />
                <figcaption
                    v-if="zoomed.caption"
                    class="mt-3 text-center text-[14px] font-medium text-white"
                >
                    {{ zoomed.caption }}
                </figcaption>
                <button
                    v-focus
                    type="button"
                    class="absolute -top-3 -right-3 grid size-10 place-items-center rounded-full bg-white text-site-ink shadow"
                    aria-label="Close"
                    @click="zoomed = null"
                >
                    ✕
                </button>
            </figure>
        </div>
    </div>
</template>
