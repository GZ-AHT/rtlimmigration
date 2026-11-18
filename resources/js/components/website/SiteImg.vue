<script setup lang="ts">
import type { SiteImage } from '@/types/website';

/** A CMS image with its stored size (no layout shift) and lazy loading. */
withDefaults(
    defineProps<{ image: SiteImage; eager?: boolean; alt?: string }>(),
    {
        eager: false,
    },
);
</script>

<template>
    <img
        :src="image.url"
        :alt="alt ?? image.alt"
        :width="image.width ?? undefined"
        :height="image.height ?? undefined"
        :loading="eager ? 'eager' : 'lazy'"
        :fetchpriority="eager ? 'high' : undefined"
        decoding="async"
        class="object-cover"
    />
</template>
