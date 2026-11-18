<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { inject } from 'vue';
import SiteButton from '@/components/website/SiteButton.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import type { LinkValue, SiteImage } from '@/types/website';

/**
 * Figma About "About hero" (node 12:265): breadcrumb, large heading, text,
 * button and a photo on a coloured panel with a red edge. Without a photo
 * it is a shorter page header for the derived pages.
 */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        button: LinkValue;
        footnote: string;
        image: SiteImage | null;
    };
}>();

const pageTitle = inject<string>('pageTitle', '');
</script>

<template>
    <div class="relative overflow-hidden">
        <div
            v-if="data.image"
            class="absolute inset-y-0 right-0 hidden w-[37.2%] bg-site-blush-panel lg:block"
            aria-hidden="true"
        />
        <div
            class="relative site-container grid gap-10 pt-8 pb-12 lg:pt-[43px]"
            :class="
                data.image
                    ? 'lg:min-h-[560px] lg:grid-cols-[1fr_360px] lg:gap-16 lg:pb-[40px]'
                    : 'lg:pb-[64px]'
            "
        >
            <div>
                <nav aria-label="Breadcrumb">
                    <ol
                        class="flex gap-3 text-[11px] leading-[17px] font-semibold text-site-muted uppercase"
                    >
                        <li>
                            <Link href="/" class="hover:text-site-red"
                                >Home</Link
                            >
                        </li>
                        <li aria-hidden="true">/</li>
                        <li aria-current="page">{{ pageTitle }}</li>
                    </ol>
                </nav>
                <p
                    v-if="data.eyebrow"
                    class="mt-8 text-[11px] leading-[17px] font-semibold text-site-red uppercase lg:mt-[34px]"
                >
                    {{ data.eyebrow }}
                </p>
                <h1
                    class="mt-4 text-[40px] leading-[1.1] font-bold tracking-[-0.03em] whitespace-pre-line text-site-ink sm:text-[52px] lg:mt-[22px] lg:text-[64px] lg:leading-[70px]"
                >
                    {{ data.title }}
                </h1>
                <p
                    v-if="data.description"
                    class="mt-6 max-w-[670px] text-[17px] leading-[1.6] text-site-muted sm:text-[20px] sm:leading-[30px] lg:mt-[33px]"
                >
                    {{ data.description }}
                </p>
                <SiteButton
                    v-if="data.button.url"
                    :href="data.button.url"
                    :arrow="data.button.url.startsWith('#') ? '↓' : '↗'"
                    class="mt-9 h-[52px] min-w-[208px] rounded-site-btn lg:mt-[46px]"
                    >{{ data.button.label }}</SiteButton
                >
                <p
                    v-if="data.footnote"
                    class="mt-8 text-[10px] leading-[15px] font-semibold text-site-muted uppercase lg:mt-[35px]"
                >
                    {{ data.footnote }}
                </p>
            </div>
            <div
                v-if="data.image"
                class="relative mx-auto w-full max-w-[360px] lg:mt-[-3px] lg:mr-[25px]"
            >
                <SiteImg
                    :image="data.image"
                    eager
                    class="aspect-[360/480] w-full rounded-[8px]"
                />
                <span
                    class="absolute top-8 -right-[7px] h-[150px] w-[7px] bg-site-red"
                    aria-hidden="true"
                />
            </div>
        </div>
    </div>
</template>
