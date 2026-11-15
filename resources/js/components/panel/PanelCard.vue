<script setup lang="ts">
import { cn } from '@/lib/utils';

/** White bordered card from admin-demo.html (.card / .panel). */
defineProps<{
    title?: string;
    description?: string;
    /** Remove inner padding, e.g. when the card wraps a table. */
    flush?: boolean;
    class?: string;
}>();
</script>

<template>
    <section
        :class="
            cn(
                'rounded-xl border bg-card text-card-foreground shadow-panel',
                flush ? 'overflow-hidden' : 'p-5 sm:p-[22px]',
                $props.class,
            )
        "
    >
        <header
            v-if="title || $slots.actions"
            :class="[
                'flex flex-wrap items-start justify-between gap-3',
                flush ? 'px-5 pt-5 sm:px-[22px] sm:pt-[22px]' : 'mb-4',
            ]"
        >
            <div class="min-w-0">
                <h3 v-if="title" class="font-display text-[17px] font-bold">
                    {{ title }}
                </h3>
                <p
                    v-if="description"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap gap-2">
                <slot name="actions" />
            </div>
        </header>
        <slot />
    </section>
</template>
