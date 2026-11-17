<script setup lang="ts">
import { Input } from '@/components/ui/input';
import type { LinkValue } from '@/types';

/** Button or link: text plus address (site path, https://, mailto:, tel:). */
defineProps<{ label: string; error?: string }>();
const urlPlaceholder = '/contact or https://…';
const model = defineModel<LinkValue>({
    default: () => ({ label: '', url: '' }),
});
</script>

<template>
    <fieldset class="flex flex-col gap-1.5">
        <legend class="mb-1.5 text-[13px] font-semibold text-foreground">
            {{ label }}
        </legend>
        <div class="grid gap-2 sm:grid-cols-[1fr_1.4fr]">
            <Input
                :model-value="model.label"
                placeholder="Text"
                :aria-label="`${label}: text`"
                maxlength="60"
                @update:model-value="
                    (v) => (model = { ...model, label: String(v) })
                "
            />
            <Input
                :model-value="model.url"
                :placeholder="urlPlaceholder"
                :aria-label="`${label}: address`"
                maxlength="255"
                :aria-invalid="!!error"
                @update:model-value="
                    (v) => (model = { ...model, url: String(v) })
                "
            />
        </div>
        <p v-if="error" class="text-xs text-danger" role="alert">{{ error }}</p>
    </fieldset>
</template>
