<script setup lang="ts">
import { ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';

/**
 * Who a chat message is from. A support person shows their profile picture,
 * or a plain person icon when they have none; the automatic assistant always
 * shows a robot icon, so the two are never mistaken for each other.
 */
const props = defineProps<{
    kind: 'human' | 'bot';
    src?: string | null;
    name?: string | null;
}>();

const failed = ref(false);
watch(
    () => props.src,
    () => (failed.value = false),
);
</script>

<template>
    <span
        class="grid size-8 shrink-0 place-items-center overflow-hidden rounded-full"
        :class="
            kind === 'human'
                ? 'bg-success-soft text-success ring-2 ring-success/30'
                : 'bg-muted text-muted-foreground'
        "
        role="img"
        :aria-label="
            kind === 'human'
                ? `${name ?? 'Support agent'}, support team`
                : 'Automated assistant'
        "
    >
        <img
            v-if="kind === 'human' && src && !failed"
            :src="src"
            :alt="name ?? ''"
            class="size-full object-cover"
            @error="failed = true"
        />
        <AppIcon
            v-else
            :name="kind === 'human' ? 'person' : 'smart_toy'"
            :size="kind === 'human' ? 20 : 17"
        />
    </span>
</template>
