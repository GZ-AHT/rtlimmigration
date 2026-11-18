<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SearchInput from '@/components/panel/SearchInput.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useFilters } from '@/composables/useFilters';
import { index as assessmentsIndex } from '@/routes/admin/skill-assessments';
import {
    destroy as destroyCategory,
    store as storeCategory,
    update as updateCategory,
} from '@/routes/admin/skill-assessments/categories';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/admin/skill-assessments/questions';
import type { Option } from '@/types';

/**
 * Shared skill migration question bank: multiple-choice questions with
 * points per answer, a category, and the countries that use them.
 */
type Q = {
    id: number;
    text: string;
    help: string;
    category_id: string;
    category: string;
    is_active: boolean;
    options: { id: number; label: string; points: number }[];
    assessment_ids: string[];
    countries: string[];
};
type Category = {
    id: number;
    name: string;
    sort_order: number;
    questions: number;
};

const props = defineProps<{
    questions: Q[];
    categories: Category[];
    assessments: Option[];
    filters: { category: string; assessment: string; search: string };
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Skill migration', href: assessmentsIndex() },
            { title: 'Question bank', href: index() },
        ],
    },
});

const { filters } = useFilters(
    { ...props.filters },
    { only: ['questions', 'filters'] },
);
const categoryOptions = computed(() =>
    props.categories.map((c) => ({ value: String(c.id), label: c.name })),
);

// Question dialog
const open = ref(false);
const editing = ref<Q | null>(null);
const form = useForm({
    text: '',
    help: '',
    category_id: '',
    is_active: true,
    options: [] as { label: string; points: number }[],
    assessment_ids: [] as string[],
});

function openNew(): void {
    editing.value = null;
    form.reset();
    form.options = [
        { label: '', points: 0 },
        { label: '', points: 10 },
    ];
    form.assessment_ids = filters.assessment ? [filters.assessment] : [];
    form.category_id = filters.category;
    form.clearErrors();
    open.value = true;
}

function openEdit(q: Q): void {
    editing.value = q;
    Object.assign(form, {
        text: q.text,
        help: q.help,
        category_id: q.category_id,
        is_active: q.is_active,
        options: q.options.map((o) => ({ label: o.label, points: o.points })),
        assessment_ids: [...q.assessment_ids],
    });
    form.clearErrors();
    open.value = true;
}

function toggleCountry(id: string, on: boolean): void {
    form.assessment_ids = on
        ? [...form.assessment_ids, id]
        : form.assessment_ids.filter((x) => x !== id);
}

function save(): void {
    const payload = form.transform((d) => ({
        ...d,
        category_id: d.category_id || null,
    }));
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (editing.value) {
        payload.put(update(editing.value.id).url, options);
    } else {
        payload.post(store().url, options);
    }
}

const deleting = ref<Q | null>(null);
const err = (k: string) =>
    (form.errors as Record<string, string | undefined>)[k];

// Categories
const catForm = useForm({ name: '', sort_order: 0 });
const editingCat = ref<Category | null>(null);
const deletingCat = ref<Category | null>(null);

function editCat(c: Category | null): void {
    editingCat.value = c;
    catForm.name = c?.name ?? '';
    catForm.sort_order = c?.sort_order ?? (props.categories.length + 1) * 10;
    catForm.clearErrors();
}

function saveCat(): void {
    const options = { preserveScroll: true, onSuccess: () => editCat(null) };

    if (editingCat.value) {
        catForm.put(updateCategory(editingCat.value.id).url, options);
    } else {
        catForm.post(storeCategory().url, options);
    }
}
</script>

<template>
    <Head title="Question bank" />
    <PageHeader
        eyebrow="Skill migration"
        title="Question bank"
        description="Multiple-choice questions shared by the country assessments. Each answer is worth points; tick the countries that should ask the question."
    >
        <template #actions>
            <Button @click="openNew"
                ><AppIcon name="add" :size="18" /> Add question</Button
            >
        </template>
    </PageHeader>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
        <PanelCard flush>
            <div class="grid gap-2 p-3 sm:grid-cols-3">
                <SearchInput
                    v-model="filters.search"
                    placeholder="Search questions…"
                />
                <SelectInput
                    v-model="filters.category"
                    :options="categoryOptions"
                    placeholder="All categories"
                    label="Category"
                />
                <SelectInput
                    v-model="filters.assessment"
                    :options="assessments"
                    placeholder="All countries"
                    label="Country"
                />
            </div>
            <p
                v-if="!questions.length"
                class="px-4 pb-8 text-center text-[13px] text-muted-foreground"
            >
                No questions match.
            </p>
            <ul>
                <li
                    v-for="q in questions"
                    :key="q.id"
                    class="flex items-start gap-3 border-t border-border px-4 py-3"
                >
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-semibold text-foreground">
                            {{ q.text }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            {{ q.category }} · {{ q.options.length }} answers
                            (0–{{
                                Math.max(0, ...q.options.map((o) => o.points))
                            }}
                            points) ·
                            {{
                                q.countries.length
                                    ? q.countries.join(', ')
                                    : 'Not used by any country'
                            }}
                        </p>
                    </div>
                    <StatusBadge
                        v-if="!q.is_active"
                        label="Inactive"
                        tone="warning"
                    />
                    <button
                        type="button"
                        class="tiny-action"
                        @click="openEdit(q)"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        class="tiny-action"
                        @click="deleting = q"
                    >
                        Delete
                    </button>
                </li>
            </ul>
        </PanelCard>

        <PanelCard
            title="Categories"
            description="Steps in the assessment, each scored separately in the result."
        >
            <ul class="flex flex-col gap-1">
                <li
                    v-for="c in categories"
                    :key="c.id"
                    class="flex items-center gap-2 rounded px-2 py-1.5 text-[13px] hover:bg-muted/60"
                >
                    <span class="flex-1"
                        >{{ c.name }}
                        <span class="text-[11px] text-muted-foreground"
                            >({{ c.questions }})</span
                        ></span
                    >
                    <button
                        type="button"
                        class="tiny-action"
                        @click="editCat(c)"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        class="tiny-action"
                        @click="deletingCat = c"
                    >
                        Delete
                    </button>
                </li>
            </ul>
            <form
                class="mt-3 flex flex-col gap-2 border-t border-border pt-3"
                @submit.prevent="saveCat"
            >
                <p class="text-[12px] font-semibold">
                    {{
                        editingCat
                            ? `Edit “${editingCat.name}”`
                            : 'New category'
                    }}
                </p>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Name"
                    :error="catForm.errors.name"
                >
                    <Input
                        :id="id"
                        v-model="catForm.name"
                        maxlength="80"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Order"
                    :error="catForm.errors.sort_order"
                >
                    <Input
                        :id="id"
                        v-model.number="catForm.sort_order"
                        type="number"
                        min="0"
                        max="1000"
                    />
                </FormField>
                <div class="flex gap-2">
                    <Button
                        type="submit"
                        size="sm"
                        :disabled="catForm.processing"
                        >{{ editingCat ? 'Save' : 'Add category' }}</Button
                    >
                    <Button
                        v-if="editingCat"
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="editCat(null)"
                        >Cancel</Button
                    >
                </div>
            </form>
        </PanelCard>
    </div>

    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto rounded-[14px] sm:max-w-[640px]"
        >
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">{{
                    editing ? 'Edit question' : 'Add question'
                }}</DialogTitle>
                <DialogDescription class="text-xs"
                    >Multiple choice. Each answer is worth points; visitors
                    never see the points.</DialogDescription
                >
            </DialogHeader>
            <form class="flex flex-col gap-3.5" @submit.prevent="save">
                <FormField
                    v-slot="{ id, invalid }"
                    label="Question"
                    required
                    :error="err('text')"
                >
                    <Input
                        :id="id"
                        v-model="form.text"
                        maxlength="300"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Help text (optional)"
                    :error="err('help')"
                >
                    <Input :id="id" v-model="form.help" maxlength="300" />
                </FormField>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id }"
                        label="Category"
                        :error="err('category_id')"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.category_id"
                            :options="categoryOptions"
                            placeholder="General"
                        />
                    </FormField>
                    <label class="mt-7 flex items-center gap-2 text-[13px]">
                        <Checkbox
                            :model-value="form.is_active"
                            @update:model-value="
                                (v) => (form.is_active = v === true)
                            "
                        />
                        Active (asked in assessments)
                    </label>
                </div>

                <div>
                    <p class="text-[13px] font-semibold">Answers and points</p>
                    <p class="text-[11px] text-muted-foreground">
                        The best answer’s points are the question’s maximum.
                        Points are never shown to visitors.
                    </p>
                    <p v-if="err('options')" class="mt-1 text-xs text-danger">
                        {{ err('options') }}
                    </p>
                    <div class="mt-2 flex flex-col gap-2">
                        <div
                            v-for="(o, i) in form.options"
                            :key="i"
                            class="grid grid-cols-[1fr_90px_36px] items-start gap-2"
                        >
                            <div>
                                <Input
                                    v-model="o.label"
                                    maxlength="200"
                                    :placeholder="`Answer ${i + 1}`"
                                    :aria-label="`Answer ${i + 1}`"
                                />
                                <p
                                    v-if="err(`options.${i}.label`)"
                                    class="mt-1 text-xs text-danger"
                                >
                                    {{ err(`options.${i}.label`) }}
                                </p>
                            </div>
                            <Input
                                v-model.number="o.points"
                                type="number"
                                min="0"
                                max="100"
                                :aria-label="`Points for answer ${i + 1}`"
                            />
                            <button
                                type="button"
                                class="grid size-9 place-items-center rounded text-muted-foreground hover:bg-muted hover:text-danger"
                                :disabled="form.options.length <= 2"
                                aria-label="Remove answer"
                                @click="form.options.splice(i, 1)"
                            >
                                <AppIcon name="close" :size="16" />
                            </button>
                        </div>
                    </div>
                    <Button
                        v-if="form.options.length < 10"
                        type="button"
                        size="sm"
                        variant="outline"
                        class="mt-2"
                        @click="form.options.push({ label: '', points: 0 })"
                        ><AppIcon name="add" :size="16" /> Add answer</Button
                    >
                </div>

                <div>
                    <p class="text-[13px] font-semibold">
                        Countries that ask this question
                    </p>
                    <p
                        v-if="!assessments.length"
                        class="text-[12px] text-muted-foreground"
                    >
                        Create a country assessment first.
                    </p>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <label
                            v-for="a in assessments"
                            :key="a.value"
                            class="flex items-center gap-2 text-[13px]"
                        >
                            <Checkbox
                                :model-value="
                                    form.assessment_ids.includes(a.value)
                                "
                                @update:model-value="
                                    (v) => toggleCountry(a.value, v === true)
                                "
                            />
                            {{ a.label }}
                        </label>
                    </div>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        New countries add it at the end with weight × 1; change
                        order and weight in the country’s assessment.
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing">{{
                        editing ? 'Save' : 'Add question'
                    }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="deleting !== null"
        title="Delete this question?"
        description="It will be removed from every country that uses it. Past submissions keep their copy."
        confirm-label="Delete"
        destructive
        @update:open="(v: boolean) => !v && (deleting = null)"
        @confirm="
            deleting &&
            router.delete(destroy(deleting.id).url, {
                preserveScroll: true,
                onFinish: () => (deleting = null),
            })
        "
    />
    <ConfirmDialog
        :open="deletingCat !== null"
        :title="`Delete the “${deletingCat?.name}” category?`"
        description="Its questions are kept and move to “General”."
        confirm-label="Delete"
        destructive
        @update:open="(v: boolean) => !v && (deletingCat = null)"
        @confirm="
            deletingCat &&
            router.delete(destroyCategory(deletingCat.id).url, {
                preserveScroll: true,
                onFinish: () => (deletingCat = null),
            })
        "
    />
</template>
