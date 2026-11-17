<script setup lang="ts">
import { ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import SearchInput from '@/components/panel/SearchInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useMediaLibrary } from '@/composables/useMediaLibrary';
import type { MediaItem } from '@/types';

/** Choose one or more images from the media library, or upload new ones. */
const props = withDefaults(defineProps<{ multiple?: boolean }>(), {
    multiple: false,
});
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ select: [items: MediaItem[]] }>();

const { list, upload } = useMediaLibrary();
const items = ref<MediaItem[]>([]);
const page = ref(1);
const lastPage = ref(1);
const search = ref('');
const loading = ref(false);
const uploading = ref(false);
const error = ref<string | null>(null);
const chosen = ref<MediaItem[]>([]);

async function load(reset = true): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        if (reset) {
            page.value = 1;
        }

        const result = await list(page.value, search.value);
        items.value = reset ? result.data : [...items.value, ...result.data];
        lastPage.value = result.last_page;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

watch(open, (value) => {
    if (value) {
        chosen.value = [];
        void load();
    }
});
watch(search, () => void load());

function toggle(item: MediaItem): void {
    if (!props.multiple) {
        emit('select', [item]);
        open.value = false;

        return;
    }

    chosen.value = chosen.value.some((c) => c.id === item.id)
        ? chosen.value.filter((c) => c.id !== item.id)
        : [...chosen.value, item];
}

async function onUpload(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';
    uploading.value = true;
    error.value = null;

    try {
        for (const file of files) {
            const item = await upload(file);
            items.value = [item, ...items.value];

            if (props.multiple) {
                chosen.value = [...chosen.value, item];
            } else {
                toggle(item);
            }
        }
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        uploading.value = false;
    }
}

function more(): void {
    page.value += 1;
    void load(false);
}

function confirm(): void {
    emit('select', chosen.value);
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="rounded-[14px] sm:max-w-[760px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold"
                    >Choose {{ multiple ? 'images' : 'an image' }}</DialogTitle
                >
                <DialogDescription class="text-xs"
                    >JPG, PNG, WEBP or GIF, up to 5 MB.</DialogDescription
                >
            </DialogHeader>
            <div class="flex flex-wrap items-center gap-2">
                <div class="min-w-48 flex-1">
                    <SearchInput
                        v-model="search"
                        placeholder="Search images…"
                    />
                </div>
                <label
                    class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-md bg-primary px-3 text-[13px] font-semibold text-primary-foreground hover:bg-primary/90"
                    :class="uploading ? 'pointer-events-none opacity-60' : ''"
                >
                    <AppIcon name="upload" :size="16" />
                    {{ uploading ? 'Uploading…' : 'Upload' }}
                    <input
                        type="file"
                        class="sr-only"
                        accept=".jpg,.jpeg,.png,.webp,.gif"
                        :multiple="multiple"
                        @change="onUpload"
                    />
                </label>
            </div>
            <p v-if="error" class="text-xs text-danger" role="alert">
                {{ error }}
            </p>
            <div class="max-h-[55vh] overflow-y-auto">
                <p
                    v-if="!loading && items.length === 0"
                    class="py-10 text-center text-[13px] text-muted-foreground"
                >
                    No images yet. Upload one.
                </p>
                <ul class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                    <li v-for="item in items" :key="item.id">
                        <button
                            type="button"
                            class="group relative block aspect-square w-full overflow-hidden rounded-md border-2 bg-muted"
                            :class="
                                chosen.some((c) => c.id === item.id)
                                    ? 'border-primary'
                                    : 'border-transparent hover:border-border'
                            "
                            :aria-pressed="chosen.some((c) => c.id === item.id)"
                            :title="item.name"
                            @click="toggle(item)"
                        >
                            <img
                                :src="item.url"
                                :alt="item.alt ?? item.name"
                                class="size-full object-cover"
                                loading="lazy"
                            />
                            <span
                                v-if="chosen.some((c) => c.id === item.id)"
                                class="absolute top-1 right-1 grid size-6 place-items-center rounded-full bg-primary text-primary-foreground"
                                ><AppIcon name="check" :size="16"
                            /></span>
                        </button>
                    </li>
                </ul>
                <div v-if="page < lastPage" class="mt-3 flex justify-center">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="loading"
                        @click="more"
                        >Load more</Button
                    >
                </div>
            </div>
            <DialogFooter v-if="multiple">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button :disabled="chosen.length === 0" @click="confirm"
                    >Add {{ chosen.length || '' }}
                    {{ chosen.length === 1 ? 'image' : 'images' }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
