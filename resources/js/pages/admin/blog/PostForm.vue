<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ImageField from '@/components/cms/ImageField.vue';
import RichTextEditor from '@/components/cms/RichTextEditor.vue';
import SeoPanel from '@/components/cms/SeoPanel.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { destroy, index, store, update } from '@/routes/admin/blog/posts';
import type { MediaItem, Option, PublishState, SeoValue } from '@/types';

type Post = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    content: string;
    featured_image: MediaItem | null;
    blog_category_id: string;
    author_id: string;
    tags: string[];
    status: 'draft' | 'published';
    published_at: string | null;
    is_featured: boolean;
    allow_comments: boolean;
    state: PublishState;
    seo: SeoValue;
};

const props = defineProps<{
    post: Post | null;
    options: { categories: Option[]; tags: string[]; authors: Option[] };
    can: { publish: boolean };
    defaultAuthor: string;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Blog', href: index() }] } });

const emptySeo: SeoValue = {
    meta_title: '',
    meta_description: '',
    focus_keyword: '',
    canonical_url: '',
    og_title: '',
    og_description: '',
    og_image: null,
    noindex: false,
};

const form = useForm({
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    excerpt: props.post?.excerpt ?? '',
    content: props.post?.content ?? '',
    featured_image: props.post?.featured_image ?? (null as MediaItem | null),
    blog_category_id: props.post?.blog_category_id ?? '',
    author_id: props.post?.author_id ?? props.defaultAuthor,
    tags: props.post?.tags ?? ([] as string[]),
    status: props.post?.status ?? 'draft',
    published_at: props.post?.published_at ?? '',
    is_featured: props.post?.is_featured ?? false,
    allow_comments: props.post?.allow_comments ?? true,
    seo: props.post?.seo ?? { ...emptySeo },
});

const tagInput = ref('');
function addTag(): void {
    const tag = tagInput.value.trim().replace(/,$/, '');
    if (
        tag &&
        !form.tags.some((t) => t.toLowerCase() === tag.toLowerCase()) &&
        form.tags.length < 15
    ) {
        form.tags.push(tag);
    }
    tagInput.value = '';
}

const err = (k: string) =>
    (form.errors as Record<string, string | undefined>)[k];

function save(status?: 'draft' | 'published'): void {
    if (status) {
        form.status = status;
    }

    const payload = form.transform((d) => ({
        ...d,
        featured_image: undefined,
        featured_image_id: d.featured_image?.id ?? null,
        blog_category_id: d.blog_category_id || null,
        author_id: d.author_id || null,
        slug: d.slug || null,
        published_at: d.published_at || null,
        seo: {
            ...d.seo,
            og_image: undefined,
            og_image_id: d.seo.og_image?.id ?? null,
        },
    }));

    if (props.post) {
        payload.put(update(props.post.id).url, { preserveScroll: true });
    } else {
        payload.post(store().url);
    }
}

const scheduled = computed(
    () =>
        form.status === 'published' &&
        !!form.published_at &&
        new Date(form.published_at) > new Date(),
);
const deleting = ref(false);
</script>

<template>
    <Head :title="post ? `Edit: ${post.title}` : 'New post'" />

    <PageHeader
        eyebrow="Blog management"
        :title="post ? 'Edit post' : 'New post'"
    >
        <template #actions>
            <StatusBadge
                v-if="post"
                :label="post.state.label"
                :tone="post.state.tone"
            />
            <Button variant="outline" as-child
                ><Link :href="index()">All posts</Link></Button
            >
        </template>
    </PageHeader>

    <form
        class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]"
        @submit.prevent="save()"
    >
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard>
                <div class="flex flex-col gap-3.5">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Title"
                        required
                        :error="err('title')"
                    >
                        <Input
                            :id="id"
                            v-model="form.title"
                            maxlength="200"
                            class="text-base font-semibold"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid, describedBy }"
                        label="Address"
                        :error="err('slug')"
                        hint="Blank: made from the title."
                    >
                        <div
                            class="flex items-center gap-1 text-[13px] text-muted-foreground"
                        >
                            <span>/blog/</span>
                            <Input
                                :id="id"
                                v-model="form.slug"
                                maxlength="200"
                                placeholder="my-post-title"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </div>
                    </FormField>
                    <FormField
                        id="post-content"
                        label="Content"
                        required
                        :error="err('content')"
                    >
                        <template #default="{ id, invalid }">
                            <RichTextEditor
                                :id="id"
                                v-model="form.content"
                                :invalid="invalid"
                                min-height="360px"
                            />
                        </template>
                    </FormField>
                    <FormField
                        v-slot="{ id, describedBy }"
                        label="Excerpt"
                        :error="err('excerpt')"
                        hint="Shown on blog cards. Blank: taken from the content."
                    >
                        <TextArea
                            :id="id"
                            v-model="form.excerpt"
                            :rows="3"
                            :maxlength="400"
                            :described-by="describedBy"
                        />
                    </FormField>
                </div>
            </PanelCard>
            <SeoPanel
                v-model="form.seo"
                :errors="form.errors as Record<string, string>"
                :fallback-title="form.title"
                :path="`/blog/${form.slug || '…'}`"
            />
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard title="Publish">
                <div class="flex flex-col gap-3">
                    <template v-if="can.publish">
                        <FormField v-slot="{ id }" label="Status">
                            <SelectInput
                                :id="id"
                                v-model="form.status"
                                :options="[
                                    { value: 'draft', label: 'Draft' },
                                    { value: 'published', label: 'Published' },
                                ]"
                            />
                        </FormField>
                        <FormField
                            v-slot="{ id, describedBy }"
                            label="Publish date"
                            :error="err('published_at')"
                            hint="A future date schedules the post."
                        >
                            <Input
                                :id="id"
                                v-model="form.published_at"
                                type="datetime-local"
                                :aria-describedby="describedBy"
                            />
                        </FormField>
                        <label class="flex items-center gap-2 text-[13px]">
                            <Checkbox
                                :model-value="form.is_featured"
                                @update:model-value="
                                    (v) => (form.is_featured = v === true)
                                "
                            />
                            Featured post
                        </label>
                    </template>
                    <p v-else class="text-[12px] text-muted-foreground">
                        You can save drafts. An editor publishes them.
                    </p>
                    <label class="flex items-center gap-2 text-[13px]">
                        <Checkbox
                            :model-value="form.allow_comments"
                            @update:model-value="
                                (v) => (form.allow_comments = v === true)
                            "
                        />
                        Allow comments
                    </label>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <Button
                            type="submit"
                            class="flex-1"
                            :disabled="form.processing"
                        >
                            {{
                                !can.publish
                                    ? 'Save draft'
                                    : form.status === 'draft'
                                      ? 'Save draft'
                                      : scheduled
                                        ? 'Schedule'
                                        : 'Publish'
                            }}
                        </Button>
                    </div>
                    <button
                        v-if="post"
                        type="button"
                        class="self-start text-[12px] text-danger hover:underline"
                        @click="deleting = true"
                    >
                        Move to trash
                    </button>
                </div>
            </PanelCard>

            <PanelCard title="Details">
                <div class="flex flex-col gap-3.5">
                    <ImageField
                        v-model="form.featured_image"
                        label="Featured image"
                        hint="Landscape, at least 1200 px wide."
                    />
                    <p
                        v-if="err('featured_image_id')"
                        class="text-xs text-danger"
                    >
                        {{ err('featured_image_id') }}
                    </p>
                    <FormField
                        v-slot="{ id }"
                        label="Category"
                        :error="err('blog_category_id')"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.blog_category_id"
                            :options="options.categories"
                            placeholder="Uncategorised"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Author"
                        :error="err('author_id')"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.author_id"
                            :options="options.authors"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, describedBy }"
                        label="Tags"
                        :error="err('tags')"
                        hint="Press Enter to add."
                    >
                        <div>
                            <ul
                                v-if="form.tags.length"
                                class="mb-2 flex flex-wrap gap-1.5"
                            >
                                <li
                                    v-for="tag in form.tags"
                                    :key="tag"
                                    class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-[12px]"
                                >
                                    {{ tag }}
                                    <button
                                        type="button"
                                        :aria-label="`Remove tag ${tag}`"
                                        @click="
                                            form.tags = form.tags.filter(
                                                (t) => t !== tag,
                                            )
                                        "
                                    >
                                        ×
                                    </button>
                                </li>
                            </ul>
                            <Input
                                :id="id"
                                v-model="tagInput"
                                list="known-tags"
                                maxlength="60"
                                :aria-describedby="describedBy"
                                @keydown.enter.prevent="addTag"
                                @keydown.,.prevent="addTag"
                                @blur="addTag"
                            />
                            <datalist id="known-tags">
                                <option
                                    v-for="t in options.tags"
                                    :key="t"
                                    :value="t"
                                />
                            </datalist>
                        </div>
                    </FormField>
                </div>
            </PanelCard>
        </div>
    </form>

    <ConfirmDialog
        v-if="post"
        v-model:open="deleting"
        title="Move this post to trash?"
        description="It leaves the website at once. You can restore it from the Trash tab."
        confirm-label="Move to trash"
        destructive
        @confirm="router.delete(destroy(post.id).url)"
    />
</template>
