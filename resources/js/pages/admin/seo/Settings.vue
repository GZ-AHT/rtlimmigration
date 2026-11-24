<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import SchemaFields from '@/components/cms/SchemaFields.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import { Button } from '@/components/ui/button';
import { clone } from '@/lib/clone';
import { settings } from '@/routes/admin/seo';
import { update } from '@/routes/admin/seo/settings';
import type { FieldDef, MediaItem } from '@/types';

const props = defineProps<{
    group: {
        label: string;
        description: string;
        fields: Record<string, FieldDef>;
    };
    values: Record<string, unknown>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'SEO settings', href: settings() }],
    },
});

/** Which settings appear in which card, in order. Any field not listed lands in "More". */
const SECTIONS: { title: string; description: string; keys: string[] }[] = [
    {
        title: 'Search engine visibility',
        description:
            'Switch SEO features on or off and decide whether search engines may index the website.',
        keys: ['enabled', 'noindex_site', 'default_robots'],
    },
    {
        title: 'Meta tags',
        description: 'Used when a page has no SEO fields of its own.',
        keys: [
            'default_title',
            'default_description',
            'default_keywords',
            'canonical_base',
        ],
    },
    {
        title: 'Social sharing',
        description: 'How links look when shared on social media.',
        keys: [
            'og_title',
            'og_description',
            'og_image',
            'twitter_card',
            'twitter_site',
        ],
    },
    {
        title: 'Structured data',
        description:
            'JSON-LD that helps search engines understand the business.',
        keys: [
            'publisher',
            'schema_organization',
            'schema_local_business',
            'business_type',
            'price_range',
            'hreflang',
        ],
    },
    {
        title: 'Verification & analytics',
        description: 'Search console codes and tracking IDs.',
        keys: ['google_verification', 'bing_verification', 'ga_id', 'gtm_id'],
    },
    {
        title: 'Sitemap & robots.txt',
        description: 'What crawlers can find and where they may go.',
        keys: ['sitemap_enabled', 'sitemap_extra', 'robots_txt'],
    },
];

const sections = computed(() => {
    const all = props.group.fields;
    const listed = new Set(SECTIONS.flatMap((s) => s.keys));
    const rest = Object.keys(all).filter((k) => !listed.has(k));
    const defs = rest.length
        ? [
              ...SECTIONS,
              {
                  title: 'More',
                  description: '',
                  keys: rest,
              },
          ]
        : SECTIONS;

    return defs
        .map((s) => ({
            ...s,
            fields: Object.fromEntries(
                s.keys.filter((k) => k in all).map((k) => [k, all[k]]),
            ) as Record<string, FieldDef>,
        }))
        .filter((s) => Object.keys(s.fields).length > 0);
});

const media = reactive<Record<number, MediaItem>>({});
// Wrapped in an object so SchemaFields can replace `form.values` through v-model.
const form = reactive({
    values: clone(props.values) as Record<string, unknown>,
});
const saving = ref(false);
const errors = ref<Record<string, string>>({});

function save(): void {
    const payload: Record<string, unknown> = {};

    for (const [name, value] of Object.entries(form.values)) {
        payload[name] =
            props.group.fields[name]?.type === 'image'
                ? ((value as MediaItem | null)?.id ?? null)
                : value;
    }

    router.put(update().url, payload as Record<string, never>, {
        preserveScroll: true,
        onStart: () => (saving.value = true),
        onFinish: () => (saving.value = false),
        onSuccess: () => (errors.value = {}),
        onError: (e) => (errors.value = e),
    });
}
</script>

<template>
    <Head title="SEO settings" />

    <PageHeader
        eyebrow="SEO Management"
        title="Global SEO & sitemap"
        description="Search and sharing defaults, indexing, sitemap and robots.txt. Pages, posts and universities can override these."
    />

    <form class="flex max-w-3xl flex-col gap-4" @submit.prevent="save">
        <PanelCard
            v-for="section in sections"
            :key="section.title"
            :title="section.title"
            :description="section.description"
        >
            <div class="flex flex-col gap-4">
                <SchemaFields
                    v-model="form.values"
                    :fields="section.fields"
                    :media="media"
                    :errors="errors"
                    images-as-objects
                />
            </div>
        </PanelCard>

        <div>
            <Button type="submit" :disabled="saving">Save SEO settings</Button>
        </div>
    </form>
</template>
