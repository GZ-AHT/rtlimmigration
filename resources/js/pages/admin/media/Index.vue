<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SearchInput from '@/components/panel/SearchInput.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { Input } from '@/components/ui/input';
import { useFilters } from '@/composables/useFilters';
import { useMediaLibrary } from '@/composables/useMediaLibrary';
import { destroy, index, update } from '@/routes/admin/media';
import type { MediaItem, Paginated } from '@/types';

const props = defineProps<{
    media: Paginated<MediaItem>;
    filters: { search: string };
    limits: {
        max_mb: number;
        extensions: string[];
        video_max_mb: number;
        video_extensions: string[];
    };
    canManage: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Media library', href: index() }] },
});

const { filters } = useFilters(
    { ...props.filters },
    { only: ['media', 'filters'] },
);
const { upload } = useMediaLibrary();
const uploading = ref(false);
const selected = ref<MediaItem | null>(null);
const alt = ref('');
const deleting = ref(false);

async function onUpload(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';
    uploading.value = true;

    for (const file of files) {
        try {
            await upload(file);
        } catch (e) {
            toast.error(`${file.name}: ${(e as Error).message}`);
        }
    }

    uploading.value = false;
    router.reload({ only: ['media'] });
}

function pick(item: MediaItem): void {
    selected.value = item;
    alt.value = item.alt ?? '';
}

function saveAlt(): void {
    if (!selected.value) return;
    router.patch(
        update(selected.value.id).url,
        { alt: alt.value },
        {
            preserveScroll: true,
            only: ['media'],
            onSuccess: () => toast.success('Description saved.'),
        },
    );
}

function remove(): void {
    if (!selected.value) return;
    router.delete(destroy(selected.value.id).url, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            selected.value = null;
        },
    });
}

const kb = (b: number) =>
    b > 1048576
        ? `${(b / 1048576).toFixed(1)} MB`
        : `${Math.round(b / 1024)} KB`;
</script>

<template>
    <Head title="Media library" />

    <PageHeader
        eyebrow="Website"
        title="Media library"
        description="Images for the website: posts, pages, universities, logos."
    >
        <template v-if="canManage" #actions>
            <label
                class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-md bg-primary px-3 text-[13px] font-semibold text-primary-foreground hover:bg-primary/90"
                :class="uploading ? 'pointer-events-none opacity-60' : ''"
            >
                <AppIcon name="upload" :size="18" />
                {{ uploading ? 'Uploading…' : 'Upload' }}
                <input
                    type="file"
                    class="sr-only"
                    multiple
                    :accept="
                        [...limits.extensions, ...limits.video_extensions]
                            .map((e) => `.${e}`)
                            .join(',')
                    "
                    @change="onUpload"
                />
            </label>
        </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
        <PanelCard>
            <div class="mb-3 max-w-sm">
                <SearchInput
                    v-model="filters.search"
                    placeholder="Search by name or description…"
                />
            </div>
            <EmptyState
                v-if="media.data.length === 0"
                icon="perm_media"
                title="No images yet"
                :description="`Images: ${limits.extensions.join(', ').toUpperCase()}, up to ${limits.max_mb} MB. Videos: ${limits.video_extensions.join(', ').toUpperCase()}, up to ${limits.video_max_mb} MB.`"
            />
            <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4 xl:grid-cols-6">
                <li v-for="item in media.data" :key="item.id">
                    <button
                        type="button"
                        class="block aspect-square w-full overflow-hidden rounded-md border-2 bg-muted"
                        :class="
                            selected?.id === item.id
                                ? 'border-primary'
                                : 'border-transparent hover:border-border'
                        "
                        :title="item.name"
                        @click="pick(item)"
                    >
                        <video
                            v-if="item.kind === 'video'"
                            :src="item.url"
                            class="size-full object-cover"
                            muted
                            preload="metadata"
                        />

                        <img
                            v-else
                            :src="item.url"
                            :alt="item.alt ?? item.name"
                            class="size-full object-cover"
                            loading="lazy"
                        />
                    </button>
                </li>
            </ul>
            <TablePagination v-if="media.last_page > 1" :meta="media" />
        </PanelCard>

        <PanelCard title="Details" class="self-start">
            <p v-if="!selected" class="text-[13px] text-muted-foreground">
                Choose an image to see its details.
            </p>
            <div v-else class="flex flex-col gap-3 text-[13px]">
                <video
                    v-if="selected.kind === 'video'"
                    :src="selected.url"
                    class="w-full rounded-md border border-border"
                    controls
                    muted
                    preload="metadata"
                />

                <img
                    v-else
                    :src="selected.url"
                    :alt="selected.alt ?? ''"
                    class="w-full rounded-md border border-border"
                />
                <p class="font-semibold break-all">{{ selected.name }}</p>
                <p class="text-muted-foreground">
                    <template v-if="selected.width"
                        >{{ selected.width }} × {{ selected.height }} px ·
                    </template>
                    <template v-else-if="selected.kind === 'video'"
                        >Video ·
                    </template>
                    {{ kb(selected.size) }}
                </p>
                <a
                    :href="selected.url"
                    target="_blank"
                    rel="noopener"
                    class="font-semibold text-primary hover:underline"
                    >Open full size</a
                >
                <template v-if="canManage">
                    <label class="flex flex-col gap-1.5">
                        <span class="font-semibold"
                            >Description (alt text)</span
                        >
                        <Input
                            v-model="alt"
                            maxlength="255"
                            placeholder="What the image shows"
                        />
                    </label>
                    <div class="flex justify-between">
                        <button
                            type="button"
                            class="tiny-action"
                            @click="saveAlt"
                        >
                            Save description
                        </button>
                        <button
                            type="button"
                            class="tiny-action text-danger"
                            @click="deleting = true"
                        >
                            Delete
                        </button>
                    </div>
                </template>
            </div>
        </PanelCard>
    </div>

    <ConfirmDialog
        v-model:open="deleting"
        title="Delete this image?"
        description="Pages, posts or logos using it will show no image."
        confirm-label="Delete"
        destructive
        @confirm="remove"
    />
</template>
