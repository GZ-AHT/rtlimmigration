<script setup lang="ts">
import { computed } from 'vue';

/** Thin progress bar for completeness and checklist progress. */
const props = withDefaults(defineProps<{ value: number; label?: string }>(), {
    label: undefined,
});
const pct = computed(() => Math.max(0, Math.min(100, Math.round(props.value))));
const tone = computed(() =>
    pct.value >= 100
        ? 'bg-success'
        : pct.value >= 60
          ? 'bg-primary'
          : 'bg-warning',
);
</script>

<template>
    <div>
        <div v-if="label" class="mb-1.5 flex justify-between text-xs">
            <span class="text-muted-foreground">{{ label }}</span>
            <b>{{ pct }}%</b>
        </div>
        <div
            class="h-2 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="pct"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="label"
        >
            <div
                class="h-full rounded-full transition-[width]"
                :class="tone"
                :style="{ width: `${pct}%` }"
            />
        </div>
    </div>
</template>
