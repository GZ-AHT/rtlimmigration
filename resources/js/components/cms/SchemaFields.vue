<script setup lang="ts">
import AppIcon from '@/components/panel/AppIcon.vue';
import GalleryField from '@/components/cms/GalleryField.vue';
import ImageField from '@/components/cms/ImageField.vue';
import LinkField from '@/components/cms/LinkField.vue';
import RichTextEditor from '@/components/cms/RichTextEditor.vue';
import SchemaFields from '@/components/cms/SchemaFields.vue';
import FormField from '@/components/panel/FormField.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { FieldDef, LinkValue, MediaItem } from '@/types';

/**
 * Renders a form from field definitions (page sections, site settings).
 * Images are stored as media ids; `media` maps ids to their details so
 * thumbnails can be shown, and is updated when new images are picked.
 */
const props = withDefaults(
    defineProps<{
        fields: Record<string, FieldDef>;
        media: Record<number, MediaItem>;
        errors?: Record<string, string | undefined>;
        errorPrefix?: string;
        imagesAsObjects?: boolean;
    }>(),
    { errors: () => ({}), errorPrefix: '', imagesAsObjects: false },
);
const model = defineModel<Record<string, unknown>>({ required: true });

const err = (key: string) => props.errors[`${props.errorPrefix}${key}`];
const str = (key: string) => String(model.value[key] ?? '');
const set = (key: string, value: unknown) =>
    (model.value = { ...model.value, [key]: value });

function image(key: string): MediaItem | null {
    const v = model.value[key];

    if (props.imagesAsObjects) {
        return (v as MediaItem | null) ?? null;
    }

    return typeof v === 'number' ? (props.media[v] ?? null) : null;
}

function setImage(key: string, item: MediaItem | null): void {
    if (item) {
        props.media[item.id] = item;
    }

    const value = props.imagesAsObjects ? item : (item?.id ?? null);

    // Banners: choosing a video switches "Background" to Video (and removing it back to
    // Image), so an uploaded video always plays. Both change in ONE update: model.value
    // only reflects a change after the parent re-renders, so a second set() here would
    // overwrite the first and lose the chosen video.
    if (props.fields[key]?.type === 'video' && 'media_type' in props.fields) {
        model.value = {
            ...model.value,
            [key]: value,
            media_type: item ? 'video' : 'image',
        };

        return;
    }

    set(key, value);
}

const items = (key: string) =>
    Array.isArray(model.value[key])
        ? (model.value[key] as Record<string, unknown>[])
        : [];

function defaultsFor(
    fields: Record<string, FieldDef>,
): Record<string, unknown> {
    return Object.fromEntries(
        Object.entries(fields).map(([k, f]) => [
            k,
            f.type === 'toggle'
                ? Boolean(f.default)
                : f.type === 'link'
                  ? { label: '', url: '' }
                  : f.type === 'image'
                    ? null
                    : f.type === 'repeater'
                      ? []
                      : f.type === 'number'
                        ? Number(f.default ?? 0)
                        : '',
        ]),
    );
}

function addItem(key: string, field: FieldDef): void {
    set(key, [...items(key), defaultsFor(field.fields ?? {})]);
}

function updateItem(
    key: string,
    index: number,
    value: Record<string, unknown>,
): void {
    const next = [...items(key)];
    next[index] = value;
    set(key, next);
}

function moveItem(key: string, index: number, by: -1 | 1): void {
    const next = [...items(key)];
    const to = index + by;

    if (to < 0 || to >= next.length) return;

    [next[index], next[to]] = [next[to], next[index]];
    set(key, next);
}

function removeItem(key: string, index: number): void {
    set(
        key,
        items(key).filter((_, i) => i !== index),
    );
}

const optionList = (o?: Record<string, string>) =>
    Object.entries(o ?? {}).map(([value, label]) => ({ value, label }));
</script>

<template>
    <div class="flex flex-col gap-3.5">
        <template v-for="(field, key) in fields" :key="key">
            <FormField
                v-if="['text', 'email', 'url'].includes(field.type)"
                v-slot="{ id, invalid, describedBy }"
                :label="field.label"
                :required="field.required"
                :error="err(String(key))"
                :hint="field.hint"
            >
                <Input
                    :id="id"
                    :type="field.type === 'text' ? 'text' : field.type"
                    :model-value="str(String(key))"
                    :maxlength="field.max"
                    :aria-invalid="invalid"
                    :aria-describedby="describedBy"
                    @update:model-value="
                        (v: string | number) => set(String(key), String(v))
                    "
                />
            </FormField>

            <FormField
                v-else-if="field.type === 'textarea' || field.type === 'code'"
                v-slot="{ id, invalid, describedBy }"
                :label="field.label"
                :error="err(String(key))"
                :hint="
                    field.hint ??
                    (field.max
                        ? `${str(String(key)).length}/${field.max}`
                        : undefined)
                "
            >
                <TextArea
                    :id="id"
                    :model-value="str(String(key))"
                    :rows="field.type === 'code' ? 8 : (field.rows ?? 3)"
                    :maxlength="field.max"
                    :invalid="invalid"
                    :described-by="describedBy"
                    :class="
                        field.type === 'code' ? 'font-mono text-[12px]' : ''
                    "
                    @update:model-value="(v) => set(String(key), v)"
                />
            </FormField>

            <FormField
                v-else-if="field.type === 'richtext'"
                :label="field.label"
                :error="err(String(key))"
            >
                <template #default="{ id, invalid }">
                    <RichTextEditor
                        :id="id"
                        :model-value="str(String(key))"
                        :invalid="invalid"
                        @update:model-value="(v) => set(String(key), v)"
                    />
                </template>
            </FormField>

            <FormField
                v-else-if="field.type === 'number'"
                v-slot="{ id, invalid }"
                :label="field.label"
                :error="err(String(key))"
            >
                <Input
                    :id="id"
                    type="number"
                    class="max-w-32"
                    :min="field.min"
                    :max="field.max"
                    :model-value="Number(model[String(key)] ?? 0)"
                    :aria-invalid="invalid"
                    @update:model-value="
                        (v: string | number) => set(String(key), Number(v))
                    "
                />
            </FormField>

            <FormField
                v-else-if="field.type === 'select'"
                v-slot="{ id }"
                :label="field.label"
                :error="err(String(key))"
                :hint="field.hint"
            >
                <SelectInput
                    :id="id"
                    :model-value="str(String(key))"
                    :options="optionList(field.options)"
                    @update:model-value="(v) => set(String(key), v)"
                />
            </FormField>

            <FormField
                v-else-if="field.type === 'color'"
                v-slot="{ id, invalid }"
                :label="field.label"
                :error="err(String(key))"
            >
                <div class="flex items-center gap-2">
                    <input
                        :id="id"
                        type="color"
                        class="h-9 w-12 cursor-pointer rounded border border-input bg-background"
                        :value="str(String(key)) || '#000000'"
                        @input="
                            (e) =>
                                set(
                                    String(key),
                                    (e.target as HTMLInputElement).value,
                                )
                        "
                    />
                    <Input
                        class="max-w-32 font-mono"
                        :model-value="str(String(key))"
                        maxlength="7"
                        :aria-invalid="invalid"
                        :aria-label="`${field.label} code`"
                        @update:model-value="
                            (v: string | number) => set(String(key), String(v))
                        "
                    />
                </div>
            </FormField>

            <label
                v-else-if="field.type === 'toggle'"
                class="flex items-center gap-2 text-[13px]"
            >
                <Checkbox
                    :model-value="Boolean(model[String(key)])"
                    @update:model-value="(v) => set(String(key), v === true)"
                />
                {{ field.label }}
            </label>

            <div v-else-if="field.type === 'image' || field.type === 'video'">
                <ImageField
                    :label="field.label"
                    :hint="field.hint"
                    :kind="field.type"
                    :model-value="image(String(key))"
                    @update:model-value="(v) => setImage(String(key), v)"
                />
                <p v-if="err(String(key))" class="mt-1 text-xs text-danger">
                    {{ err(String(key)) }}
                </p>
            </div>

            <GalleryField
                v-else-if="field.type === 'images'"
                :label="field.label"
                :max="field.max"
                :model-value="(model[String(key)] as MediaItem[]) ?? []"
                @update:model-value="(v) => set(String(key), v)"
            />

            <LinkField
                v-else-if="field.type === 'link'"
                :label="field.label"
                :error="err(`${String(key)}.url`)"
                :model-value="
                    (model[String(key)] as LinkValue) ?? { label: '', url: '' }
                "
                @update:model-value="(v) => set(String(key), v)"
            />

            <fieldset
                v-else-if="field.type === 'repeater'"
                class="rounded-lg border border-border p-3"
            >
                <legend class="px-1 text-[13px] font-semibold">
                    {{ field.label }}
                    <span class="font-normal text-muted-foreground"
                        >({{ items(String(key)).length }}/{{ field.max }})</span
                    >
                </legend>
                <ol class="flex flex-col gap-3">
                    <li
                        v-for="(item, i) in items(String(key))"
                        :key="i"
                        class="rounded-md border border-border bg-muted/20 p-3"
                    >
                        <div
                            class="mb-2 flex items-center justify-between text-[12px] font-semibold text-muted-foreground"
                        >
                            <span
                                >{{ i + 1 }}.
                                {{
                                    String(
                                        item.title ??
                                            item.name ??
                                            item.value ??
                                            item.label ??
                                            '',
                                    ) || 'Item'
                                }}</span
                            >
                            <span class="flex gap-0.5">
                                <button
                                    type="button"
                                    class="grid size-7 place-items-center rounded hover:bg-muted disabled:opacity-30"
                                    :disabled="i === 0"
                                    aria-label="Move up"
                                    @click="moveItem(String(key), i, -1)"
                                >
                                    <AppIcon
                                        name="keyboard_arrow_up"
                                        :size="18"
                                    />
                                </button>
                                <button
                                    type="button"
                                    class="grid size-7 place-items-center rounded hover:bg-muted disabled:opacity-30"
                                    :disabled="
                                        i === items(String(key)).length - 1
                                    "
                                    aria-label="Move down"
                                    @click="moveItem(String(key), i, 1)"
                                >
                                    <AppIcon
                                        name="keyboard_arrow_down"
                                        :size="18"
                                    />
                                </button>
                                <button
                                    type="button"
                                    class="grid size-7 place-items-center rounded text-danger hover:bg-muted"
                                    aria-label="Remove item"
                                    @click="removeItem(String(key), i)"
                                >
                                    <AppIcon name="delete" :size="18" />
                                </button>
                            </span>
                        </div>
                        <SchemaFields
                            :fields="field.fields ?? {}"
                            :media="media"
                            :errors="errors"
                            :error-prefix="`${errorPrefix}${String(key)}.${i}.`"
                            :model-value="item"
                            @update:model-value="
                                (v) => updateItem(String(key), i, v)
                            "
                        />
                    </li>
                </ol>
                <button
                    v-if="items(String(key)).length < (field.max ?? 12)"
                    type="button"
                    class="mt-3 inline-flex items-center gap-1 text-[13px] font-semibold text-primary hover:underline"
                    @click="addItem(String(key), field)"
                >
                    <AppIcon name="add" :size="16" /> Add
                </button>
            </fieldset>
        </template>
    </div>
</template>
