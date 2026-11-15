<script setup lang="ts">
import SearchInput from '@/components/panel/SearchInput.vue';

/** Filter row above a table (admin-demo.html .tabletools). */
withDefaults(
    defineProps<{ searchable?: boolean; searchPlaceholder?: string }>(),
    { searchable: true, searchPlaceholder: 'Search…' },
);

const search = defineModel<string>('search', { default: '' });
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-2.5 border-b px-4 py-4 sm:px-5 sm:py-[18px]"
    >
        <SearchInput
            v-if="searchable"
            v-model="search"
            :placeholder="searchPlaceholder"
        />
        <slot name="filters" />
        <div class="hidden flex-1 sm:block" />
        <div v-if="$slots.actions" class="flex flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
