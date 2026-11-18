<script setup lang="ts">
import SectionHeading from '@/components/website/SectionHeading.vue';

/** Figma About "Company credentials" (node 12:315): numbered cards. */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        items: { title: string; text: string }[];
    };
    dark?: boolean;
    background?: string;
}>();
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
        <ul class="mt-9 grid gap-6 md:grid-cols-2 lg:mt-[46px] lg:grid-cols-3">
            <li
                v-for="(item, i) in data.items"
                :key="i"
                class="rounded-[6px] px-6 pt-[22px] pb-8 lg:min-h-[178px]"
                :class="
                    dark
                        ? 'bg-site-panel'
                        : background === 'blush' || background === 'sand'
                          ? 'bg-white'
                          : 'bg-site-blush-bg'
                "
            >
                <p class="text-[12px] leading-[18px] font-bold text-site-red">
                    {{ String(i + 1).padStart(2, '0') }}
                </p>
                <h3
                    class="mt-4 text-[19px] leading-[25px] font-semibold"
                    :class="dark ? 'text-white' : 'text-site-ink'"
                >
                    {{ item.title }}
                </h3>
                <p
                    class="mt-[15px] text-[14px] leading-[22px] whitespace-pre-line"
                    :class="dark ? 'text-site-on-dark' : 'text-site-muted'"
                >
                    {{ item.text }}
                </p>
            </li>
        </ul>
    </div>
</template>
