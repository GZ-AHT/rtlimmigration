<script setup lang="ts">
/** Page title block from admin-demo.html (.pagehead). */
defineProps<{
    title: string;
    eyebrow?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="mb-6 flex flex-col items-start justify-between gap-4 md:flex-row md:items-end md:gap-5"
    >
        <div class="min-w-0">
            <p v-if="eyebrow" class="eyebrow mb-2">{{ eyebrow }}</p>
            <h1
                class="font-display text-[25px] leading-tight font-extrabold tracking-[-0.7px] md:text-[30px]"
            >
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="mt-1.5 text-[13px] text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap gap-2.5">
            <slot name="actions" />
        </div>
    </div>
</template>
