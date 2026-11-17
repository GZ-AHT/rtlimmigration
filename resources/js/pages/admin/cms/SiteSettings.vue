<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import SchemaFields from '@/components/cms/SchemaFields.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import { Button } from '@/components/ui/button';
import { settings as settingsRoute } from '@/routes/admin/cms';
import { update } from '@/routes/admin/cms/settings';
import type { FieldDef, MediaItem } from '@/types';

type Group = {
    label: string;
    description: string;
    fields: Record<string, FieldDef>;
};

const props = defineProps<{
    groups: Record<string, Group>;
    values: Record<string, Record<string, unknown>>;
    tab: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Site settings', href: settingsRoute() }],
    },
});

const active = ref(props.groups[props.tab] ? props.tab : 'general');
watch(active, (v) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', v);
    window.history.replaceState(window.history.state, '', url);
});

const tabs = computed(() =>
    Object.entries(props.groups).map(([key, g]) => ({ key, label: g.label })),
);
const media = reactive<Record<number, MediaItem>>({});

// Values per group; saving a tab sends only that group, so it never overwrites another tab.
const values = reactive(
    structuredClone(props.values) as Record<string, Record<string, unknown>>,
);
const saving = ref<string | null>(null);
const errors = ref<Record<string, string>>({});

function save(key: string): void {
    const fields = props.groups[key].fields;
    const payload: Record<string, unknown> = {};

    for (const [name, value] of Object.entries(values[key] ?? {})) {
        const type = fields[name]?.type;
        payload[name] =
            type === 'image'
                ? ((value as MediaItem | null)?.id ?? null)
                : type === 'images'
                  ? ((value as MediaItem[]) ?? []).map((m) => m.id)
                  : value;
    }

    router.put(update(key).url, payload as Record<string, never>, {
        preserveScroll: true,
        onStart: () => (saving.value = key),
        onFinish: () => (saving.value = null),
        onSuccess: () => (errors.value = {}),
        onError: (e) => (errors.value = e),
    });
}
</script>

<template>
    <Head title="Site settings" />

    <PageHeader
        eyebrow="Website CMS"
        title="Site settings"
        description="Everything about the website that isn't page content."
    />

    <PanelTabs v-model="active" label="Settings group" :tabs="tabs" />

    <template v-for="(group, key) in groups" :key="key">
        <PanelCard
            v-if="active === key"
            :title="group.label"
            :description="group.description"
        >
            <form
                class="flex max-w-3xl flex-col gap-4"
                @submit.prevent="save(String(key))"
            >
                <SchemaFields
                    v-model="values[key]"
                    :fields="group.fields"
                    :media="media"
                    :errors="errors"
                    images-as-objects
                />
                <div>
                    <Button type="submit" :disabled="saving === key"
                        >Save {{ group.label.toLowerCase() }}</Button
                    >
                </div>
            </form>
        </PanelCard>
    </template>
</template>
