<script setup lang="ts">
import { ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import MediaPicker from '@/components/cms/MediaPicker.vue';
import type { MediaItem } from '@/types';

/** Several images in order (university gallery, payment logos). */
const props = withDefaults(defineProps<{ label: string; max?: number }>(), {
    max: 20,
});
const model = defineModel<MediaItem[]>({ default: () => [] });
const picking = ref(false);

function add(items: MediaItem[]): void {
    const ids = new Set(model.value.map((m) => m.id));
    model.value = [
        ...model.value,
        ...items.filter((i) => !ids.has(i.id)),
    ].slice(0, props.max);
}

function move(index: number, by: -1 | 1): void {
    const next = [...model.value];
    const to = index + by;

    if (to < 0 || to >= next.length) {
        return;
    }

    [next[index], next[to]] = [next[to], next[index]];
    model.value = next;
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span class="text-[13px] font-semibold text-foreground"
            >{{ label }}
            <span class="font-normal text-muted-foreground"
                >({{ model.length }}/{{ max }})</span
            ></span
        >
        <ul class="flex flex-wrap gap-2">
            <li
                v-for="(item, i) in model"
                :key="item.id"
                class="group relative size-20 overflow-hidden rounded-md border border-border"
            >
                <img
                    :src="item.url"
                    :alt="item.alt ?? ''"
                    class="size-full object-cover"
                />
                <div
                    class="absolute inset-x-0 bottom-0 flex justify-between bg-black/55 px-1 py-0.5 text-white opacity-0 group-focus-within:opacity-100 group-hover:opacity-100"
                >
                    <button
                        type="button"
                        :aria-label="`Move image ${i + 1} left`"
                        @click="move(i, -1)"
                    >
                        <AppIcon name="chevron_left" :size="16" />
                    </button>
                    <button
                        type="button"
                        :aria-label="`Remove image ${i + 1}`"
                        @click="model = model.filter((m) => m.id !== item.id)"
                    >
                        <AppIcon name="close" :size="16" />
                    </button>
                    <button
                        type="button"
                        :aria-label="`Move image ${i + 1} right`"
                        @click="move(i, 1)"
                    >
                        <AppIcon name="chevron_right" :size="16" />
                    </button>
                </div>
            </li>
            <li v-if="model.length < max">
                <button
                    type="button"
                    class="grid size-20 place-items-center rounded-md border border-dashed border-border bg-muted/40 hover:bg-muted"
                    :aria-label="`Add images to ${label}`"
                    @click="picking = true"
                >
                    <AppIcon name="add_photo_alternate" :size="24" />
                </button>
            </li>
        </ul>
        <MediaPicker v-model:open="picking" multiple @select="add" />
    </div>
</template>
