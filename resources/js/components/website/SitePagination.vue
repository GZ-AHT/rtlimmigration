<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Pagination } from '@/types/website';
import SiteArrow from '@/components/website/SiteArrow.vue';

defineProps<{ pagination: Pagination }>();
</script>

<template>
    <nav
        v-if="pagination.last > 1"
        class="mt-12 flex items-center justify-between gap-4 border-t border-site-line pt-6 text-[14px]"
        aria-label="Pages"
    >
        <Link
            v-if="pagination.prev"
            :href="pagination.prev"
            preserve-scroll
            class="font-semibold text-site-red hover:underline"
            ><SiteArrow glyph="←" class="mr-1.5" />Previous</Link
        >
        <span v-else />
        <span class="text-site-muted"
            >Page {{ pagination.current }} of {{ pagination.last }}</span
        >
        <Link
            v-if="pagination.next"
            :href="pagination.next"
            preserve-scroll
            class="font-semibold text-site-red hover:underline"
            >Next <SiteArrow glyph="→" class="ml-1.5"
        /></Link>
        <span v-else />
    </nav>
</template>
