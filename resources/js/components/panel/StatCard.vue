<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import { formatNumber } from '@/lib/format';
import type { Tone } from '@/types';

/** KPI tile from admin-demo.html (.stat). */
const props = withDefaults(
    defineProps<{
        label: string;
        value: number | string;
        icon: string;
        hint?: string;
        hintTone?: Tone;
    }>(),
    { hintTone: 'success' },
);

const formatted = computed(() =>
    typeof props.value === 'number' ? formatNumber(props.value) : props.value,
);

const hintClass: Record<Tone, string> = {
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    info: 'text-info',
    neutral: 'text-muted-foreground',
};
</script>

<template>
    <div
        class="min-h-[112px] rounded-xl border bg-card p-3.5 shadow-panel sm:min-h-[126px] sm:px-5 sm:py-[18px]"
    >
        <div
            class="flex items-center justify-between gap-2 text-[13px] text-muted-foreground"
        >
            <span class="truncate">{{ label }}</span>
            <span
                class="grid size-[34px] shrink-0 place-items-center rounded-[9px] bg-accent text-accent-foreground"
            >
                <AppIcon :name="icon" :size="17" />
            </span>
        </div>
        <p
            class="mt-2 mb-1 font-display text-[25px] font-extrabold sm:text-[30px]"
        >
            {{ formatted }}
        </p>
        <p v-if="hint" class="text-[11px]" :class="hintClass[hintTone]">
            {{ hint }}
        </p>
    </div>
</template>
