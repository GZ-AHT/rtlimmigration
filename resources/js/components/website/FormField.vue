<script setup lang="ts">
/** Label, control slot and error for website forms (Figma Contact form style). */
defineProps<{
    id: string;
    label: string;
    error?: string;
    hint?: string;
    class?: string;
}>();
</script>

<template>
    <div :class="$props.class">
        <label
            :for="id"
            class="mb-2 block text-sm leading-[19.5px] font-medium text-site-ink"
            >{{ label }}</label
        >
        <slot />
        <p v-if="hint && !error" class="mt-1.5 text-xs text-site-muted">
            {{ hint }}
        </p>
        <p v-if="error" :id="`${id}-err`" class="mt-1.5 text-xs text-site-red">
            {{ error }}
        </p>
    </div>
</template>
