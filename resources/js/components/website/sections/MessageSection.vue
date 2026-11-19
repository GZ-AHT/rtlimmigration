<script setup lang="ts">
import SiteButton from '@/components/website/SiteButton.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import type { LinkValue, SiteImage } from '@/types/website';

/** Figma About "Leadership message" (node 12:347): portrait beside a message. */
defineProps<{
    data: {
        image: SiteImage | null;
        eyebrow: string;
        title: string;
        description: string;
        text: string;
        highlight: string;
        detail: string;
        button: LinkValue;
    };
    dark?: boolean;
}>();
</script>

<template>
    <div
        class="site-container grid items-start gap-10 lg:grid-cols-[420px_1fr] lg:gap-[84px]"
    >
        <div v-if="data.image" class="relative mx-auto w-full max-w-[420px]">
            <SiteImg
                :image="data.image"
                class="aspect-[420/525] w-full rounded-[8px]"
            />
            <span
                class="absolute top-0 left-0 h-[92px] w-[5px] bg-site-red"
                aria-hidden="true"
            />
        </div>
        <div class="lg:pt-[5px]" :class="!data.image && 'lg:col-span-2'">
            <p
                v-if="data.eyebrow"
                class="text-[11px] leading-[17px] font-semibold text-site-red uppercase"
            >
                {{ data.eyebrow }}
            </p>
            <h2
                class="mt-[15px] text-[32px] leading-[1.15] font-bold whitespace-pre-line sm:text-[40px] sm:leading-[46px]"
                :class="dark ? 'text-white' : 'text-site-ink'"
            >
                {{ data.title }}
            </h2>
            <p
                v-if="data.description"
                class="mt-[27px] max-w-[714px] text-[17px] leading-[27px]"
                :class="dark ? 'text-site-on-dark' : 'text-site-muted'"
            >
                {{ data.description }}
            </p>
            <p
                v-if="data.text"
                class="mt-6 max-w-[714px] text-[16px] leading-[26px]"
                :class="dark ? 'text-site-on-dark' : 'text-site-muted'"
            >
                {{ data.text }}
            </p>
            <div
                v-if="data.highlight || data.detail"
                class="mt-6 border-t border-site-rule pt-[22px]"
            >
                <p
                    v-if="data.highlight"
                    class="text-[18px] leading-[25px] font-semibold"
                    :class="dark ? 'text-white' : 'text-site-ink'"
                >
                    {{ data.highlight }}
                </p>
                <p
                    v-if="data.detail"
                    class="mt-[13px] text-[13px] leading-5 font-medium whitespace-pre-wrap text-site-muted"
                >
                    {{ data.detail }}
                </p>
            </div>
            <SiteButton
                v-if="data.button.url"
                :href="data.button.url"
                class="mt-[23px] h-12 min-w-[214px]"
                >{{ data.button.label }}</SiteButton
            >
        </div>
    </div>
</template>
