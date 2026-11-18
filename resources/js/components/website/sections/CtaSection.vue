<script setup lang="ts">
import SiteButton from '@/components/website/SiteButton.vue';
import type { LinkValue } from '@/types/website';

/** Figma About "Consultation invitation" (node 12:359): red band with a white button. */
defineProps<{
    data: { title: string; description: string; button: LinkValue };
}>();
</script>

<template>
    <div class="bg-site-red">
        <div
            class="site-container flex flex-col gap-6 py-10 lg:min-h-[190px] lg:flex-row lg:items-center lg:justify-between lg:py-[45px]"
        >
            <div>
                <h2
                    class="text-[28px] leading-[1.2] font-bold text-white sm:text-[36px] sm:leading-[43px]"
                >
                    {{ data.title }}
                </h2>
                <p
                    v-if="data.description"
                    class="mt-[18px] text-[16px] leading-6 text-[#fff1f1]"
                >
                    {{ data.description }}
                </p>
            </div>
            <SiteButton
                v-if="data.button.url"
                :href="data.button.url"
                variant="light"
                size="lg"
                class="w-full shrink-0 text-site-red sm:w-[252px]"
                >{{ data.button.label }}</SiteButton
            >
        </div>
    </div>
</template>
