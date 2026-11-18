<script setup lang="ts">
import SiteLink from '@/components/website/SiteLink.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';

/** Figma text links such as "Talk to an adviser ↗" and "Explore our services →". */
withDefaults(
    defineProps<{
        href: string;
        arrow?: string;
        tone?: 'red' | 'ink' | 'white';
        weight?: 'semibold' | 'normal';
    }>(),
    { arrow: '↗', tone: 'red', weight: 'semibold' },
);
</script>

<template>
    <SiteLink
        :href="href"
        class="group inline-flex items-center gap-2.5 underline-offset-4 hover:underline"
        :class="{
            'font-semibold': weight === 'semibold',
            'text-site-red': tone === 'red',
            'text-site-ink': tone === 'ink',
            'text-white': tone === 'white',
        }"
    >
        <slot />
        <span
            aria-hidden="true"
            class="transition-transform group-hover:translate-x-0.5"
            :class="arrow === '↗' && 'group-hover:-translate-y-0.5'"
            ><SiteArrow :glyph="arrow"
        /></span>
    </SiteLink>
</template>
