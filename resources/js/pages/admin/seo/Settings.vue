<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
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

const media = reactive<Record<number, MediaItem>>({});
const values = reactive(clone(props.values) as Record<string, unknown>);
const saving = ref(false);
const errors = ref<Record<string, string>>({});

function save(): void {
    const payload: Record<string, unknown> = {};

    for (const [name, value] of Object.entries(values)) {
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
        description="Search and sharing defaults, indexing, sitemap and robots.txt."
    />

    <PanelCard :title="group.label" :description="group.description">
        <form class="flex max-w-3xl flex-col gap-4" @submit.prevent="save">
            <SchemaFields
                v-model="values"
                :fields="group.fields"
                :media="media"
                :errors="errors"
                images-as-objects
            />
            <div>
                <Button type="submit" :disabled="saving"
                    >Save SEO settings</Button
                >
            </div>
        </form>
    </PanelCard>
</template>
