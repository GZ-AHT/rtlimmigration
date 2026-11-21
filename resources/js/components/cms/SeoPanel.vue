<script setup lang="ts">
import { computed } from 'vue';
import ImageField from '@/components/cms/ImageField.vue';
import FormField from '@/components/panel/FormField.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { SeoValue } from '@/types';

/** REQ §4 / §5 / §15 SEO: meta title and description, keyword, canonical, Open Graph, noindex. */
const props = defineProps<{
    errors?: Record<string, string | undefined>;
    fallbackTitle?: string;
    path?: string;
}>();
const model = defineModel<SeoValue>({ required: true });

const urlPlaceholder = 'https://…';
const cardOptions = [
    { value: 'summary_large_image', label: 'Large image' },
    { value: 'summary', label: 'Summary' },
];
const err = (k: string) => props.errors?.[`seo.${k}`];
const titleLength = computed(
    () => (model.value.meta_title || props.fallbackTitle || '').length,
);
</script>

<template>
    <PanelCard
        title="SEO"
        description="How this appears in search results and when shared."
    >
        <div class="flex flex-col gap-3.5">
            <div
                class="rounded-md border border-border bg-muted/30 p-3 text-[13px]"
                aria-label="Search result preview"
            >
                <p
                    class="truncate text-[15px] text-[#1a0dab] dark:text-[#8ab4f8]"
                >
                    {{ model.meta_title || fallbackTitle || 'Page title' }}
                </p>
                <p v-if="path" class="truncate text-[12px] text-success">
                    {{ path }}
                </p>
                <p class="line-clamp-2 text-muted-foreground">
                    {{
                        model.meta_description ||
                        'Add a meta description so search engines show a helpful summary.'
                    }}
                </p>
            </div>
            <FormField
                v-slot="{ id, invalid }"
                label="Meta title"
                :error="err('meta_title')"
                :hint="`${titleLength}/60 characters. Blank uses the title.`"
            >
                <Input
                    :id="id"
                    v-model="model.meta_title"
                    maxlength="70"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField
                v-slot="{ id, invalid }"
                label="Meta description"
                :error="err('meta_description')"
                :hint="`${model.meta_description.length}/160 characters.`"
            >
                <TextArea
                    :id="id"
                    v-model="model.meta_description"
                    :rows="3"
                    :maxlength="170"
                    :invalid="invalid"
                />
            </FormField>
            <label class="flex items-center gap-2 text-[13px]">
                <Checkbox
                    :model-value="model.seo_enabled"
                    @update:model-value="
                        (v) => (model.seo_enabled = v === true)
                    "
                />
                Use these SEO fields (off = use the site defaults)
            </label>
            <FormField
                v-slot="{ id }"
                label="Meta keywords"
                :error="err('meta_keywords')"
            >
                <Input :id="id" v-model="model.meta_keywords" maxlength="255" />
            </FormField>
            <FormField
                v-slot="{ id }"
                label="Focus keyword"
                :error="err('focus_keyword')"
            >
                <Input :id="id" v-model="model.focus_keyword" maxlength="100" />
            </FormField>
            <FormField
                v-slot="{ id, invalid }"
                label="Canonical URL"
                :error="err('canonical_url')"
                hint="Only if this content is published elsewhere first."
            >
                <Input
                    :id="id"
                    v-model="model.canonical_url"
                    type="url"
                    :placeholder="urlPlaceholder"
                    :aria-invalid="invalid"
                />
            </FormField>
            <details class="rounded-md border border-border px-3 py-2">
                <summary class="cursor-pointer text-[13px] font-semibold">
                    Social sharing (Open Graph)
                </summary>
                <div class="mt-3 flex flex-col gap-3">
                    <FormField
                        v-slot="{ id }"
                        label="Share title"
                        :error="err('og_title')"
                    >
                        <Input
                            :id="id"
                            v-model="model.og_title"
                            maxlength="100"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Share description"
                        :error="err('og_description')"
                    >
                        <TextArea
                            :id="id"
                            v-model="model.og_description"
                            :rows="2"
                            :maxlength="300"
                        />
                    </FormField>
                    <ImageField
                        v-model="model.og_image"
                        label="Share image"
                        hint="1200 × 630 px works best."
                    />
                </div>
            </details>
            <details class="rounded-md border border-border px-3 py-2">
                <summary class="cursor-pointer text-[13px] font-semibold">
                    Twitter card and structured data
                </summary>
                <div class="mt-3 flex flex-col gap-3">
                    <FormField
                        v-slot="{ id }"
                        label="Twitter card"
                        :error="err('twitter_card')"
                        hint="Blank uses the site default."
                    >
                        <SelectInput
                            :id="id"
                            v-model="model.twitter_card"
                            :options="cardOptions"
                            placeholder="Site default"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Custom structured data (JSON-LD)"
                        :error="err('custom_schema')"
                        hint="A JSON-LD object or a list of objects. Breadcrumb and article data are added automatically."
                    >
                        <TextArea
                            :id="id"
                            v-model="model.custom_schema"
                            :rows="5"
                            :maxlength="10000"
                            :invalid="invalid"
                            class="font-mono text-[12px]"
                        />
                    </FormField>
                </div>
            </details>
            <label class="flex items-center gap-2 text-[13px]">
                <Checkbox
                    :model-value="model.nofollow"
                    @update:model-value="(v) => (model.nofollow = v === true)"
                />
                Tell search engines not to follow links (nofollow)
            </label>
            <label class="flex items-center gap-2 text-[13px]">
                <Checkbox
                    :model-value="model.noindex"
                    @update:model-value="(v) => (model.noindex = v === true)"
                />
                Hide from search engines (noindex)
            </label>
        </div>
    </PanelCard>
</template>
