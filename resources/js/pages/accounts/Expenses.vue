<script setup lang="ts">
import {
    Head,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
import DateRangeFilter from '@/components/panel/DateRangeFilter.vue';
import FileUpload from '@/components/panel/FileUpload.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import TextArea from '@/components/panel/TextArea.vue';
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
import { Spinner } from '@/components/ui/spinner';
import { useFilters } from '@/composables/useFilters';
import { formatDate } from '@/lib/format';
import type { Option, Paginated, TableColumn } from '@/types';

type Expense = {
    id: number;
    title: string;
    category: string;
    category_id: string;
    amount_label: string;
    amount_decimal: string;
    spent_on: string;
    payee: string | null;
    reference: string | null;
    notes: string | null;
    recorded_by: string | null;
    attachment_name: string | null;
    attachment_url: string | null;
    update_url: string;
    delete_url: string;
};

const props = defineProps<{
    expenses: Paginated<Expense>;
    filters: { search: string; category: string; from: string; to: string };
    categories: Option[];
    totals: { filtered: string; count: number; month: string; year: string };
    currency: string;
    attachment: { max_mb: number; extensions: string[] };
    urls: { store: string; categories: string };
}>();

const page = usePage();
setLayoutProps({ breadcrumbs: [{ title: 'Expenses', href: page.url }] });

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['expenses', 'filters', 'totals'] },
);

const columns: TableColumn<Expense>[] = [
    { key: 'title', label: 'Expense' },
    { key: 'category', label: 'Category', hideBelow: 'md' },
    { key: 'amount_label', label: 'Amount', align: 'right' },
    {
        key: 'spent_on',
        label: 'Date',
        hideBelow: 'sm',
        format: (r) => formatDate(r.spent_on),
    },
];

const today = new Date().toISOString().slice(0, 10);
const editing = ref<Expense | null>(null);
const formOpen = ref(false);
const form = useForm({
    expense_category_id: '',
    title: '',
    amount: '',
    spent_on: today,
    payee: '',
    reference: '',
    notes: '',
    attachment: [] as File[],
});

function open(expense: Expense | null): void {
    editing.value = expense;
    form.clearErrors();
    form.expense_category_id =
        expense?.category_id ?? props.categories[0]?.value ?? '';
    form.title = expense?.title ?? '';
    form.amount = expense?.amount_decimal ?? '';
    form.spent_on = expense?.spent_on ?? today;
    form.payee = expense?.payee ?? '';
    form.reference = expense?.reference ?? '';
    form.notes = expense?.notes ?? '';
    form.attachment = [];
    formOpen.value = true;
}

function save(): void {
    form.transform((d) => ({
        expense_category_id: d.expense_category_id,
        title: d.title,
        amount: d.amount,
        spent_on: d.spent_on,
        payee: d.payee || null,
        reference: d.reference || null,
        notes: d.notes || null,
        ...(d.attachment[0] ? { attachment: d.attachment[0] } : {}),
    })).post(editing.value?.update_url ?? props.urls.store, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (formOpen.value = false),
    });
}

const removing = ref<Expense | null>(null);
const busy = ref(false);

function remove(): void {
    if (!removing.value) {
        return;
    }

    router.delete(removing.value.delete_url, {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onFinish: () => {
            busy.value = false;
            removing.value = null;
        },
    });
}

const categoryForm = useForm({ name: '' });
const addingCategory = ref(false);

function saveCategory(): void {
    categoryForm.post(props.urls.categories, {
        preserveScroll: true,
        onSuccess: () => {
            categoryForm.reset();
            addingCategory.value = false;
        },
    });
}
</script>

<template>
    <Head title="Expenses" />

    <PageHeader
        eyebrow="Accounts"
        title="Expenses"
        description="What the office spends. These records feed the income and expense reports."
    >
        <template #actions>
            <Button variant="outline" @click="addingCategory = true"
                ><AppIcon name="category" :size="18" /> New category</Button
            >
            <Button @click="open(null)"
                ><AppIcon name="add" :size="18" /> Record expense</Button
            >
        </template>
    </PageHeader>

    <div class="mb-6 grid grid-cols-3 gap-2.5 sm:gap-4">
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">This month</p>
            <p class="mt-1 font-display text-lg font-extrabold sm:text-2xl">
                {{ totals.month }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">This year</p>
            <p class="mt-1 font-display text-lg font-extrabold sm:text-2xl">
                {{ totals.year }}
            </p>
        </div>
        <div class="rounded-xl border bg-card p-4">
            <p class="text-xs text-muted-foreground">Shown below</p>
            <p class="mt-1 font-display text-lg font-extrabold sm:text-2xl">
                {{ totals.filtered }}
            </p>
            <p class="text-[11px] text-muted-foreground">
                {{ totals.count }} record{{ totals.count === 1 ? '' : 's' }}
            </p>
        </div>
    </div>

    <PanelCard flush>
        <TableToolbar
            v-model:search="filters.search"
            search-placeholder="Title, payee or reference…"
        >
            <template #filters>
                <SelectInput
                    v-model="filters.category"
                    :options="categories"
                    placeholder="All categories"
                    label="Filter by category"
                    compact
                />
                <DateRangeFilter
                    v-model:from="filters.from"
                    v-model:to="filters.to"
                />
            </template>
            <template
                v-if="
                    filters.search ||
                    filters.category ||
                    filters.from ||
                    filters.to
                "
                #actions
            >
                <Button variant="ghost" size="sm" @click="reset"
                    >Clear filters</Button
                >
            </template>
        </TableToolbar>
        <DataTable
            :columns="columns"
            :rows="expenses.data"
            caption="Expenses"
            empty-icon="shopping_cart"
            empty-title="No expenses recorded"
            empty-description="Record rent, salaries, embassy fees and other costs to see profit in the financial reports."
        >
            <template #cell-title="{ row }">
                <span class="block font-semibold text-foreground">{{
                    row.title
                }}</span>
                <span class="block text-[11px] text-muted-foreground">
                    {{
                        [
                            row.payee,
                            row.reference ? `ref ${row.reference}` : null,
                            row.recorded_by ? `by ${row.recorded_by}` : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')
                    }}
                </span>
            </template>
            <template #cell-amount_label="{ row }"
                ><b class="text-foreground">{{ row.amount_label }}</b></template
            >
            <template #actions="{ row }">
                <a
                    v-if="row.attachment_url"
                    :href="row.attachment_url"
                    target="_blank"
                    rel="noopener"
                    class="tiny-action"
                    ><AppIcon name="attach_file" :size="14" /> Bill</a
                >
                <button type="button" class="tiny-action" @click="open(row)">
                    Edit
                </button>
                <button
                    type="button"
                    class="tiny-action"
                    @click="removing = row"
                >
                    Remove
                </button>
            </template>
        </DataTable>
        <TablePagination :meta="expenses" />
    </PanelCard>

    <Dialog v-model:open="formOpen">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto rounded-[14px] sm:max-w-[540px]"
        >
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">
                    {{ editing ? 'Edit expense' : 'Record an expense' }}
                </DialogTitle>
                <DialogDescription class="text-xs">
                    Amounts are in {{ currency }}.
                </DialogDescription>
            </DialogHeader>
            <form
                class="flex flex-col gap-3.5"
                novalidate
                @submit.prevent="save"
            >
                <FormField
                    v-slot="{ id, invalid }"
                    label="What was it for?"
                    required
                    :error="form.errors.title"
                >
                    <Input
                        :id="id"
                        v-model="form.title"
                        placeholder="e.g. Office rent, November"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Category"
                        required
                        :error="form.errors.expense_category_id"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.expense_category_id"
                            :options="categories"
                            :invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        :label="`Amount (${currency})`"
                        required
                        :error="form.errors.amount"
                    >
                        <Input
                            :id="id"
                            v-model="form.amount"
                            inputmode="decimal"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Date spent"
                        required
                        :error="form.errors.spent_on"
                    >
                        <Input
                            :id="id"
                            v-model="form.spent_on"
                            type="date"
                            :max="today"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Paid to"
                        :error="form.errors.payee"
                    >
                        <Input
                            :id="id"
                            v-model="form.payee"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                </div>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Voucher / reference"
                    :error="form.errors.reference"
                >
                    <Input
                        :id="id"
                        v-model="form.reference"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FileUpload
                    v-model="form.attachment"
                    :label="
                        editing?.attachment_name
                            ? `Replace bill (${editing.attachment_name})`
                            : 'Bill or voucher (optional)'
                    "
                    :accept="
                        attachment.extensions.map((e) => `.${e}`).join(',')
                    "
                    :max-size-mb="attachment.max_mb"
                    :error="form.errors.attachment"
                    :hint="`${attachment.extensions.join(', ').toUpperCase()} · up to ${attachment.max_mb} MB`"
                />
                <FormField
                    v-slot="{ id, invalid }"
                    label="Note"
                    :error="form.errors.notes"
                >
                    <TextArea
                        :id="id"
                        v-model="form.notes"
                        :rows="2"
                        :invalid="invalid"
                    />
                </FormField>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="formOpen = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        ><Spinner v-if="form.processing" />
                        {{
                            editing ? 'Save changes' : 'Record expense'
                        }}</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="addingCategory">
        <DialogContent class="rounded-[14px] sm:max-w-[420px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">
                    New expense category
                </DialogTitle>
            </DialogHeader>
            <form
                class="flex flex-col gap-3.5"
                novalidate
                @submit.prevent="saveCategory"
            >
                <FormField
                    v-slot="{ id, invalid }"
                    label="Name"
                    required
                    :error="categoryForm.errors.name"
                >
                    <Input
                        :id="id"
                        v-model="categoryForm.name"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="addingCategory = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="categoryForm.processing"
                        ><Spinner v-if="categoryForm.processing" /> Add
                        category</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removing !== null"
        title="Remove this expense?"
        :description="`${removing?.title} (${removing?.amount_label}) will no longer count in reports. The record is kept for audit.`"
        confirm-label="Remove"
        destructive
        :loading="busy"
        @update:open="(v) => !v && (removing = null)"
        @confirm="remove"
    />
</template>
