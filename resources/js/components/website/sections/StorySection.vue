<script setup lang="ts">
import SectionHeading from '@/components/website/SectionHeading.vue';

/** Figma About "Company story" (node 12:276): text beside a numbered list. */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        text: string;
        list_title: string;
        items: { title: string; text: string }[];
    };
    dark?: boolean;
}>();
</script>

<template>
    <div
        class="site-container grid gap-10 lg:grid-cols-[591px_559px] lg:justify-between"
    >
        <div>
            <SectionHeading
                :eyebrow="data.eyebrow"
                :title="data.title"
                :dark="dark"
                class="[&_h2]:sm:text-[39px] [&_h2]:sm:leading-[45px] [&_h2]:sm:tracking-normal"
            />
            <p
                v-if="data.description"
                class="mt-7 text-[17px] leading-7"
                :class="dark ? 'text-site-on-dark' : 'text-site-muted'"
            >
                {{ data.description }}
            </p>
            <p
                v-if="data.text"
                class="mt-6 text-[15px] leading-[25px]"
                :class="dark ? 'text-site-on-dark' : 'text-site-muted'"
            >
                {{ data.text }}
            </p>
        </div>
        <div class="lg:pt-[3px]">
            <h3
                v-if="data.list_title"
                class="text-[21px] leading-[30px] font-semibold sm:text-[23px]"
                :class="dark ? 'text-white' : 'text-site-ink'"
            >
                {{ data.list_title }}
            </h3>
            <ol class="mt-[26px]">
                <li
                    v-for="(item, i) in data.items"
                    :key="i"
                    class="grid grid-cols-[50px_1fr] border-t border-site-rule pt-[15px] pb-[25px]"
                >
                    <span
                        class="pt-[5px] text-[12px] leading-[18px] font-semibold text-site-red"
                        >{{ String(i + 1).padStart(2, '0') }}</span
                    >
                    <span>
                        <span
                            class="block text-[17px] leading-[26px] font-semibold"
                            :class="dark ? 'text-white' : 'text-site-ink'"
                            >{{ item.title }}</span
                        >
                        <span
                            class="mt-[2px] block text-[13px] leading-5"
                            :class="
                                dark ? 'text-site-on-dark' : 'text-site-muted'
                            "
                            >{{ item.text }}</span
                        >
                    </span>
                </li>
            </ol>
        </div>
    </div>
</template>
