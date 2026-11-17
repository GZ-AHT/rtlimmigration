<script setup lang="ts" generic="T extends { id: number | string }">
import { ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';

/**
 * Reorderable list: drag the handle, or use the up/down buttons (keyboard
 * and touch friendly). Emits the new order; the parent saves it.
 */
const props = defineProps<{ items: T[]; label: string; disabled?: boolean }>();
const emit = defineEmits<{ reorder: [items: T[]] }>();

const dragging = ref<number | null>(null);
const over = ref<number | null>(null);

function move(from: number, to: number): void {
    if (to < 0 || to >= props.items.length || from === to) {
        return;
    }

    const next = [...props.items];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    emit('reorder', next);
}

function onDrop(index: number): void {
    if (dragging.value !== null) {
        move(dragging.value, index);
    }

    dragging.value = null;
    over.value = null;
}
</script>

<template>
    <ul class="flex flex-col gap-2" :aria-label="label">
        <li
            v-for="(item, index) in items"
            :key="item.id"
            class="flex items-stretch gap-2 rounded-lg border bg-card transition-colors"
            :class="[
                over === index && dragging !== null && dragging !== index
                    ? 'border-primary'
                    : 'border-border',
                dragging === index ? 'opacity-50' : '',
            ]"
            :draggable="!disabled"
            @dragstart="
                (e) => {
                    dragging = index;
                    e.dataTransfer?.setData('text/plain', String(index));
                }
            "
            @dragover.prevent="over = index"
            @dragleave="over = over === index ? null : over"
            @drop.prevent="onDrop(index)"
            @dragend="
                dragging = null;
                over = null;
            "
        >
            <span
                v-if="!disabled"
                class="grid w-7 shrink-0 cursor-grab place-items-center text-muted-foreground"
                aria-hidden="true"
                ><AppIcon name="drag_indicator" :size="18"
            /></span>
            <div class="min-w-0 flex-1 py-2" :class="disabled ? 'pl-3' : ''">
                <slot :item="item" :index="index" />
            </div>
            <div
                v-if="!disabled"
                class="flex shrink-0 flex-col justify-center pr-1"
            >
                <button
                    type="button"
                    class="grid size-6 place-items-center rounded text-muted-foreground hover:bg-muted disabled:opacity-30"
                    :disabled="index === 0"
                    aria-label="Move up"
                    @click="move(index, index - 1)"
                >
                    <AppIcon name="keyboard_arrow_up" :size="18" />
                </button>
                <button
                    type="button"
                    class="grid size-6 place-items-center rounded text-muted-foreground hover:bg-muted disabled:opacity-30"
                    :disabled="index === items.length - 1"
                    aria-label="Move down"
                    @click="move(index, index + 1)"
                >
                    <AppIcon name="keyboard_arrow_down" :size="18" />
                </button>
            </div>
        </li>
    </ul>
</template>
