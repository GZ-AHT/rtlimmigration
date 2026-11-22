<script setup lang="ts">
import { ref, useId } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';

/**
 * Phones and tablets (under 1024px): the search / filter form folds into one
 * compact "Search & Filter" button and opens on tap (closed by default).
 * From 1024px up both wrappers use display: contents, so the desktop form
 * renders exactly as if this component were not there.
 */
withDefaults(
    defineProps<{
        label?: string;
        /** Short hint shown beside the label, e.g. the chosen tab. */
        summary?: string;
        /** Number of filters currently chosen. */
        count?: number;
        /** A stand-alone bordered button; leave off when it sits inside a card. */
        framed?: boolean;
    }>(),
    { label: 'Search & Filter', summary: '', count: 0, framed: false },
);

const open = ref(false);
const panelId = useId();
</script>

<template>
    <div class="lg:contents">
        <button
            type="button"
            class="flex h-12 w-full items-center gap-2.5 px-3 text-left transition-colors hover:bg-site-sand/60 focus-visible:ring-2 focus-visible:ring-site-red/40 focus-visible:outline-none focus-visible:ring-inset lg:hidden"
            :class="
                framed &&
                'rounded-xl border border-site-warm-line bg-white shadow-[0_6px_18px_rgba(0,0,0,0.06)]'
            "
            :aria-expanded="open"
            :aria-controls="panelId"
            @click="open = !open"
        >
            <span
                class="grid size-8 shrink-0 place-items-center rounded-full bg-site-red text-white"
            >
                <AppIcon name="search" :size="18" />
            </span>
            <span class="text-[14px] font-semibold text-site-ink">{{
                label
            }}</span>
            <span
                v-if="count > 0"
                class="grid h-5 min-w-5 place-items-center rounded-full bg-site-red-text/10 px-1.5 text-[11px] font-bold text-site-red-text"
                >{{ count }}</span
            >
            <span
                v-if="summary"
                class="min-w-0 truncate text-[12px] text-site-muted"
                >{{ summary }}</span
            >
            <AppIcon
                name="expand_more"
                :size="22"
                class="ml-auto text-site-muted transition-transform duration-200 motion-reduce:transition-none"
                :class="open && 'rotate-180'"
            />
        </button>
        <div
            :id="panelId"
            :class="open ? '' : 'max-lg:hidden'"
            class="lg:contents"
        >
            <slot />
        </div>
    </div>
</template>
