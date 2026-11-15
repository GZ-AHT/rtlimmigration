<script setup lang="ts">
import type { Option } from '@/types';

/** Native select styled like admin-demo.html (.field select / .tabletools select). */
withDefaults(
    defineProps<{
        options: Option[];
        placeholder?: string;
        id?: string;
        invalid?: boolean;
        describedBy?: string;
        label?: string;
        disabled?: boolean;
        compact?: boolean;
    }>(),
    {
        placeholder: undefined,
        id: undefined,
        invalid: false,
        describedBy: undefined,
        label: undefined,
        disabled: false,
        compact: false,
    },
);

const model = defineModel<string>({ default: '' });
</script>

<template>
    <select
        :id="id"
        v-model="model"
        :aria-label="label"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedBy"
        :disabled="disabled"
        class="h-10 rounded-lg border bg-card px-3 text-[13px] text-secondary-foreground outline-none focus:border-ring focus:ring-3 focus:ring-accent disabled:cursor-not-allowed disabled:opacity-60 aria-invalid:border-danger"
        :class="compact ? 'w-auto' : 'w-full'"
    >
        <option v-if="placeholder !== undefined" value="">
            {{ placeholder }}
        </option>
        <option
            v-for="option in options"
            :key="option.value"
            :value="option.value"
        >
            {{ option.label }}
        </option>
    </select>
</template>
