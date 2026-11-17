<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { headers as jsonHeaders } from '@/composables/useMessageThread';
import { relativeTime } from '@/lib/format';
import { index as bulkIndex, preview, store } from '@/routes/admin/email/bulk';
import { index as logsIndex } from '@/routes/admin/email/logs';
import type {
    EmailTemplateItem,
    Option,
    Paginated,
    TableColumn,
} from '@/types';

type Campaign = {
    id: number;
    name: string;
    subject: string;
    from: string | null;
    audience: string;
    total: number;
    sent: number;
    failed: number;
    creator: string | null;
    created_at: string | null;
};

const props = defineProps<{
    campaigns: Paginated<Campaign>;
    options: {
        senders: Option[];
        templates: EmailTemplateItem[];
        counselors: Option[];
        leadStatuses: Option[];
        fileStatuses: Option[];
    };
    placeholders: Record<string, string>;
    maxRecipients: number;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Bulk email', href: bulkIndex() }] },
});

const form = useForm({
    name: '',
    email_account_id: '',
    email_template_id: '',
    subject: '',
    body: '',
    audience: { type: 'students', counselor: '', status: '' },
});

const statusOptions = computed(() =>
    form.audience.type === 'leads'
        ? props.options.leadStatuses
        : props.options.fileStatuses,
);

const templateOptions = computed<Option[]>(() =>
    props.options.templates.map((t) => ({
        value: String(t.id),
        label: t.name,
    })),
);

function applyTemplate(id: string): void {
    form.email_template_id = id;
    const t = props.options.templates.find((x) => String(x.id) === id);
    if (t) {
        form.subject = t.subject;
        form.body = t.body;
        form.name ||= t.name;
    }
}

const audiencePayload = () => ({
    type: form.audience.type,
    counselor: form.audience.counselor ? Number(form.audience.counselor) : null,
    status: form.audience.status || null,
});

// Live recipient count for the chosen audience.
const count = ref<number | null>(null);
let seq = 0;

async function refreshCount(): Promise<void> {
    const mine = ++seq;
    count.value = null;

    try {
        const res = await fetch(preview().url, {
            method: 'POST',
            headers: { ...jsonHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ audience: audiencePayload() }),
        });
        if (mine === seq && res.ok) {
            count.value = (await res.json()).count;
        }
    } catch {
        // The count is a convenience; sending still validates the audience.
    }
}

watch(
    () => form.audience.type,
    () => (form.audience.status = ''),
);
watch(() => ({ ...form.audience }), refreshCount, { immediate: true });

const bodyHint = `Placeholders: ${Object.keys(props.placeholders)
    .map((k) => `{{ ${k} }}`)
    .join(', ')}. Your signature is added.`;

const confirming = ref(false);

function send(): void {
    form.transform((d) => ({
        ...d,
        email_account_id: d.email_account_id || null,
        email_template_id: d.email_template_id || null,
        audience: audiencePayload(),
    })).submit(store(), {
        preserveScroll: true,
        onSuccess: () =>
            form.reset('name', 'subject', 'body', 'email_template_id'),
        onFinish: () => (confirming.value = false),
    });
}

const err = (key: string) =>
    (form.errors as Record<string, string | undefined>)[key];

const columns: TableColumn<Campaign>[] = [
    { key: 'name', label: 'Campaign' },
    { key: 'audience', label: 'Audience', hideBelow: 'md' },
    { key: 'progress', label: 'Sent' },
    {
        key: 'created_at',
        label: 'Created',
        hideBelow: 'sm',
        format: (r) => relativeTime(r.created_at),
    },
];
</script>

<template>
    <Head title="Bulk email" />

    <PageHeader
        eyebrow="Email / Roundcube"
        title="Bulk email"
        description="One personal email per student or lead, sent through the queue. Everyone gets their own copy; nobody sees the others."
    />

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <PanelCard title="New bulk email">
            <form
                class="flex flex-col gap-3.5"
                @submit.prevent="confirming = true"
            >
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Campaign name"
                        required
                        :error="err('name')"
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            maxlength="120"
                            placeholder="e.g. September intake reminder"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="From"
                        :error="err('email_account_id')"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.email_account_id"
                            :options="options.senders"
                            :invalid="invalid"
                        />
                    </FormField>
                </div>

                <fieldset
                    class="grid gap-3 rounded-lg border border-border p-3 sm:grid-cols-3"
                >
                    <legend class="px-1 text-[13px] font-semibold">
                        Audience
                    </legend>
                    <SelectInput
                        v-model="form.audience.type"
                        :options="[
                            { value: 'students', label: 'Students' },
                            { value: 'leads', label: 'Leads' },
                        ]"
                        label="Send to"
                    />
                    <SelectInput
                        v-model="form.audience.counselor"
                        :options="options.counselors"
                        placeholder="Any counselor"
                        label="Counselor"
                    />
                    <SelectInput
                        v-model="form.audience.status"
                        :options="statusOptions"
                        :placeholder="
                            form.audience.type === 'leads'
                                ? 'Any lead status'
                                : 'Any file status'
                        "
                        label="Status"
                    />
                    <p
                        class="text-[12px] text-muted-foreground sm:col-span-3"
                        aria-live="polite"
                    >
                        <template v-if="count === null"
                            >Counting recipients…</template
                        >
                        <template v-else-if="count > maxRecipients"
                            ><span class="text-danger"
                                >{{ count }} people: narrow this to
                                {{ maxRecipients }} or fewer.</span
                            ></template
                        >
                        <template v-else
                            >{{ count }}
                            {{ count === 1 ? 'person' : 'people' }} with an
                            email address.</template
                        >
                    </p>
                    <p
                        v-if="err('audience')"
                        class="text-xs text-danger sm:col-span-3"
                        role="alert"
                    >
                        {{ err('audience') }}
                    </p>
                </fieldset>

                <FormField
                    v-if="templateOptions.length"
                    v-slot="{ id }"
                    label="Template"
                >
                    <SelectInput
                        :id="id"
                        :model-value="form.email_template_id"
                        :options="templateOptions"
                        placeholder="No template"
                        @update:model-value="applyTemplate"
                    />
                </FormField>
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
                    v-slot="{ id, invalid, describedBy }"
                    label="Message"
                    required
                    :error="err('body')"
                    :hint="bodyHint"
                >
                    <TextArea
                        :id="id"
                        v-model="form.body"
                        :rows="10"
                        :maxlength="20000"
                        :invalid="invalid"
                        :described-by="describedBy"
                    />
                </FormField>
                <div class="flex justify-end">
                    <Button
                        type="submit"
                        :disabled="
                            form.processing ||
                            !form.name ||
                            !form.subject ||
                            !form.body ||
                            count === 0 ||
                            (count ?? 0) > maxRecipients
                        "
                        >Review and send</Button
                    >
                </div>
            </form>
        </PanelCard>

        <PanelCard title="Sent campaigns" flush class="self-start">
            <DataTable
                :columns="columns"
                :rows="campaigns.data"
                caption="Bulk email campaigns"
                empty-title="No bulk emails yet"
                empty-icon="campaign"
            >
                <template #cell-name="{ row }">
                    <Link
                        :href="
                            logsIndex({ query: { campaign: String(row.id) } })
                        "
                        class="block font-semibold text-foreground hover:underline"
                        >{{ row.name }}</Link
                    >
                    <span
                        class="block truncate text-[11px] text-muted-foreground"
                        >{{ row.subject }}</span
                    >
                </template>
                <template #cell-progress="{ row }">
                    <span class="text-[13px]"
                        >{{ row.sent }} / {{ row.total }}</span
                    >
                    <span
                        v-if="row.failed"
                        class="block text-[11px] text-danger"
                        >{{ row.failed }} failed</span
                    >
                </template>
            </DataTable>
            <TablePagination v-if="campaigns.last_page > 1" :meta="campaigns" />
        </PanelCard>
    </div>

    <ConfirmDialog
        v-model:open="confirming"
        :title="`Send to ${count ?? '…'} ${count === 1 ? 'person' : 'people'}?`"
        :description="`“${form.subject}” will be queued now. This cannot be undone.`"
        confirm-label="Send now"
        :loading="form.processing"
        @confirm="send"
    />
</template>
