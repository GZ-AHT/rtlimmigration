<script setup lang="ts">
import { ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import MediaPicker from '@/components/cms/MediaPicker.vue';
import type { MediaItem } from '@/types';

/** One image chosen from the media library (stored as its id). */
const props = defineProps<{ label: string; hint?: string }>();
const model = defineModel<MediaItem | null>({ default: null });
const picking = ref(false);
void props;
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span class="text-[13px] font-semibold text-foreground">{{
            label
        }}</span>
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-md border border-dashed border-border bg-muted/40 hover:bg-muted"
                :aria-label="model ? `Change ${label}` : `Choose ${label}`"
                @click="picking = true"
            >
                <img
                    v-if="model"
                    :src="model.url"
                    :alt="model.alt ?? ''"
                    class="size-full object-cover"
                />
                <AppIcon v-else name="add_photo_alternate" :size="24" />
            </button>
            <div class="flex flex-col items-start gap-1 text-[12px]">
                <button
                    type="button"
                    class="font-semibold text-primary hover:underline"
                    @click="picking = true"
                >
                    {{ model ? 'Change' : 'Choose image' }}
                </button>
                <button
                    v-if="model"
                    type="button"
                    class="text-muted-foreground hover:text-danger"
                    @click="model = null"
                >
                    Remove
                </button>
                <span v-if="hint" class="text-muted-foreground">{{
                    hint
                }}</span>
            </div>
        </div>
        <MediaPicker
            v-model:open="picking"
            @select="(items) => (model = items[0] ?? null)"
        />
    </div>
</template>
