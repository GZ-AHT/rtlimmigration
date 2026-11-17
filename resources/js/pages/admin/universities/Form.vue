<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import GalleryField from '@/components/cms/GalleryField.vue';
import ImageField from '@/components/cms/ImageField.vue';
import RichTextEditor from '@/components/cms/RichTextEditor.vue';
import SeoPanel from '@/components/cms/SeoPanel.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
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
import { formatDate } from '@/lib/format';
import { destroy, index, store, update } from '@/routes/admin/universities';
import {
    destroy as destroyCourse,
    store as storeCourse,
    update as updateCourse,
} from '@/routes/admin/universities/courses';
import type { MediaItem, Option, SeoValue, TableColumn } from '@/types';

type Course = {
    id: number;
    name: string;
    level: string;
    level_label: string;
    subject: string;
    visa_kind: string;
    duration: string;
    tuition_fee: string;
    tuition_currency: string;
    tuition_period: string;
    intakes: number[];
    intake_label: string;
    application_deadline: string;
    requirements: string;
    scholarship: string;
    is_featured: boolean;
    is_active: boolean;
};

type University = {
    id: number;
    name: string;
    slug: string;
    country_id: string;
    city: string;
    address: string;
    website: string;
    type: string;
    established_year: string;
    ranking: string;
    summary: string;
    description: string;
    admission_requirements: string;
    scholarships: string;
    logo: MediaItem | null;
    banner: MediaItem | null;
    gallery: MediaItem[];
    is_featured: boolean;
    is_active: boolean;
    seo: SeoValue;
    courses: Course[];
};

const props = defineProps<{
    university: University | null;
    options: { countries: Option[]; levels: Option[]; visaKinds: Option[] };
    canManage: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Universities', href: index() }] },
});

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
const u = props.university;
const form = useForm({
    name: u?.name ?? '',
    slug: u?.slug ?? '',
    country_id: u?.country_id ?? '',
    city: u?.city ?? '',
    address: u?.address ?? '',
    website: u?.website ?? '',
    type: u?.type ?? '',
    established_year: u?.established_year ?? '',
    ranking: u?.ranking ?? '',
    summary: u?.summary ?? '',
    description: u?.description ?? '',
    admission_requirements: u?.admission_requirements ?? '',
    scholarships: u?.scholarships ?? '',
    logo: u?.logo ?? (null as MediaItem | null),
    banner: u?.banner ?? (null as MediaItem | null),
    gallery: u?.gallery ?? ([] as MediaItem[]),
    is_featured: u?.is_featured ?? false,
    is_active: u?.is_active ?? true,
    seo: u?.seo ?? { ...emptySeo },
});

const websitePlaceholder = 'https://www.university.edu';
const err = (k: string) =>
    (form.errors as Record<string, string | undefined>)[k];

function save(): void {
    const payload = form.transform((d) => ({
        ...d,
        logo: undefined,
        banner: undefined,
        logo_id: d.logo?.id ?? null,
        banner_id: d.banner?.id ?? null,
        gallery: d.gallery.map((m) => m.id),
        slug: d.slug || null,
        type: d.type || null,
        established_year: d.established_year || null,
        ranking: d.ranking || null,
        website: d.website || null,
        seo: {
            ...d.seo,
            og_image: undefined,
            og_image_id: d.seo.og_image?.id ?? null,
        },
    }));

    if (u) {
        payload.put(update(u.id).url, { preserveScroll: true });
    } else {
        payload.post(store().url);
    }
}

// --- courses
const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];
const courseOpen = ref(false);
const editingCourse = ref<Course | null>(null);
const courseForm = useForm({
    name: '',
    level: 'bachelor',
    subject: '',
    visa_kind: 'study',
    duration: '',
    tuition_fee: '',
    tuition_currency: 'USD',
    tuition_period: 'year',
    intakes: [] as number[],
    application_deadline: '',
    requirements: '',
    scholarship: '',
    is_featured: false,
    is_active: true,
});

function openCourse(c: Course | null): void {
    editingCourse.value = c;
    courseForm.reset();
    courseForm.clearErrors();

    if (c) {
        Object.assign(courseForm, {
            name: c.name,
            level: c.level,
            subject: c.subject,
            visa_kind: c.visa_kind,
            duration: c.duration,
            tuition_fee: c.tuition_fee,
            tuition_currency: c.tuition_currency || 'USD',
            tuition_period: c.tuition_period || 'year',
            intakes: [...c.intakes],
            application_deadline: c.application_deadline,
            requirements: c.requirements,
            scholarship: c.scholarship,
            is_featured: c.is_featured,
            is_active: c.is_active,
        });
    }

    courseOpen.value = true;
}

function saveCourse(): void {
    if (!u) return;
    const payload = courseForm.transform((d) => ({
        ...d,
        tuition_fee: d.tuition_fee || null,
        tuition_currency: d.tuition_currency || null,
        tuition_period: d.tuition_period || null,
        application_deadline: d.application_deadline || null,
    }));
    const options = {
        preserveScroll: true,
        onSuccess: () => (courseOpen.value = false),
    };
    if (editingCourse.value) {
        payload.put(updateCourse([u.id, editingCourse.value.id]).url, options);
    } else {
        payload.post(storeCourse(u.id).url, options);
    }
}

const removingCourse = ref<Course | null>(null);
const deleting = ref(false);

const courseColumns: TableColumn<Course>[] = [
    { key: 'name', label: 'Course' },
    { key: 'tuition', label: 'Tuition', hideBelow: 'md' },
    { key: 'intake_label', label: 'Intakes', hideBelow: 'lg' },
    { key: 'status', label: 'Status' },
];
const cerr = (k: string) =>
    (courseForm.errors as Record<string, string | undefined>)[k];
</script>

<template>
    <Head :title="university ? university.name : 'Add university'" />

    <PageHeader
        eyebrow="University management"
        :title="university ? university.name : 'Add university'"
    >
        <template #actions>
            <Button variant="outline" as-child
                ><Link :href="index()">All universities</Link></Button
            >
            <Button v-if="canManage" :disabled="form.processing" @click="save"
                >Save</Button
            >
        </template>
    </PageHeader>

    <form
        class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]"
        :inert="!canManage || undefined"
        @submit.prevent="save"
    >
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard title="Profile">
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Name"
                        required
                        :error="err('name')"
                        class="sm:col-span-2"
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            maxlength="200"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Country"
                        required
                        :error="err('country_id')"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.country_id"
                            :options="options.countries"
                            placeholder="Choose a country"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="City"
                        :error="err('city')"
                    >
                        <Input :id="id" v-model="form.city" maxlength="120" />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Address"
                        :error="err('address')"
                        class="sm:col-span-2"
                    >
                        <Input
                            :id="id"
                            v-model="form.address"
                            maxlength="255"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Website"
                        :error="err('website')"
                    >
                        <Input
                            :id="id"
                            v-model="form.website"
                            type="url"
                            :placeholder="websitePlaceholder"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Type"
                        :error="err('type')"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.type"
                            :options="[
                                { value: 'public', label: 'Public' },
                                { value: 'private', label: 'Private' },
                            ]"
                            placeholder="Not set"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Established"
                        :error="err('established_year')"
                    >
                        <Input
                            :id="id"
                            v-model="form.established_year"
                            type="number"
                            min="1000"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="World ranking"
                        :error="err('ranking')"
                    >
                        <Input
                            :id="id"
                            v-model="form.ranking"
                            type="number"
                            min="1"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Summary"
                        :error="err('summary')"
                        hint="One or two sentences for cards and search results."
                        class="sm:col-span-2"
                    >
                        <TextArea
                            :id="id"
                            v-model="form.summary"
                            :rows="2"
                            :maxlength="400"
                        />
                    </FormField>
                </div>
            </PanelCard>
            <PanelCard title="About the university">
                <RichTextEditor
                    v-model="form.description"
                    :invalid="!!err('description')"
                />
            </PanelCard>
            <PanelCard title="Admission and scholarships">
                <div class="grid gap-3.5">
                    <FormField
                        v-slot="{ id }"
                        label="General admission requirements"
                        :error="err('admission_requirements')"
                    >
                        <TextArea
                            :id="id"
                            v-model="form.admission_requirements"
                            :rows="4"
                            :maxlength="5000"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Scholarship information"
                        :error="err('scholarships')"
                    >
                        <TextArea
                            :id="id"
                            v-model="form.scholarships"
                            :rows="4"
                            :maxlength="5000"
                        />
                    </FormField>
                </div>
            </PanelCard>

            <PanelCard
                v-if="university"
                :title="`Courses (${university.courses.length})`"
                flush
            >
                <template v-if="canManage" #actions>
                    <Button type="button" size="sm" @click="openCourse(null)"
                        ><AppIcon name="add" :size="16" /> Add course</Button
                    >
                </template>
                <DataTable
                    :columns="courseColumns"
                    :rows="university.courses"
                    caption="Courses"
                    empty-title="No courses yet"
                    empty-icon="menu_book"
                >
                    <template #cell-name="{ row }">
                        <span class="block font-semibold">{{ row.name }}</span>
                        <span class="block text-[11px] text-muted-foreground"
                            >{{ row.level_label
                            }}<template v-if="row.subject">
                                · {{ row.subject }}</template
                            ><template v-if="row.duration">
                                · {{ row.duration }}</template
                            ></span
                        >
                    </template>
                    <template #cell-tuition="{ row }">
                        <span v-if="row.tuition_fee"
                            >{{ row.tuition_currency }}
                            {{ Number(row.tuition_fee).toLocaleString()
                            }}<span class="text-muted-foreground">
                                /{{ row.tuition_period }}</span
                            ></span
                        >
                        <span v-else class="text-muted-foreground">—</span>
                        <span
                            v-if="row.application_deadline"
                            class="block text-[11px] text-muted-foreground"
                            >Deadline
                            {{ formatDate(row.application_deadline) }}</span
                        >
                    </template>
                    <template #cell-status="{ row }">
                        <div class="flex flex-wrap gap-1">
                            <StatusBadge
                                :label="row.is_active ? 'Active' : 'Hidden'"
                                :tone="row.is_active ? 'success' : 'neutral'"
                            />
                            <StatusBadge
                                v-if="row.is_featured"
                                label="Featured"
                                tone="warning"
                            />
                        </div>
                    </template>
                    <template v-if="canManage" #actions="{ row }">
                        <button
                            type="button"
                            class="tiny-action"
                            @click="openCourse(row)"
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            class="tiny-action"
                            @click="removingCourse = row"
                        >
                            Delete
                        </button>
                    </template>
                </DataTable>
            </PanelCard>
            <p
                v-else
                class="rounded-lg border border-dashed border-border p-4 text-[13px] text-muted-foreground"
            >
                Save the university first, then add its courses.
            </p>

            <SeoPanel
                v-model="form.seo"
                :errors="form.errors as Record<string, string>"
                :fallback-title="form.name"
                :path="`/universities/${form.slug || '…'}`"
            />
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard title="Status">
                <div class="flex flex-col gap-3">
                    <label class="flex items-center gap-2 text-[13px]"
                        ><Checkbox
                            :model-value="form.is_active"
                            @update:model-value="
                                (v) => (form.is_active = v === true)
                            "
                        />
                        Active (shown on the website)</label
                    >
                    <label class="flex items-center gap-2 text-[13px]"
                        ><Checkbox
                            :model-value="form.is_featured"
                            @update:model-value="
                                (v) => (form.is_featured = v === true)
                            "
                        />
                        Featured</label
                    >
                    <FormField
                        v-slot="{ id }"
                        label="Address"
                        :error="err('slug')"
                        hint="Blank: made from the name."
                    >
                        <Input :id="id" v-model="form.slug" maxlength="200" />
                    </FormField>
                    <Button
                        v-if="canManage"
                        type="submit"
                        :disabled="form.processing"
                        >Save university</Button
                    >
                    <button
                        v-if="university && canManage"
                        type="button"
                        class="self-start text-[12px] text-danger hover:underline"
                        @click="deleting = true"
                    >
                        Delete university
                    </button>
                </div>
            </PanelCard>
            <PanelCard title="Images">
                <div class="flex flex-col gap-4">
                    <ImageField
                        v-model="form.logo"
                        label="Logo"
                        hint="Square or wide, transparent PNG works best."
                    />
                    <ImageField
                        v-model="form.banner"
                        label="Banner"
                        hint="Wide campus photo, 1600 px or more."
                    />
                    <GalleryField
                        v-model="form.gallery"
                        label="Gallery"
                        :max="20"
                    />
                </div>
            </PanelCard>
        </div>
    </form>

    <Dialog v-model:open="courseOpen">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto rounded-[14px] sm:max-w-[640px]"
        >
            <DialogHeader
                ><DialogTitle class="font-display text-xl font-extrabold">{{
                    editingCourse ? 'Edit course' : 'Add course'
                }}</DialogTitle></DialogHeader
            >
            <form
                class="grid gap-3.5 sm:grid-cols-2"
                @submit.prevent="saveCourse"
            >
                <FormField
                    v-slot="{ id, invalid }"
                    label="Course name"
                    required
                    :error="cerr('name')"
                    class="sm:col-span-2"
                >
                    <Input
                        :id="id"
                        v-model="courseForm.name"
                        maxlength="200"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Level"
                    required
                    :error="cerr('level')"
                >
                    <SelectInput
                        :id="id"
                        v-model="courseForm.level"
                        :options="options.levels"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Visa type"
                    :error="cerr('visa_kind')"
                >
                    <SelectInput
                        :id="id"
                        v-model="courseForm.visa_kind"
                        :options="options.visaKinds"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Subject area"
                    :error="cerr('subject')"
                >
                    <Input
                        :id="id"
                        v-model="courseForm.subject"
                        maxlength="120"
                        placeholder="e.g. Computer science"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Duration"
                    :error="cerr('duration')"
                >
                    <Input
                        :id="id"
                        v-model="courseForm.duration"
                        maxlength="60"
                        placeholder="e.g. 2 years"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Tuition fee"
                    :error="cerr('tuition_fee')"
                >
                    <div class="flex gap-2">
                        <Input
                            :id="id"
                            v-model="courseForm.tuition_fee"
                            type="number"
                            min="0"
                            step="0.01"
                            :aria-invalid="invalid"
                        />
                        <Input
                            v-model="courseForm.tuition_currency"
                            maxlength="3"
                            class="w-20 uppercase"
                            aria-label="Currency"
                        />
                    </div>
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Fee is per"
                    :error="cerr('tuition_period')"
                >
                    <SelectInput
                        :id="id"
                        v-model="courseForm.tuition_period"
                        :options="[
                            { value: 'year', label: 'Year' },
                            { value: 'semester', label: 'Semester' },
                            { value: 'total', label: 'Whole course' },
                        ]"
                    />
                </FormField>
                <fieldset class="sm:col-span-2">
                    <legend class="mb-1.5 text-[13px] font-semibold">
                        Intakes
                    </legend>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="(m, i) in months"
                            :key="m"
                            type="button"
                            class="rounded-full border px-2.5 py-1 text-[12px]"
                            :class="
                                courseForm.intakes.includes(i + 1)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border hover:bg-muted'
                            "
                            :aria-pressed="courseForm.intakes.includes(i + 1)"
                            @click="
                                courseForm.intakes =
                                    courseForm.intakes.includes(i + 1)
                                        ? courseForm.intakes.filter(
                                              (x) => x !== i + 1,
                                          )
                                        : [...courseForm.intakes, i + 1]
                            "
                        >
                            {{ m }}
                        </button>
                    </div>
                </fieldset>
                <FormField
                    v-slot="{ id }"
                    label="Application deadline"
                    :error="cerr('application_deadline')"
                >
                    <Input
                        :id="id"
                        v-model="courseForm.application_deadline"
                        type="date"
                    />
                </FormField>
                <div />
                <FormField
                    v-slot="{ id }"
                    label="Admission requirements"
                    :error="cerr('requirements')"
                    class="sm:col-span-2"
                >
                    <TextArea
                        :id="id"
                        v-model="courseForm.requirements"
                        :rows="3"
                        :maxlength="5000"
                        placeholder="e.g. IELTS 6.5 (no band below 6.0), HSC GPA 4.0"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Scholarship"
                    :error="cerr('scholarship')"
                    class="sm:col-span-2"
                >
                    <TextArea
                        :id="id"
                        v-model="courseForm.scholarship"
                        :rows="2"
                        :maxlength="5000"
                    />
                </FormField>
                <div class="flex flex-wrap gap-4 sm:col-span-2">
                    <label class="flex items-center gap-2 text-[13px]"
                        ><Checkbox
                            :model-value="courseForm.is_active"
                            @update:model-value="
                                (v) => (courseForm.is_active = v === true)
                            "
                        />
                        Active</label
                    >
                    <label class="flex items-center gap-2 text-[13px]"
                        ><Checkbox
                            :model-value="courseForm.is_featured"
                            @update:model-value="
                                (v) => (courseForm.is_featured = v === true)
                            "
                        />
                        Featured course</label
                    >
                </div>
                <DialogFooter class="sm:col-span-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="courseOpen = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="courseForm.processing"
                        >Save course</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removingCourse !== null"
        :title="`Delete ${removingCourse?.name}?`"
        confirm-label="Delete"
        destructive
        @update:open="(v) => !v && (removingCourse = null)"
        @confirm="
            university &&
            removingCourse &&
            router.delete(
                destroyCourse([university.id, removingCourse.id]).url,
                {
                    preserveScroll: true,
                    onFinish: () => (removingCourse = null),
                },
            )
        "
    />
    <ConfirmDialog
        v-if="university"
        v-model:open="deleting"
        :title="`Delete ${university.name}?`"
        description="Its courses are deleted too. This can't be undone."
        confirm-label="Delete"
        destructive
        @confirm="router.delete(destroy(university.id).url)"
    />
</template>
