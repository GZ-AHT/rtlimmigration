<script setup lang="ts">
/**
 * The Figma section heading: red small caps eyebrow, bold tight heading,
 * muted introduction. Line breaks typed in the CMS are kept.
 */
withDefaults(
    defineProps<{
        eyebrow?: string;
        title?: string;
        description?: string;
        dark?: boolean;
        size?: 'md' | 'lg';
        as?: 'h1' | 'h2';
    }>(),
    { size: 'md', as: 'h2' },
);
</script>

<template>
    <div>
        <p
            v-if="eyebrow"
            class="text-[11px] leading-[14.3px] font-semibold tracking-[0.06em] uppercase"
            :class="dark ? 'text-site-coral' : 'text-site-red'"
        >
            {{ eyebrow }}
        </p>
        <component
            :is="as"
            v-if="title"
            class="mt-3 font-bold whitespace-pre-line"
            :class="[
                dark ? 'text-white' : 'text-site-ink',
                size === 'lg'
                    ? 'text-[34px] leading-[1.1] tracking-[-0.03em] sm:text-[46px] sm:leading-[50px]'
                    : 'text-[30px] leading-[1.1] tracking-[-0.03em] sm:text-[42px] sm:leading-[46px]',
            ]"
        >
            {{ title }}
        </component>
        <p
            v-if="description"
            class="mt-4 max-w-[960px] text-[16px] leading-[1.5] whitespace-pre-line sm:text-[17px]"
            :class="dark ? 'text-site-on-dark' : 'text-site-muted'"
        >
            {{ description }}
        </p>
    </div>
</template>
