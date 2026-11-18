<script setup lang="ts">
import ArrowLink from '@/components/website/ArrowLink.vue';
import SectionHeading from '@/components/website/SectionHeading.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import type { LinkValue, SiteImage } from '@/types/website';

/** Photo beside formatted text (REQ §5 page builder). Rich text is cleaned on the server. */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        content: string;
        image: SiteImage | null;
        image_side: 'left' | 'right';
        link: LinkValue;
    };
    dark?: boolean;
}>();
</script>

<template>
    <div
        class="site-container grid items-center gap-10 lg:grid-cols-2 lg:gap-[76px]"
    >
        <SiteImg
            v-if="data.image"
            :image="data.image"
            class="aspect-[588/496] w-full rounded-[24px]"
            :class="data.image_side === 'right' && 'lg:order-2'"
        />
        <div :class="!data.image && 'lg:col-span-2 lg:max-w-[800px]'">
            <SectionHeading
                :eyebrow="data.eyebrow"
                :title="data.title"
                :dark="dark"
            />
            <!-- eslint-disable-next-line vue/no-v-html -- sanitised server-side by App\Support\Content\RichText -->
            <div
                v-if="data.content"
                class="site-prose mt-6"
                :class="dark && 'text-site-on-dark [&_strong]:text-white'"
                v-html="data.content"
            />
            <ArrowLink
                v-if="data.link.url"
                :href="data.link.url"
                :tone="dark ? 'white' : 'red'"
                class="mt-8 text-[15px]"
                >{{ data.link.label }}</ArrowLink
            >
        </div>
    </div>
</template>
