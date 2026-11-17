<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { destroy, index, store, update } from '@/routes/admin/email/templates';
import type { EmailTemplateItem } from '@/types';

const props = defineProps<{
    templates: (EmailTemplateItem & { is_active: boolean })[];
    placeholders: Record<string, string>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Email templates', href: index() }] },
});

const selectedId = ref<number | null>(null);
const form = useForm({
    name: '',
    category: '',
    subject: '',
    body: '',
    is_active: true,
});

const grouped = computed(() => {
    const groups = new Map<string, typeof props.templates>();

    for (const t of props.templates) {
        const key = t.category ?? 'Uncategorised';
        groups.set(key, [...(groups.get(key) ?? []), t]);
    }

    return [...groups.entries()];
});

const categories = computed(
    () =>
        [
            ...new Set(props.templates.map((t) => t.category).filter(Boolean)),
        ] as string[],
);

function select(t: EmailTemplateItem & { is_active: boolean }): void {
    selectedId.value = t.id;
    form.defaults({
        name: t.name,
        category: t.category ?? '',
        subject: t.subject,
        body: t.body,
        is_active: t.is_active,
    });
    form.reset();
    form.clearErrors();
}

function startNew(): void {
    selectedId.value = null;
    form.defaults({
        name: '',
        category: '',
        subject: '',
        body: '',
        is_active: true,
    });
    form.reset();
    form.clearErrors();
}

function save(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedId.value === null) {
                startNew();
            } else {
                form.defaults();
            }
        },
    };
    const payload = form.transform((d) => ({
        ...d,
        category: d.category || null,
    }));

    if (selectedId.value === null) {
        payload.submit(store(), options);
    } else {
        payload.put(update(selectedId.value).url, options);
    }
}

function insertPlaceholder(key: string): void {
    const text = token(key);
    const el = document.getElementById(
        'template-body',
    ) as HTMLTextAreaElement | null;
    const start = el?.selectionStart ?? form.body.length;
    const end = el?.selectionEnd ?? start;
    form.body = form.body.slice(0, start) + text + form.body.slice(end);
    requestAnimationFrame(() => {
        el?.focus();
        el?.setSelectionRange(start + text.length, start + text.length);
    });
}

const deleting = ref(false);
function remove(): void {
    if (selectedId.value === null) return;

    router.delete(destroy(selectedId.value).url, {
        preserveScroll: true,
        onSuccess: () => startNew(),
        onError: (e) => toast.error(Object.values(e)[0] ?? 'Could not delete.'),
        onFinish: () => (deleting.value = false),
    });
}

const err = (key: string) =>
    (form.errors as Record<string, string | undefined>)[key] ??
    (key === 'name'
        ? (form.errors as Record<string, string | undefined>).slug
        : undefined);

/** Shown as a literal placeholder, e.g. {{ name }}. */
const token = (key: string): string => `{{ ${key} }}`;
</script>

<template>
    <Head title="Email templates" />

    <PageHeader
        eyebrow="Email / Roundcube"
        title="Email templates"
        description="Ready-made emails for compose and bulk email. Placeholders are filled in for each recipient."
    >
        <template #actions>
            <Button @click="startNew"
                ><AppIcon name="add" :size="18" /> New template</Button
            >
        </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[minmax(240px,1fr)_minmax(0,2fr)]">
        <PanelCard title="Templates" class="self-start">
            <EmptyState
                v-if="templates.length === 0"
                icon="description"
                title="No templates yet"
                description="Write your first one on the right."
            />
            <div v-else class="flex flex-col gap-4">
                <section v-for="[category, items] in grouped" :key="category">
                    <h3
                        class="mb-1.5 text-[11px] font-bold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ category }}
                    </h3>
                    <ul class="flex flex-col gap-1">
                        <li v-for="t in items" :key="t.id">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-2 rounded-md px-2.5 py-2 text-left text-[13px] hover:bg-muted"
                                :class="
                                    selectedId === t.id
                                        ? 'bg-muted font-semibold'
                                        : ''
                                "
                                :aria-current="
                                    selectedId === t.id ? 'true' : undefined
                                "
                                @click="select(t)"
                            >
                                <span class="truncate">{{ t.name }}</span>
                                <StatusBadge
                                    v-if="!t.is_active"
                                    label="Off"
                                    tone="neutral"
                                />
                            </button>
                        </li>
                    </ul>
                </section>
            </div>
        </PanelCard>

        <PanelCard
            :title="selectedId === null ? 'New template' : 'Edit template'"
        >
            <form class="flex flex-col gap-3.5" @submit.prevent="save">
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Name"
                        required
                        :error="err('name')"
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            maxlength="120"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, describedBy }"
                        label="Category"
                        :error="err('category')"
                        hint="e.g. Admission, Payment, Visa"
                    >
                        <Input
                            :id="id"
                            v-model="form.category"
                            list="template-categories"
                            maxlength="60"
                            :aria-describedby="describedBy"
                        />
                        <datalist id="template-categories">
                            <option
                                v-for="c in categories"
                                :key="c"
                                :value="c"
                            />
                        </datalist>
                    </FormField>
                </div>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Subject"
                    required
                    :error="err('subject')"
                >
                    <Input
                        :id="id"
                        v-model="form.subject"
                        maxlength="200"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    id="template-body"
                    v-slot="{ id, invalid, describedBy }"
                    label="Message"
                    required
                    :error="err('body')"
                    hint="Plain text. Links become clickable."
                >
                    <TextArea
                        :id="id"
                        v-model="form.body"
                        :rows="12"
                        :maxlength="20000"
                        :invalid="invalid"
                        :described-by="describedBy"
                    />
                </FormField>
                <div
                    class="flex flex-wrap gap-1.5"
                    aria-label="Insert a placeholder"
                >
                    <button
                        v-for="(label, key) in placeholders"
                        :key="key"
                        type="button"
                        class="rounded-full border border-border px-2.5 py-1 text-[11px] hover:bg-muted"
                        :title="label"
                        @click="insertPlaceholder(String(key))"
                    >
                        {{ token(String(key)) }}
                    </button>
                </div>
                <label class="flex items-center gap-2 text-[13px]">
                    <Checkbox
                        :model-value="form.is_active"
                        @update:model-value="
                            (v) => (form.is_active = v === true)
                        "
                    />
                    Available in compose and bulk email
                </label>
                <div class="flex flex-wrap justify-between gap-2">
                    <Button
                        v-if="selectedId !== null"
                        type="button"
                        variant="ghost"
                        class="text-danger"
                        @click="deleting = true"
                    >
                        Delete
                    </Button>
                    <span v-else />
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.isDirty"
                    >
                        {{
                            selectedId === null
                                ? 'Add template'
                                : 'Save changes'
                        }}
                    </Button>
                </div>
            </form>
        </PanelCard>
    </div>

    <ConfirmDialog
        v-model:open="deleting"
        title="Delete this template?"
        description="Emails already sent keep their text."
        confirm-label="Delete"
        destructive
        @confirm="remove"
    />
</template>
