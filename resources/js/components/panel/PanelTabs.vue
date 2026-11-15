<script setup lang="ts">
/** Underlined tab strip from admin-demo.html (.tabs). */
defineProps<{
    tabs: { key: string; label: string; count?: number }[];
    label?: string;
}>();

const active = defineModel<string>({ required: true });
</script>

<template>
    <div
        role="tablist"
        :aria-label="label"
        class="mb-[18px] flex gap-1.5 overflow-x-auto border-b"
    >
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="active === tab.key"
            class="-mb-px border-b-2 px-[15px] py-3 text-[13px] whitespace-nowrap"
            :class="
                active === tab.key
                    ? 'border-primary font-bold text-primary'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
            @click="active = tab.key"
        >
            {{ tab.label }}
            <span
                v-if="tab.count !== undefined"
                class="ml-1 rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-bold"
                >{{ tab.count }}</span
            >
        </button>
    </div>
</template>
