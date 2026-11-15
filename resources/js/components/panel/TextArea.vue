<script setup lang="ts">
/** Multi-line text field (admin-demo.html .field textarea). */
withDefaults(
    defineProps<{
        id?: string;
        rows?: number;
        placeholder?: string;
        invalid?: boolean;
        describedBy?: string;
        maxlength?: number;
    }>(),
    {
        id: undefined,
        rows: 4,
        placeholder: undefined,
        invalid: false,
        describedBy: undefined,
        maxlength: undefined,
    },
);

const model = defineModel<string>({ default: '' });
</script>

<template>
    <textarea
        :id="id"
        v-model="model"
        :rows="rows"
        :placeholder="placeholder"
        :maxlength="maxlength"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedBy"
        class="min-h-[90px] w-full resize-y rounded-lg border bg-card px-3 py-2.5 text-[13px] outline-none placeholder:text-muted-foreground focus:border-ring focus:ring-3 focus:ring-accent aria-invalid:border-danger"
    />
</template>
