<script setup lang="ts">
import { computed } from 'vue';

/**
 * The arrows from the Figma labels (↗ → ↓ ←), drawn as SVG. The bundled
 * Inter is the Latin subset, which lacks → and ↗, so as text they would
 * fall back to another font and look thinner than in the design.
 */
const props = withDefaults(defineProps<{ glyph?: string }>(), { glyph: '↗' });

// A 45° arrow fills less of its box, so ↗ is drawn a little larger.
const rotation = computed(
    () => ({ '↗': -45, '→': 0, '↓': 90, '←': 180, '↑': -90 })[props.glyph] ?? 0,
);
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2.4"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        :class="
            glyph === '↗'
                ? 'size-[0.95em] align-[-0.14em]'
                : 'size-[0.78em] align-[-0.08em]'
        "
        class="inline-block shrink-0"
    >
        <g :transform="`rotate(${rotation} 12 12)`">
            <path d="M4 12h15.5M13 5.5l6.5 6.5-6.5 6.5" />
        </g>
    </svg>
</template>
