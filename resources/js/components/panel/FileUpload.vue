<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';

/**
 * Drag-and-drop file picker. Checks type and size in the browser for quick
 * feedback only; the server validates every upload again.
 */
const props = withDefaults(
    defineProps<{
        label?: string;
        /** Comma-separated extensions or MIME types, e.g. ".pdf,.jpg,image/png". */
        accept?: string;
        maxSizeMb?: number;
        multiple?: boolean;
        maxFiles?: number;
        error?: string;
        hint?: string;
    }>(),
    {
        label: 'Choose files',
        accept: undefined,
        maxSizeMb: 10,
        multiple: false,
        maxFiles: 10,
        error: undefined,
        hint: undefined,
    },
);

const files = defineModel<File[]>({ default: () => [] });
const inputId = `upload-${useId()}`;
const dragging = ref(false);
const localError = ref<string | null>(null);
const input = ref<HTMLInputElement | null>(null);

const shownError = computed(() => props.error ?? localError.value);

function matchesAccept(file: File): boolean {
    if (!props.accept) {
        return true;
    }

    return props.accept.split(',').some((rule) => {
        const r = rule.trim().toLowerCase();

        if (r.startsWith('.')) {
            return file.name.toLowerCase().endsWith(r);
        }

        if (r.endsWith('/*')) {
            return file.type.startsWith(r.slice(0, -1));
        }

        return file.type === r;
    });
}

function add(list: FileList | null): void {
    localError.value = null;

    if (!list) {
        return;
    }

    const incoming = Array.from(list);
    const accepted: File[] = [];

    for (const file of incoming) {
        if (!matchesAccept(file)) {
            localError.value = `${file.name} is not an allowed file type.`;
            continue;
        }

        if (file.size > props.maxSizeMb * 1024 * 1024) {
            localError.value = `${file.name} is larger than ${props.maxSizeMb} MB.`;
            continue;
        }

        accepted.push(file);
    }

    const next = props.multiple
        ? [...files.value, ...accepted]
        : accepted.slice(0, 1);

    if (next.length > props.maxFiles) {
        localError.value = `You can upload up to ${props.maxFiles} files.`;
    }

    files.value = next.slice(0, props.maxFiles);

    if (input.value) {
        input.value.value = '';
    }
}

function remove(index: number): void {
    files.value = files.value.filter((_, i) => i !== index);
}

function size(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-2">
        <label
            :for="inputId"
            class="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed px-4 py-6 text-center transition-colors"
            :class="[
                dragging
                    ? 'border-primary bg-accent'
                    : 'bg-muted hover:border-ring',
                shownError && 'border-danger',
            ]"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="
                dragging = false;
                add($event.dataTransfer?.files ?? null);
            "
        >
            <span
                class="grid size-10 place-items-center rounded-lg bg-accent text-accent-foreground"
            >
                <AppIcon name="upload" />
            </span>
            <span class="text-[13px] font-semibold">{{ label }}</span>
            <span class="text-[11px] text-muted-foreground">
                {{
                    hint ??
                    `Drag and drop, or click to browse · Up to ${maxSizeMb} MB per file`
                }}
            </span>
            <input
                :id="inputId"
                ref="input"
                type="file"
                class="sr-only"
                :accept="accept"
                :multiple="multiple"
                @change="add(($event.target as HTMLInputElement).files)"
            />
        </label>
        <p v-if="shownError" class="text-xs text-danger" role="alert">
            {{ shownError }}
        </p>
        <ul v-if="files.length" class="flex flex-col gap-1.5">
            <li
                v-for="(file, index) in files"
                :key="`${file.name}-${index}`"
                class="flex min-w-0 items-center gap-2.5 rounded-lg border bg-card px-3 py-2 text-[13px]"
            >
                <AppIcon
                    name="description"
                    :size="18"
                    class="text-muted-foreground"
                />
                <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                <span class="shrink-0 text-[11px] text-muted-foreground">{{
                    size(file.size)
                }}</span>
                <button
                    type="button"
                    class="grid size-7 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                    :aria-label="`Remove ${file.name}`"
                    @click="remove(index)"
                >
                    <AppIcon name="close" :size="16" />
                </button>
            </li>
        </ul>
    </div>
</template>
