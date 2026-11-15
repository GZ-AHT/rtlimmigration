<script setup lang="ts">
import { useId } from 'vue';

/**
 * Label + control + hint + error wrapper (admin-demo.html .field).
 * The default slot receives `id`, `describedBy` and `invalid` so the
 * control can be wired up for screen readers.
 */
const props = defineProps<{
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    id?: string;
}>();

const generated = useId();
const fieldId = props.id ?? `field-${generated}`;
const hintId = `${fieldId}-hint`;
const errorId = `${fieldId}-error`;
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label
            :for="fieldId"
            class="text-xs font-bold text-secondary-foreground"
        >
            {{ label }}
            <span v-if="required" class="text-primary" aria-hidden="true"
                >*</span
            >
        </label>
        <slot
            :id="fieldId"
            :described-by="
                [hint ? hintId : null, error ? errorId : null]
                    .filter(Boolean)
                    .join(' ') || undefined
            "
            :invalid="Boolean(error)"
        />
        <p
            v-if="hint && !error"
            :id="hintId"
            class="text-[11px] text-muted-foreground"
        >
            {{ hint }}
        </p>
        <p v-if="error" :id="errorId" class="text-xs text-danger" role="alert">
            {{ error }}
        </p>
    </div>
</template>
