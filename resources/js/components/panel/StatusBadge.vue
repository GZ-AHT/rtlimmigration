<script setup lang="ts">
import type { Tone } from '@/types';

/** Rounded status pill from admin-demo.html (.pill). */
withDefaults(defineProps<{ label: string; tone?: Tone }>(), {
    tone: 'neutral',
});

const toneClass: Record<Tone, string> = {
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    info: 'bg-info-soft text-info',
    neutral: 'bg-neutral-soft text-neutral',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-[9px] py-[5px] text-[10px] leading-none font-bold whitespace-nowrap"
        :class="toneClass[tone]"
    >
        {{ label }}
    </span>
</template>
