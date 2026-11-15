<script setup lang="ts">
import AppIcon from '@/components/panel/AppIcon.vue';

/** Empty-state block (admin-demo.html .empty). */
withDefaults(
    defineProps<{ title: string; description?: string; icon?: string }>(),
    { icon: 'inbox' },
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center px-6 py-11 text-center text-muted-foreground"
    >
        <span
            class="mb-3 grid size-11 place-items-center rounded-xl bg-accent text-accent-foreground"
        >
            <AppIcon :name="icon" :size="22" />
        </span>
        <p class="text-sm font-semibold text-foreground">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-sm text-xs">
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
