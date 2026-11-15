<script setup lang="ts">
import { computed } from 'vue';

/**
 * Simple bar chart styled after admin-demo.html (.chart / .bar): CSS only,
 * no chart library. Each bar has an accessible label with its value.
 */
const props = withDefaults(
    defineProps<{
        data: { label: string; value: number; display: string }[];
        height?: number;
        caption: string;
    }>(),
    { height: 210 },
);

const max = computed(() => Math.max(1, ...props.data.map((d) => d.value)));
</script>

<template>
    <figure>
        <figcaption class="sr-only">{{ caption }}</figcaption>
        <div
            class="flex items-end gap-1.5 border-b px-1 pt-5 pb-1 sm:gap-3 sm:px-2.5"
            :style="{
                height: `${height}px`,
                backgroundImage:
                    'repeating-linear-gradient(to bottom, transparent 0, transparent 49px, var(--border) 50px)',
            }"
            role="list"
        >
            <div
                v-for="d in data"
                :key="d.label"
                role="listitem"
                class="group relative min-w-[8px] flex-1 rounded-t-[5px] bg-gradient-to-b from-[#ff6b6b] to-primary transition-opacity hover:opacity-85"
                :style="{
                    height: `${Math.max(d.value ? 3 : 0, (d.value / max) * 100)}%`,
                }"
                :aria-label="`${d.label}: ${d.display}`"
                :title="`${d.label}: ${d.display}`"
            >
                <span
                    class="pointer-events-none absolute -top-6 left-1/2 hidden -translate-x-1/2 rounded bg-foreground px-1.5 py-0.5 text-[10px] whitespace-nowrap text-background group-hover:block"
                    >{{ d.display }}</span
                >
            </div>
        </div>
        <div
            class="flex gap-1.5 px-1 pt-1.5 sm:gap-3 sm:px-2.5"
            aria-hidden="true"
        >
            <span
                v-for="d in data"
                :key="d.label"
                class="min-w-[8px] flex-1 text-center text-[10px] text-muted-foreground"
                >{{ d.label }}</span
            >
        </div>
    </figure>
</template>
