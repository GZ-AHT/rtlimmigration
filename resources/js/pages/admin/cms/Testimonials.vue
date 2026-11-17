<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ImageField from '@/components/cms/ImageField.vue';
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
import { getInitials } from '@/composables/useInitials';
import {
    destroy,
    index,
    reorder,
    store,
    update,
} from '@/routes/admin/cms/testimonials';
import type { MediaItem, Option } from '@/types';

type T = {
    id: number;
    name: string;
    role: string;
    country_id: string;
    country: string | null;
    photo: MediaItem | null;
    quote: string;
    rating: number;
    is_active: boolean;
    is_featured: boolean;
};

const props = defineProps<{ testimonials: T[]; countries: Option[] }>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Testimonials', href: index() }] },
});

const list = ref<T[]>([...props.testimonials]);
watch(
    () => props.testimonials,
    (v) => (list.value = [...v]),
);

function saveOrder(next: T[]): void {
    list.value = next;
    router.put(
        reorder().url,
        { order: next.map((t) => t.id) },
        {
            preserveScroll: true,
            onError: () => toast.error('Could not save the order.'),
        },
    );
}

const editing = ref<T | null>(null);
const open = ref(false);
const form = useForm({
    name: '',
    role: '',
    country_id: '',
    photo: null as MediaItem | null,
    quote: '',
    rating: 5,
    is_active: true,
    is_featured: false,
});

function openNew(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(t: T): void {
    editing.value = t;
    Object.assign(form, {
        name: t.name,
        role: t.role,
        country_id: t.country_id,
        photo: t.photo,
        quote: t.quote,
        rating: t.rating,
        is_active: t.is_active,
        is_featured: t.is_featured,
    });
    form.clearErrors();
    open.value = true;
}

function save(): void {
    const payload = form.transform((d) => ({
        ...d,
        photo: undefined,
        photo_id: d.photo?.id ?? null,
        country_id: d.country_id || null,
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

const deleting = ref<T | null>(null);
</script>

<template>
    <Head title="Testimonials" />

    <PageHeader
        eyebrow="Website CMS"
        title="Student testimonials"
        description="Shown on the reviews page. Featured ones also appear in the homepage “Student stories” section."
    >
        <template #actions>
            <Button @click="openNew"
                ><AppIcon name="add" :size="18" /> Add testimonial</Button
            >
        </template>
    </PageHeader>

    <PanelCard>
        <p
            v-if="list.length === 0"
            class="py-6 text-center text-[13px] text-muted-foreground"
        >
            No testimonials yet.
        </p>
        <SortableList :items="list" label="Testimonials" @reorder="saveOrder">
            <template #default="{ item }">
                <div class="flex items-start gap-3 pr-2">
                    <img
                        v-if="item.photo"
                        :src="item.photo.url"
                        alt=""
                        class="size-10 shrink-0 rounded-full object-cover"
                    />
                    <span
                        v-else
                        class="grid size-10 shrink-0 place-items-center rounded-full bg-primary/10 text-[13px] font-bold text-primary"
                        >{{ getInitials(item.name) }}</span
                    >
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-[13px] font-semibold"
                            :class="
                                item.is_active
                                    ? ''
                                    : 'text-muted-foreground line-through'
                            "
                        >
                            {{ item.name }}
                            <span class="font-normal text-muted-foreground"
                                >{{ item.role
                                }}<template v-if="item.country">
                                    · {{ item.country }}</template
                                ></span
                            >
                        </p>
                        <p
                            class="line-clamp-2 text-[12px] text-muted-foreground"
                        >
                            “{{ item.quote }}”
                        </p>
                        <div class="mt-1 flex gap-1">
                            <StatusBadge
                                :label="`${item.rating}★`"
                                tone="neutral"
                            />
                            <StatusBadge
                                v-if="item.is_featured"
                                label="Featured"
                                tone="warning"
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

    <Dialog v-model:open="open">
        <DialogContent class="rounded-[14px] sm:max-w-[560px]">
            <DialogHeader
                ><DialogTitle class="font-display text-xl font-extrabold">{{
                    editing ? 'Edit testimonial' : 'Add testimonial'
                }}</DialogTitle></DialogHeader
            >
            <form class="flex flex-col gap-3.5" @submit.prevent="save">
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Name"
                        required
                        :error="form.errors.name"
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            maxlength="120"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Course or role"
                        :error="form.errors.role"
                    >
                        <Input
                            :id="id"
                            v-model="form.role"
                            maxlength="160"
                            placeholder="MSc, University of Toronto"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Destination"
                        :error="form.errors.country_id"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.country_id"
                            :options="countries"
                            placeholder="None"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Rating"
                        :error="form.errors.rating"
                    >
                        <SelectInput
                            :id="id"
                            :model-value="String(form.rating)"
                            :options="
                                [5, 4, 3, 2, 1].map((n) => ({
                                    value: String(n),
                                    label: `${n} star${n === 1 ? '' : 's'}`,
                                }))
                            "
                            @update:model-value="
                                (v) => (form.rating = Number(v))
                            "
                        />
                    </FormField>
                </div>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Quote"
                    required
                    :error="form.errors.quote"
                >
                    <TextArea
                        :id="id"
                        v-model="form.quote"
                        :rows="4"
                        :maxlength="1500"
                        :invalid="invalid"
                    />
                </FormField>
                <ImageField
                    v-model="form.photo"
                    label="Photo"
                    hint="Square, with permission from the student."
                />
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
                            :model-value="form.is_featured"
                            @update:model-value="
                                (v) => (form.is_featured = v === true)
                            "
                        />
                        Featured on the homepage</label
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
        :title="`Delete ${deleting?.name}'s testimonial?`"
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
</template>
