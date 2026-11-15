<script setup lang="ts">
/** Material Symbols Outlined icon, the icon set used by admin-demo.html. */
withDefaults(
    defineProps<{
        name: string;
        size?: number;
        filled?: boolean;
    }>(),
    { size: 20, filled: false },
);
</script>

<template>
    <span
        class="icon shrink-0"
        :class="{ 'icon-filled': filled }"
        :style="{
            fontSize: `${size}px`,
            width: `${size}px`,
            height: `${size}px`,
        }"
        aria-hidden="true"
        >{{ name }}</span
    >
</template>
