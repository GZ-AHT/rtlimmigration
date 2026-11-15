<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';

/**
 * Search box with a debounced v-model (admin-demo.html .search), so typing
 * does not fire a server request on every keystroke.
 */
const props = withDefaults(
    defineProps<{
        placeholder?: string;
        debounce?: number;
        label?: string;
    }>(),
    { placeholder: 'Search…', debounce: 300, label: 'Search' },
);

const model = defineModel<string>({ default: '' });
const local = ref(model.value);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(model, (value) => {
    if (value !== local.value) {
        local.value = value;
    }
});

watch(local, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => (model.value = value), props.debounce);
});

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <label class="relative block w-full sm:w-auto sm:min-w-[210px]">
        <span class="sr-only">{{ label }}</span>
        <AppIcon
            name="search"
            :size="19"
            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground"
        />
        <input
            v-model="local"
            type="search"
            :placeholder="placeholder"
            class="h-10 w-full rounded-lg border bg-card pr-3 pl-10 text-[13px] outline-none placeholder:text-muted-foreground focus:border-ring focus:ring-3 focus:ring-accent"
        />
    </label>
</template>
