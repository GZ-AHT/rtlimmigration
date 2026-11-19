<script setup lang="ts">
import NewsletterForm from '@/components/website/NewsletterForm.vue';

/** Figma Blog page "Stay curious. Stay informed." newsletter band. */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        button: string;
        note: string;
    };
    dark?: boolean;
}>();
</script>

<template>
    <div
        class="site-container grid w-full min-w-0 grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_520px] lg:items-start lg:gap-10"
    >
        <div class="w-full min-w-0">
            <p
                v-if="data.eyebrow"
                class="text-[11px] leading-[14px] font-semibold text-site-red uppercase"
            >
                {{ data.eyebrow }}
            </p>
            <h2
                class="mt-4 text-[30px] leading-[1.15] font-bold whitespace-pre-line sm:text-[36px] sm:leading-[41px] lg:mt-[17px]"
                :class="dark ? 'text-white' : 'text-site-ink'"
            >
                {{ data.title }}
            </h2>
            <p
                v-if="data.description"
                class="mt-5 text-[15px] leading-[21px] lg:mt-[24px]"
                :class="dark ? 'text-site-on-dark' : 'text-site-muted'"
            >
                {{ data.description }}
            </p>
        </div>
        <div class="w-full min-w-0 lg:pt-[29px]">
            <NewsletterForm
                :dark="dark"
                :button="data.button || 'Subscribe'"
                source="section"
                combined
            />
            <p
                v-if="data.note"
                class="mt-5 text-[11px] leading-[18px] whitespace-pre-line"
                :class="dark ? 'text-site-on-dark' : 'text-site-muted'"
            >
                {{ data.note }}
            </p>
        </div>
    </div>
</template>
