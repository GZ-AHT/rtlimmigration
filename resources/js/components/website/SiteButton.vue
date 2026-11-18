<script setup lang="ts">
import SiteLink from '@/components/website/SiteLink.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';

/**
 * Figma buttons: red (primary), white on red bands (light) and dark. The
 * arrow glyphs (↗ →) are part of the Figma labels.
 */
withDefaults(
    defineProps<{
        href: string;
        variant?: 'primary' | 'light' | 'dark';
        size?: 'md' | 'lg' | 'tall';
        arrow?: string;
    }>(),
    { variant: 'primary', size: 'md', arrow: '↗' },
);
</script>

<template>
    <SiteLink
        :href="href"
        class="inline-flex items-center justify-center gap-2.5 rounded-site-btn px-5 font-semibold whitespace-nowrap transition-colors focus-visible:ring-4 focus-visible:outline-none"
        :class="[
            size === 'lg'
                ? 'h-[54px] text-[15px]'
                : size === 'tall'
                  ? 'h-[54px] text-[14px]'
                  : 'h-[50px] text-[14px]',
            variant === 'primary' &&
                'bg-site-red text-white hover:bg-site-red-dark focus-visible:ring-site-red/30',
            variant === 'light' &&
                'bg-white text-site-red-deep hover:bg-site-sand focus-visible:ring-white/40',
            variant === 'dark' &&
                'bg-site-ink text-white hover:bg-black focus-visible:ring-site-ink/30',
        ]"
    >
        <slot />
        <SiteArrow v-if="arrow" :glyph="arrow" />
    </SiteLink>
</template>
