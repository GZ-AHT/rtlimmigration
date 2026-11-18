<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { flag } from '@/lib/flag';
import { edit, index, store } from '@/routes/admin/skill-assessments';
import { index as questionsIndex } from '@/routes/admin/skill-assessments/questions';
import { index as submissionsIndex } from '@/routes/admin/skill-assessments/submissions';
import type { Option, TableColumn } from '@/types';

/** One skill migration assessment per destination country. */
type Row = {
    id: number;
    country: string;
    iso2: string;
    country_active: boolean;
    title: string;
    slug: string;
    status: string;
    pass_percentage: number;
    questions: number;
    completed: number;
    url: string;
};

const props = defineProps<{
    assessments: Row[];
    countries: Option[];
    canManage: boolean;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Skill migration', href: index() }] },
});

const columns: TableColumn<Row>[] = [
    { key: 'country', label: 'Country' },
    { key: 'status', label: 'Status' },
    { key: 'questions', label: 'Questions', align: 'right', hideBelow: 'sm' },
    {
        key: 'pass_percentage',
        label: 'Pass mark',
        align: 'right',
        hideBelow: 'md',
        format: (r) => `${r.pass_percentage}%`,
    },
    { key: 'completed', label: 'Completed', align: 'right', hideBelow: 'md' },
];

const open = ref(false);
const form = useForm({
    country_id: '',
    slug: '',
    title: '',
    pass_percentage: 65,
});
const slugify = (s: string) =>
    s
        .toLowerCase()
        .replace(/^united kingdom$/, 'uk')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

watch(
    () => form.country_id,
    (id) => {
        const name = props.countries.find((c) => c.value === id)?.label ?? '';
        form.slug = slugify(name);
        form.title = name ? `${name} skilled migration assessment` : '';
    },
);

function create(): void {
    form.post(store().url, { onSuccess: () => (open.value = false) });
}
</script>

<template>
    <Head title="Skill migration assessments" />
    <PageHeader
        eyebrow="Skill migration"
        title="Country assessments"
        description="Each destination has its own questions, scoring, pass mark and recommendations. Only published assessments for active countries appear on the website."
    >
        <template #actions>
            <Button variant="outline" as-child
                ><Link :href="submissionsIndex()"
                    ><AppIcon name="inbox" :size="18" /> Submissions</Link
                ></Button
            >
            <Button v-if="canManage" variant="outline" as-child
                ><Link :href="questionsIndex()"
                    ><AppIcon name="quiz" :size="18" /> Question bank</Link
                ></Button
            >
            <Button v-if="canManage && countries.length" @click="open = true"
                ><AppIcon name="add" :size="18" /> New assessment</Button
            >
        </template>
    </PageHeader>

    <PanelCard flush>
        <DataTable
            :columns="columns"
            :rows="assessments"
            caption="Country assessments"
            empty-title="No assessments yet"
            empty-icon="quiz"
        >
            <template #cell-country="{ row }">
                <span class="mr-1.5" aria-hidden="true">{{
                    flag(row.iso2)
                }}</span>
                <Link
                    v-if="canManage"
                    :href="edit(row.id)"
                    class="font-semibold text-foreground hover:underline"
                    >{{ row.country }}</Link
                >
                <span v-else class="font-semibold">{{ row.country }}</span>
                <span class="block text-[11px] text-muted-foreground"
                    >/skill-migration/{{ row.slug }}</span
                >
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :label="row.status === 'published' ? 'Published' : 'Draft'"
                    :tone="row.status === 'published' ? 'success' : 'neutral'"
                />
                <span
                    v-if="!row.country_active"
                    class="ml-1 text-[11px] text-warning"
                    >Country inactive</span
                >
            </template>
            <template #actions="{ row }">
                <a
                    :href="row.url"
                    target="_blank"
                    rel="noopener"
                    class="tiny-action"
                    >{{ row.status === 'published' ? 'View' : 'Preview' }}</a
                >
                <Link v-if="canManage" :href="edit(row.id)" class="tiny-action"
                    >Edit</Link
                >
            </template>
        </DataTable>
    </PanelCard>

    <Dialog v-model:open="open">
        <DialogContent class="rounded-[14px] sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold"
                    >New country assessment</DialogTitle
                >
                <DialogDescription class="text-xs"
                    >It starts as a draft with starter recommendations. Add
                    questions, then publish.</DialogDescription
                >
            </DialogHeader>
            <form class="flex flex-col gap-3.5" @submit.prevent="create">
                <FormField
                    v-slot="{ id, invalid }"
                    label="Country"
                    required
                    :error="form.errors.country_id"
                    hint="Missing a country? Add it under Skill Migration → Countries."
                >
                    <SelectInput
                        :id="id"
                        v-model="form.country_id"
                        :options="countries"
                        placeholder="Choose a country"
                        :invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Web address"
                    required
                    :error="form.errors.slug"
                >
                    <div
                        class="flex items-center gap-1 text-[13px] text-muted-foreground"
                    >
                        <span>/skill-migration/</span
                        ><Input
                            :id="id"
                            v-model="form.slug"
                            maxlength="60"
                            :aria-invalid="invalid"
                        />
                    </div>
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Title"
                    required
                    :error="form.errors.title"
                >
                    <Input
                        :id="id"
                        v-model="form.title"
                        maxlength="160"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Pass mark (%)"
                    required
                    :error="form.errors.pass_percentage"
                >
                    <Input
                        :id="id"
                        v-model.number="form.pass_percentage"
                        type="number"
                        min="1"
                        max="100"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Create</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
