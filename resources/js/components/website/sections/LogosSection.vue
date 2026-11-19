<script setup lang="ts">
import SectionHeading from '@/components/website/SectionHeading.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import type { SiteImage } from '@/types/website';

/** Partner logos (REQ §21.7; not in Figma). */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        items: { image: SiteImage | null; name: string; url: string }[];
    };
}>();
</script>

<template>
    <div class="site-container w-full min-w-0">
        <SectionHeading
            v-if="data.title || data.eyebrow"
            :eyebrow="data.eyebrow"
            :title="data.title"
            :description="data.description"
            class="text-center [&_p]:mx-auto"
        />
        <div class="w-full min-w-0 overflow-hidden">
            <ul
                class="-mx-4 mt-9 no-scrollbar flex snap-x snap-mandatory items-center gap-4 overflow-x-auto px-4 pt-1 pb-3 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0 sm:pt-0 sm:pb-0 lg:grid-cols-6"
            >
                <li
                    v-for="(logo, i) in data.items"
                    :key="i"
                    class="grid h-24 w-[42vw] max-w-[160px] shrink-0 snap-start place-items-center rounded-2xl bg-white p-4 sm:w-auto sm:max-w-none"
                >
                    <component
                        :is="logo.url ? 'a' : 'span'"
                        v-bind="
                            logo.url
                                ? {
                                      href: logo.url,
                                      target: '_blank',
                                      rel: 'noopener noreferrer',
                                  }
                                : {}
                        "
                        class="grid size-full place-items-center"
                    >
                        <SiteImg
                            v-if="logo.image"
                            :image="logo.image"
                            :alt="logo.name || logo.image.alt"
                            class="max-h-14 w-auto object-contain opacity-80 grayscale transition hover:opacity-100 hover:grayscale-0"
                        />
                        <span
                            v-else
                            class="text-center text-[14px] font-semibold text-site-muted"
                            >{{ logo.name }}</span
                        >
                    </component>
                </li>
            </ul>
        </div>
    </div>
</template>
