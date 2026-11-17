<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    destroy,
    index,
    reorder,
    store,
    update,
} from '@/routes/admin/cms/faqs';
import {
    destroy as destroyCategory,
    store as storeCategory,
} from '@/routes/admin/cms/faqs/categories';

type Faq = {
    id: number;
    question: string;
    answer: string;
    faq_category_id: string;
    category: string | null;
    is_active: boolean;
    show_on_home: boolean;
};
type Category = { id: number; name: string; faqs_count: number };

const props = defineProps<{ faqs: Faq[]; categories: Category[] }>();
defineOptions({ layout: { breadcrumbs: [{ title: 'FAQs', href: index() }] } });

const list = ref<Faq[]>([...props.faqs]);
watch(
    () => props.faqs,
    (v) => (list.value = [...v]),
);
const filter = ref('');
const shown = computed(() =>
    filter.value
        ? list.value.filter((f) => f.faq_category_id === filter.value)
        : list.value,
);
const categoryOptions = computed(() =>
    props.categories.map((c) => ({ value: String(c.id), label: c.name })),
);

function saveOrder(next: Faq[]): void {
    list.value = filter.value
        ? [
              ...next,
              ...list.value.filter((f) => f.faq_category_id !== filter.value),
          ]
        : next;
    router.put(
        reorder().url,
        { order: list.value.map((f) => f.id) },
        {
            preserveScroll: true,
            onError: () => toast.error('Could not save the order.'),
        },
    );
}

const editing = ref<Faq | null>(null);
const open = ref(false);
const form = useForm({
    question: '',
    answer: '',
    faq_category_id: '',
    is_active: true,
    show_on_home: false,
});

function openNew(): void {
    editing.value = null;
    form.reset();
    form.faq_category_id = filter.value;
    form.clearErrors();
    open.value = true;
}

function openEdit(f: Faq): void {
    editing.value = f;
    Object.assign(form, {
        question: f.question,
        answer: f.answer,
        faq_category_id: f.faq_category_id,
        is_active: f.is_active,
        show_on_home: f.show_on_home,
    });
    form.clearErrors();
    open.value = true;
}

function save(): void {
    const payload = form.transform((d) => ({
        ...d,
        faq_category_id: d.faq_category_id || null,
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

const deleting = ref<Faq | null>(null);
const newCategory = ref('');
const removingCategory = ref<Category | null>(null);

function addCategory(): void {
    router.post(
        storeCategory().url,
        { name: newCategory.value },
        {
            preserveScroll: true,
            onSuccess: () => (newCategory.value = ''),
            onError: (e) => toast.error(Object.values(e)[0]),
        },
    );
}
</script>

<template>
    <Head title="FAQs" />

    <PageHeader
        eyebrow="Website CMS"
        title="FAQs"
        description="Questions for the FAQ page, grouped by category. Tick “Homepage” to show one in the homepage FAQ section."
    >
        <template #actions>
            <Button @click="openNew"
                ><AppIcon name="add" :size="18" /> Add question</Button
            >
        </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(260px,1fr)]">
        <PanelCard :title="`Questions (${shown.length})`">
            <template #actions>
                <SelectInput
                    v-model="filter"
                    :options="categoryOptions"
                    placeholder="All categories"
                    label="Filter by category"
                    compact
                />
            </template>
            <p
                v-if="shown.length === 0"
                class="py-6 text-center text-[13px] text-muted-foreground"
            >
                No questions here yet.
            </p>
            <SortableList :items="shown" label="FAQs" @reorder="saveOrder">
                <template #default="{ item }">
                    <div class="flex items-start gap-2 pr-2">
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[13px] font-semibold"
                                :class="
                                    item.is_active
                                        ? ''
                                        : 'text-muted-foreground line-through'
                                "
                            >
                                {{ item.question }}
                            </p>
                            <p
                                class="line-clamp-2 text-[12px] text-muted-foreground"
                            >
                                {{ item.answer }}
                            </p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <StatusBadge
                                    v-if="item.category"
                                    :label="item.category"
                                    tone="neutral"
                                />
                                <StatusBadge
                                    v-if="item.show_on_home"
                                    label="Homepage"
                                    tone="info"
                                />
                            </div>
                        </div>
                        <button
                            type="button"
                            class="tiny-action"
                            @click="openEdit(item)"
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            class="tiny-action"
                            @click="deleting = item"
                        >
                            Delete
                        </button>
                    </div>
                </template>
            </SortableList>
        </PanelCard>

        <PanelCard title="Categories" class="self-start">
            <ul class="mb-3 flex flex-col gap-1.5">
                <li
                    v-for="c in categories"
                    :key="c.id"
                    class="flex items-center justify-between text-[13px]"
                >
                    <span
                        >{{ c.name }}
                        <span class="text-muted-foreground"
                            >({{ c.faqs_count }})</span
                        ></span
                    >
                    <button
                        type="button"
                        class="tiny-action"
                        @click="removingCategory = c"
                    >
                        Delete
                    </button>
                </li>
            </ul>
            <form class="flex gap-2" @submit.prevent="addCategory">
                <Input
                    v-model="newCategory"
                    placeholder="e.g. Student visa"
                    aria-label="New category"
                    maxlength="80"
                />
                <Button type="submit" :disabled="!newCategory.trim()"
                    >Add</Button
                >
            </form>
        </PanelCard>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="rounded-[14px] sm:max-w-[560px]">
            <DialogHeader
                ><DialogTitle class="font-display text-xl font-extrabold">{{
                    editing ? 'Edit question' : 'Add question'
                }}</DialogTitle></DialogHeader
            >
            <form class="flex flex-col gap-3.5" @submit.prevent="save">
                <FormField
                    v-slot="{ id, invalid }"
                    label="Question"
                    required
                    :error="form.errors.question"
                >
                    <Input
                        :id="id"
                        v-model="form.question"
                        maxlength="300"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Answer"
                    required
                    :error="form.errors.answer"
                >
                    <TextArea
                        :id="id"
                        v-model="form.answer"
                        :rows="5"
                        :maxlength="5000"
                        :invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Category"
                    :error="form.errors.faq_category_id"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.faq_category_id"
                        :options="categoryOptions"
                        placeholder="No category"
                    />
                </FormField>
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-[13px]"
                        ><Checkbox
                            :model-value="form.is_active"
                            @update:model-value="
                                (v) => (form.is_active = v === true)
                            "
                        />
                        Shown on the website</label
                    >
                    <label class="flex items-center gap-2 text-[13px]"
                        ><Checkbox
                            :model-value="form.show_on_home"
                            @update:model-value="
                                (v) => (form.show_on_home = v === true)
                            "
                        />
                        Also on the homepage</label
                    >
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="deleting !== null"
        title="Delete this question?"
        confirm-label="Delete"
        destructive
        @update:open="(v) => !v && (deleting = null)"
        @confirm="
            deleting &&
            router.delete(destroy(deleting.id).url, {
                preserveScroll: true,
                onFinish: () => (deleting = null),
            })
        "
    />
    <ConfirmDialog
        :open="removingCategory !== null"
        :title="`Delete ${removingCategory?.name}?`"
        description="Its questions stay, without a category."
        confirm-label="Delete"
        destructive
        @update:open="(v) => !v && (removingCategory = null)"
        @confirm="
            removingCategory &&
            router.delete(destroyCategory(removingCategory.id).url, {
                preserveScroll: true,
                onFinish: () => (removingCategory = null),
            })
        "
    />
</template>
