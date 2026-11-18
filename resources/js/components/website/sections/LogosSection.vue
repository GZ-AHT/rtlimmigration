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
    <div class="site-container">
        <SectionHeading
            v-if="data.title || data.eyebrow"
            :eyebrow="data.eyebrow"
            :title="data.title"
            :description="data.description"
            class="text-center [&_p]:mx-auto"
        />
        <ul
            class="mt-9 grid grid-cols-2 items-center gap-4 sm:grid-cols-3 lg:grid-cols-6"
        >
            <li
                v-for="(logo, i) in data.items"
                :key="i"
                class="grid h-24 place-items-center rounded-[16px] bg-white p-4"
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
</template>
