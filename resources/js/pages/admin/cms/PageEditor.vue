<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import SchemaFields from '@/components/cms/SchemaFields.vue';
import SeoPanel from '@/components/cms/SeoPanel.vue';
import SortableList from '@/components/cms/SortableList.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { relativeTime } from '@/lib/format';
import { destroy, index, store, update } from '@/routes/admin/pages';
import { restore as restoreRevision } from '@/routes/admin/pages/revisions';
import type {
    MediaItem,
    PageSection,
    PublishState,
    SectionTypeDef,
    SeoValue,
} from '@/types';

type Revision = {
    id: number;
    title: string;
    sections: number;
    author: string | null;
    created_at: string | null;
};
type PageData = {
    id: number;
    title: string;
    slug: string;
    path: string;
    system_key: string | null;
    category: string;
    status: 'draft' | 'published';
    published_at: string | null;
    visibility: string;
    sections: PageSection[];
    custom_css: string;
    custom_js: string;
    state: PublishState;
    seo: SeoValue;
    revisions: Revision[];
};

const props = defineProps<{
    page: PageData | null;
    sectionTypes: Record<string, SectionTypeDef>;
    sectionDefaults: Record<string, Record<string, unknown>>;
    backgrounds: Record<string, string>;
    spacing: Record<string, string>;
    visibility: Record<string, string>;
    media: Record<number, MediaItem>;
    categories: string[];
    can: { manage: boolean; publish: boolean; custom_code: boolean };
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Pages', href: index() }] } });

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
const mediaMap = reactive<Record<number, MediaItem>>({ ...props.media });

const form = useForm({
    title: props.page?.title ?? '',
    slug: props.page?.slug ?? '',
    category: props.page?.category ?? '',
    status: props.page?.status ?? 'draft',
    published_at: props.page?.published_at ?? '',
    visibility: props.page?.visibility ?? 'public',
    custom_css: props.page?.custom_css ?? '',
    custom_js: props.page?.custom_js ?? '',
    seo: props.page?.seo ?? { ...emptySeo },
});

// Sections live outside useForm: their free-form data doesn't fit Inertia's form typing.
const sections = ref<PageSection[]>(
    structuredClone(props.page?.sections ?? []),
);
const sectionsDirty = ref(false);
watch(sections, () => (sectionsDirty.value = true), { deep: true });

const expanded = ref<string | null>(null);
const adding = ref(false);
const deleting = ref(false);
const restoring = ref<Revision | null>(null);
const isHome = computed(() => props.page?.system_key === 'home');

function newId(): string {
    return crypto.randomUUID
        ? crypto.randomUUID()
        : `s-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function addSection(type: string): void {
    const section: PageSection = {
        id: newId(),
        type,
        enabled: true,
        data: structuredClone(props.sectionDefaults[type] ?? {}),
        settings: {
            background: 'white',
            spacing: 'default',
            anchor: '',
            hide_on_mobile: false,
        },
    };
    sections.value.push(section);
    expanded.value = section.id;
    adding.value = false;
}

function duplicate(index: number): void {
    const copy = structuredClone(sections.value[index]);
    copy.id = newId();
    sections.value.splice(index + 1, 0, copy);
}

function remove(index: number): void {
    sections.value.splice(index, 1);
}

function summary(s: PageSection): string {
    const d = s.data as Record<string, unknown>;
    return (
        String(d.title ?? d.eyebrow ?? '').split('\n')[0] ||
        props.sectionTypes[s.type]?.description ||
        ''
    );
}

const err = (k: string) =>
    (form.errors as Record<string, string | undefined>)[k];

function save(): void {
    const payload = form.transform((d) => ({
        ...d,
        slug: d.slug || null,
        category: d.category || null,
        published_at: d.published_at || null,
        seo: {
            ...d.seo,
            og_image: undefined,
            og_image_id: d.seo.og_image?.id ?? null,
        },
    }));

    if (props.page) {
        payload.put(update(props.page.id).url, { preserveScroll: true });
    } else {
        payload.post(store().url);
    }
}

function doRestore(): void {
    if (!props.page || !restoring.value) return;
    router.post(
        restoreRevision([props.page.id, restoring.value.id]).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                form.title = props.page!.title;
                sections.value = structuredClone(props.page!.sections);
                void nextTick(() => (sectionsDirty.value = false));
            },
            onFinish: () => (restoring.value = null),
        },
    );
}
</script>

<template>
    <Head :title="page ? `Edit: ${page.title}` : 'New page'" />

    <PageHeader
        :eyebrow="isHome ? 'Website CMS' : 'Page management'"
        :title="isHome ? 'Homepage' : page ? 'Edit page' : 'New page'"
        :description="
            isHome
                ? 'Show, hide, reorder and edit the homepage sections.'
                : undefined
        "
    >
        <template #actions>
            <StatusBadge
                v-if="page"
                :label="page.state.label"
                :tone="page.state.tone"
            />
            <Button
                v-if="page && page.state.key === 'published'"
                variant="outline"
                as-child
            >
                <a :href="page.path" target="_blank" rel="noopener"
                    ><AppIcon name="open_in_new" :size="16" /> View</a
                >
            </Button>
            <Button v-if="can.manage" :disabled="form.processing" @click="save"
                >Save</Button
            >
        </template>
    </PageHeader>

    <form
        class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]"
        @submit.prevent="save"
    >
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard>
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Page title"
                        required
                        :error="err('title')"
                    >
                        <Input
                            :id="id"
                            v-model="form.title"
                            maxlength="200"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid, describedBy }"
                        label="Address"
                        :error="err('slug')"
                        :hint="
                            page?.system_key
                                ? 'Built-in page: the address is fixed.'
                                : 'Blank: made from the title.'
                        "
                    >
                        <div
                            class="flex items-center gap-1 text-[13px] text-muted-foreground"
                        >
                            <span>/</span>
                            <Input
                                :id="id"
                                v-model="form.slug"
                                :disabled="!!page?.system_key"
                                maxlength="200"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </div>
                    </FormField>
                </div>
            </PanelCard>

            <PanelCard
                :title="`Sections (${sections.length})`"
                description="Drag to reorder. Hidden sections stay saved but don't show on the website."
            >
                <template v-if="can.manage" #actions>
                    <Button type="button" size="sm" @click="adding = true"
                        ><AppIcon name="add" :size="16" /> Add section</Button
                    >
                </template>
                <p
                    v-if="sections.length === 0"
                    class="py-6 text-center text-[13px] text-muted-foreground"
                >
                    No sections yet. Add the first one.
                </p>
                <SortableList
                    :items="sections"
                    label="Page sections"
                    :disabled="!can.manage"
                    @reorder="(next) => (sections = next)"
                >
                    <template #default="{ item, index }">
                        <div class="pr-2">
                            <div class="flex items-center gap-2">
                                <AppIcon
                                    :name="
                                        sectionTypes[item.type]?.icon ??
                                        'square'
                                    "
                                    :size="18"
                                    class="shrink-0 text-muted-foreground"
                                />
                                <button
                                    type="button"
                                    class="min-w-0 flex-1 text-left"
                                    :aria-expanded="expanded === item.id"
                                    @click="
                                        expanded =
                                            expanded === item.id
                                                ? null
                                                : item.id
                                    "
                                >
                                    <span
                                        class="block text-[13px] font-semibold"
                                        :class="
                                            item.enabled
                                                ? 'text-foreground'
                                                : 'text-muted-foreground line-through'
                                        "
                                        >{{
                                            sectionTypes[item.type]?.label ??
                                            item.type
                                        }}</span
                                    >
                                    <span
                                        class="block truncate text-[11px] text-muted-foreground"
                                        >{{ summary(item) }}</span
                                    >
                                </button>
                                <template v-if="can.manage">
                                    <label
                                        class="flex items-center gap-1.5 text-[12px]"
                                        :title="
                                            item.enabled
                                                ? 'Shown on the website'
                                                : 'Hidden'
                                        "
                                    >
                                        <Checkbox
                                            :model-value="item.enabled"
                                            :aria-label="`Show ${sectionTypes[item.type]?.label}`"
                                            @update:model-value="
                                                (v) =>
                                                    (item.enabled = v === true)
                                            "
                                        />
                                        <span class="hidden sm:inline"
                                            >Show</span
                                        >
                                    </label>
                                    <button
                                        type="button"
                                        class="grid size-7 place-items-center rounded hover:bg-muted"
                                        aria-label="Duplicate section"
                                        @click="duplicate(index)"
                                    >
                                        <AppIcon
                                            name="content_copy"
                                            :size="16"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="grid size-7 place-items-center rounded text-danger hover:bg-muted"
                                        aria-label="Remove section"
                                        @click="remove(index)"
                                    >
                                        <AppIcon name="delete" :size="16" />
                                    </button>
                                </template>
                                <AppIcon
                                    :name="
                                        expanded === item.id
                                            ? 'expand_less'
                                            : 'expand_more'
                                    "
                                    :size="20"
                                    class="text-muted-foreground"
                                />
                            </div>
                            <div
                                v-if="expanded === item.id"
                                class="mt-3 border-t border-border pt-3"
                                :inert="!can.manage || undefined"
                            >
                                <p
                                    v-if="sectionTypes[item.type]?.dynamic"
                                    class="mb-3 flex items-center gap-1.5 rounded-md bg-info-soft px-2.5 py-1.5 text-[12px] text-info"
                                >
                                    <AppIcon name="sync" :size="14" /> Shows
                                    live content:
                                    {{ sectionTypes[item.type].dynamic }}.
                                </p>
                                <SchemaFields
                                    v-model="item.data"
                                    :fields="
                                        sectionTypes[item.type]?.fields ?? {}
                                    "
                                    :media="mediaMap"
                                    :errors="
                                        form.errors as Record<string, string>
                                    "
                                    :error-prefix="`sections.${index}.data.`"
                                />
                                <details
                                    class="mt-4 rounded-md border border-border px-3 py-2"
                                >
                                    <summary
                                        class="cursor-pointer text-[12px] font-semibold"
                                    >
                                        Section appearance
                                    </summary>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <FormField
                                            v-slot="{ id }"
                                            label="Background"
                                        >
                                            <SelectInput
                                                :id="id"
                                                v-model="
                                                    item.settings.background
                                                "
                                                :options="
                                                    Object.entries(
                                                        backgrounds,
                                                    ).map(([value, label]) => ({
                                                        value,
                                                        label,
                                                    }))
                                                "
                                            />
                                        </FormField>
                                        <FormField
                                            v-slot="{ id }"
                                            label="Spacing"
                                        >
                                            <SelectInput
                                                :id="id"
                                                v-model="item.settings.spacing"
                                                :options="
                                                    Object.entries(spacing).map(
                                                        ([value, label]) => ({
                                                            value,
                                                            label,
                                                        }),
                                                    )
                                                "
                                            />
                                        </FormField>
                                        <FormField
                                            v-slot="{ id }"
                                            label="Anchor"
                                            hint="Link to it with #anchor."
                                        >
                                            <Input
                                                :id="id"
                                                v-model="item.settings.anchor"
                                                maxlength="40"
                                                placeholder="e.g. services"
                                            />
                                        </FormField>
                                        <label
                                            class="flex items-center gap-2 self-end pb-2 text-[13px]"
                                        >
                                            <Checkbox
                                                :model-value="
                                                    item.settings.hide_on_mobile
                                                "
                                                @update:model-value="
                                                    (v) =>
                                                        (item.settings.hide_on_mobile =
                                                            v === true)
                                                "
                                            />
                                            Hide on phones
                                        </label>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </template>
                </SortableList>
                <p v-if="err('sections')" class="mt-2 text-xs text-danger">
                    {{ err('sections') }}
                </p>
            </PanelCard>

            <SeoPanel
                v-model="form.seo"
                :errors="form.errors as Record<string, string>"
                :fallback-title="form.title"
                :path="page?.path ?? `/${form.slug || '…'}`"
            />

            <PanelCard
                v-if="can.custom_code"
                title="Custom code"
                description="This page only. Super Admin; every change is logged."
            >
                <div class="flex flex-col gap-3">
                    <FormField
                        v-slot="{ id }"
                        label="Custom CSS"
                        :error="err('custom_css')"
                    >
                        <TextArea
                            :id="id"
                            v-model="form.custom_css"
                            :rows="6"
                            :maxlength="20000"
                            class="font-mono text-[12px]"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Custom JavaScript"
                        :error="err('custom_js')"
                        hint="Runs on this page for every visitor. Only paste code from sources you trust."
                    >
                        <TextArea
                            :id="id"
                            v-model="form.custom_js"
                            :rows="6"
                            :maxlength="20000"
                            class="font-mono text-[12px]"
                        />
                    </FormField>
                </div>
            </PanelCard>
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
                            hint="A future date schedules the page."
                        >
                            <Input
                                :id="id"
                                v-model="form.published_at"
                                type="datetime-local"
                                :aria-describedby="describedBy"
                            />
                        </FormField>
                    </template>
                    <p v-else class="text-[12px] text-muted-foreground">
                        You can edit drafts. Someone with publishing rights
                        makes them live.
                    </p>
                    <FormField v-slot="{ id }" label="Who can see it">
                        <SelectInput
                            :id="id"
                            v-model="form.visibility"
                            :options="
                                Object.entries(visibility).map(
                                    ([value, label]) => ({ value, label }),
                                )
                            "
                        />
                    </FormField>
                    <FormField
                        v-if="!page?.system_key"
                        v-slot="{ id }"
                        label="Category"
                        hint="Groups pages in this list, e.g. Legal, Landing pages."
                    >
                        <Input
                            :id="id"
                            v-model="form.category"
                            list="page-categories"
                            maxlength="60"
                        />
                        <datalist id="page-categories">
                            <option
                                v-for="c in categories"
                                :key="c"
                                :value="c"
                            />
                        </datalist>
                    </FormField>
                    <Button
                        v-if="can.manage"
                        type="submit"
                        :disabled="form.processing"
                        >Save page</Button
                    >
                    <p
                        v-if="form.isDirty || sectionsDirty"
                        class="text-[11px] text-warning"
                    >
                        Unsaved changes
                    </p>
                    <button
                        v-if="page && !page.system_key && can.manage"
                        type="button"
                        class="self-start text-[12px] text-danger hover:underline"
                        @click="deleting = true"
                    >
                        Move to trash
                    </button>
                </div>
            </PanelCard>

            <PanelCard
                v-if="page"
                title="Revision history"
                description="A copy is kept each time the page is saved."
            >
                <p
                    v-if="page.revisions.length === 0"
                    class="text-[12px] text-muted-foreground"
                >
                    No earlier versions yet.
                </p>
                <ul v-else class="flex max-h-80 flex-col gap-2 overflow-y-auto">
                    <li
                        v-for="r in page.revisions"
                        :key="r.id"
                        class="flex items-center justify-between gap-2 text-[12px]"
                    >
                        <span class="min-w-0">
                            <span class="block font-semibold">{{
                                relativeTime(r.created_at)
                            }}</span>
                            <span class="block truncate text-muted-foreground"
                                >{{ r.author ?? 'Unknown' }} ·
                                {{ r.sections }} sections</span
                            >
                        </span>
                        <button
                            v-if="can.manage"
                            type="button"
                            class="tiny-action"
                            @click="restoring = r"
                        >
                            Restore
                        </button>
                    </li>
                </ul>
            </PanelCard>

            <Link
                v-if="!isHome"
                :href="index()"
                class="text-[12px] font-semibold text-muted-foreground hover:underline"
                >← All pages</Link
            >
        </div>
    </form>

    <Dialog v-model:open="adding">
        <DialogContent class="rounded-[14px] sm:max-w-[720px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold"
                    >Add a section</DialogTitle
                >
                <DialogDescription class="text-xs"
                    >Every section follows the website design. Edit its content
                    after adding it.</DialogDescription
                >
            </DialogHeader>
            <ul class="grid max-h-[60vh] gap-2 overflow-y-auto sm:grid-cols-2">
                <li v-for="(type, key) in sectionTypes" :key="key">
                    <button
                        type="button"
                        class="flex w-full items-start gap-3 rounded-lg border border-border p-3 text-left hover:border-primary hover:bg-muted/40"
                        @click="addSection(String(key))"
                    >
                        <AppIcon
                            :name="type.icon"
                            :size="22"
                            class="mt-0.5 shrink-0 text-primary"
                        />
                        <span>
                            <span class="block text-[13px] font-semibold">{{
                                type.label
                            }}</span>
                            <span
                                class="block text-[12px] text-muted-foreground"
                                >{{ type.description }}</span
                            >
                        </span>
                    </button>
                </li>
            </ul>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        v-if="page"
        v-model:open="deleting"
        title="Move this page to trash?"
        description="It leaves the website at once. You can restore it from the trash."
        confirm-label="Move to trash"
        destructive
        @confirm="router.delete(destroy(page.id).url)"
    />
    <ConfirmDialog
        :open="restoring !== null"
        title="Restore this version?"
        description="The current version is kept in the history, so you can switch back."
        confirm-label="Restore"
        @update:open="(v) => !v && (restoring = null)"
        @confirm="doRestore"
    />
</template>
