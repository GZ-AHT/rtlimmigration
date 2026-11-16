<script setup lang="ts">
import AppIcon from '@/components/panel/AppIcon.vue';
import type { StageStep } from '@/types';

/**
 * An application's workflow as a vertical list of steps (REQ §13). State is
 * shown by icon and text as well as colour.
 */
defineProps<{ stages: StageStep[]; showDescriptions?: boolean }>();

const stateLabel = {
    done: 'Completed',
    current: 'Current stage',
    upcoming: 'Not reached yet',
} as const;
</script>

<template>
    <ol class="relative">
        <li
            v-for="(s, i) in stages"
            :key="s.id"
            class="relative flex gap-3 pb-4 last:pb-0"
            :aria-current="s.state === 'current' ? 'step' : undefined"
        >
            <span
                v-if="i < stages.length - 1"
                class="absolute top-6 left-[11px] h-[calc(100%-1.25rem)] w-px"
                :class="s.state === 'done' ? 'bg-success' : 'bg-border'"
                aria-hidden="true"
            />
            <span
                class="relative z-10 grid size-[23px] shrink-0 place-items-center rounded-full border-2 text-[11px] font-bold"
                :class="{
                    'border-success bg-success text-white': s.state === 'done',
                    'border-primary bg-primary text-primary-foreground':
                        s.state === 'current',
                    'border-border bg-card text-muted-foreground':
                        s.state === 'upcoming',
                }"
            >
                <AppIcon v-if="s.state === 'done'" name="check" :size="14" />
                <template v-else>{{ i + 1 }}</template>
            </span>
            <div class="min-w-0 pt-0.5">
                <p
                    class="text-[13px] leading-snug"
                    :class="{
                        'font-bold text-foreground': s.state === 'current',
                        'text-foreground': s.state === 'done',
                        'text-muted-foreground': s.state === 'upcoming',
                    }"
                >
                    {{ s.name }}
                    <span class="sr-only">({{ stateLabel[s.state] }})</span>
                </p>
                <p
                    v-if="s.state === 'current'"
                    class="text-[11px] font-semibold text-primary"
                >
                    Current stage
                </p>
                <p
                    v-if="
                        showDescriptions &&
                        s.description &&
                        s.state === 'current'
                    "
                    class="mt-0.5 text-xs text-secondary-foreground"
                >
                    {{ s.description }}
                </p>
            </div>
        </li>
    </ol>
</template>
