<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Paginated } from '@/types';

/** Footer for a server-paginated table (admin-demo.html .pagination). */
const props = defineProps<{
    meta: Omit<Paginated<unknown>, 'data'>;
}>();

// Laravel labels the first/last links "&laquo; Previous" / "Next &raquo;".
const pages = computed(() => props.meta.links.slice(1, -1));
const previous = computed(() => props.meta.links[0]);
const next = computed(() => props.meta.links[props.meta.links.length - 1]);

const linkBase =
    'grid h-8 min-w-8 place-items-center rounded-md border px-2 text-xs font-semibold';
</script>

<template>
    <nav
        v-if="meta.total > 0"
        class="flex flex-col items-center justify-between gap-3 border-t px-5 py-4 text-xs text-muted-foreground sm:flex-row"
        aria-label="Pagination"
    >
        <p>
            Showing <b class="text-foreground">{{ meta.from }}</b
            >–<b class="text-foreground">{{ meta.to }}</b> of
            <b class="text-foreground">{{ meta.total }}</b>
        </p>
        <div
            v-if="meta.last_page > 1"
            class="flex flex-wrap items-center gap-1"
        >
            <component
                :is="previous?.url ? Link : 'span'"
                :href="previous?.url ?? undefined"
                preserve-scroll
                preserve-state
                :class="[linkBase, !previous?.url && 'opacity-40']"
                aria-label="Previous page"
                >‹</component
            >
            <template v-for="link in pages" :key="link.label">
                <span v-if="!link.url" class="px-1">…</span>
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    :aria-current="link.active ? 'page' : undefined"
                    :class="[
                        linkBase,
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'bg-card hover:border-ring hover:text-accent-foreground',
                    ]"
                    >{{ link.label }}</Link
                >
            </template>
            <component
                :is="next?.url ? Link : 'span'"
                :href="next?.url ?? undefined"
                preserve-scroll
                preserve-state
                :class="[linkBase, !next?.url && 'opacity-40']"
                aria-label="Next page"
                >›</component
            >
        </div>
    </nav>
</template>
