<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { EmailTemplateItem, Option } from '@/types';

const props = defineProps<{
    senders: Option[];
    templates: EmailTemplateItem[];
    recipient: {
        type: 'student' | 'lead';
        id: number;
        name: string;
        email: string | null;
    } | null;
    prefill: {
        mode: 'reply' | 'forward';
        to_email: string | null;
        to_name: string | null;
        cc: string;
        subject: string;
        body: string;
        forward_of: number | null;
        forward_of_inbox?: number | null;
        email_account_id?: string;
        attachments: string[];
    } | null;
    placeholders: Record<string, string>;
    signature: string;
    limits: { max_files: number; max_mb: number; extensions: string[] };
    urls: { send: string; back: string };
}>();

const form = useForm({
    email_account_id:
        props.prefill?.email_account_id ?? props.senders[1]?.value ?? '',
    recipient_type: props.recipient?.type ?? '',
    recipient_id: props.recipient ? String(props.recipient.id) : '',
    to_email: props.prefill?.to_email ?? '',
    to_name: props.prefill?.to_name ?? '',
    cc: props.prefill?.cc ?? '',
    bcc: '',
    forward_of: props.prefill?.forward_of
        ? String(props.prefill.forward_of)
        : '',
    forward_of_inbox: props.prefill?.forward_of_inbox
        ? String(props.prefill.forward_of_inbox)
        : '',
    email_template_id: '',
    subject: props.prefill?.subject ?? '',
    body: props.prefill?.body ?? '',
    include_signature: true,
    files: [] as File[],
});

// Cc and Bcc stay out of the way until asked for (or already filled, e.g. a reply).
const showCopies = ref(!!props.prefill?.cc);

const templateOptions = computed<Option[]>(() =>
    props.templates.map((t) => ({
        value: String(t.id),
        label: t.category ? `${t.category} · ${t.name}` : t.name,
    })),
);

function applyTemplate(id: string): void {
    form.email_template_id = id;
    const template = props.templates.find((t) => String(t.id) === id);

    if (template) {
        form.subject = template.subject;
        form.body = template.body;
    }
}

function insertPlaceholder(key: string): void {
    const text = token(key);
    const el = document.getElementById(
        'compose-body',
    ) as HTMLTextAreaElement | null;

    if (!el) {
        form.body += text;
        return;
    }

    const start = el.selectionStart ?? form.body.length;
    const end = el.selectionEnd ?? start;
    form.body = form.body.slice(0, start) + text + form.body.slice(end);
    requestAnimationFrame(() => {
        el.focus();
        el.setSelectionRange(start + text.length, start + text.length);
    });
}

function onFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.files = [...form.files, ...Array.from(input.files ?? [])].slice(
        0,
        props.limits.max_files,
    );
    input.value = '';
}

function removeFile(index: number): void {
    form.files = form.files.filter((_, i) => i !== index);
}

const accept = computed(() =>
    props.limits.extensions.map((e) => `.${e}`).join(','),
);

const err = (key: string) =>
    (form.errors as Record<string, string | undefined>)[key];

const fileError = computed(
    () =>
        Object.entries(form.errors as Record<string, string>)
            .filter(([k]) => k === 'files' || k.startsWith('files.'))
            .map(([, v]) => v)[0],
);

function send(): void {
    form.transform((d) => ({
        ...d,
        email_account_id: d.email_account_id || null,
        recipient_type: d.recipient_type || null,
        recipient_id: d.recipient_id || null,
        to_email: d.to_email || null,
        cc: d.cc || null,
        bcc: d.bcc || null,
        forward_of: d.forward_of || null,
        forward_of_inbox: d.forward_of_inbox || null,
        email_template_id: d.email_template_id || null,
    })).post(props.urls.send, { forceFormData: true });
}

/** Shown as a literal placeholder, e.g. {{ name }}. */
const token = (key: string): string => `{{ ${key} }}`;
</script>

<template>
    <Head
        :title="
            prefill?.mode === 'forward'
                ? 'Forward email'
                : prefill?.mode === 'reply'
                  ? 'Reply'
                  : 'Compose email'
        "
    />

    <PageHeader
        eyebrow="Email"
        :title="
            prefill?.mode === 'forward'
                ? 'Forward email'
                : prefill?.mode === 'reply'
                  ? 'Reply'
                  : 'Compose email'
        "
        description="Sent from the queue and kept in the delivery log and on the recipient's record."
    >
        <template #actions>
            <Button variant="outline" as-child>
                <Link :href="urls.back">Back to my email</Link>
            </Button>
        </template>
    </PageHeader>

    <form
        class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(260px,1fr)]"
        @submit.prevent="send"
    >
        <PanelCard>
            <div class="flex flex-col gap-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="From" :error="err('email_account_id')">
                        <template #default="{ id, invalid }">
                            <SelectInput
                                :id="id"
                                v-model="form.email_account_id"
                                :options="senders"
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <FormField
                        v-if="templates.length"
                        label="Template"
                        hint="Replaces the subject and message."
                    >
                        <template #default="{ id, describedBy }">
                            <SelectInput
                                :id="id"
                                :model-value="form.email_template_id"
                                :options="templateOptions"
                                placeholder="No template"
                                :described-by="describedBy"
                                @update:model-value="applyTemplate"
                            />
                        </template>
                    </FormField>
                </div>

                <div
                    v-if="recipient"
                    class="flex items-center gap-3 rounded-lg border border-border bg-muted/40 px-3 py-2"
                >
                    <AppIcon
                        :name="
                            recipient.type === 'student' ? 'school' : 'person'
                        "
                        :size="18"
                    />
                    <div class="min-w-0 text-[13px]">
                        <span class="font-semibold text-foreground">{{
                            recipient.name
                        }}</span>
                        <span class="text-muted-foreground">
                            · {{ recipient.email ?? 'no email on file' }}
                        </span>
                    </div>
                </div>
                <p
                    v-if="recipient && err('to_email')"
                    class="text-xs text-danger"
                    role="alert"
                >
                    {{ err('to_email') }}
                </p>

                <div v-if="!recipient" class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="To"
                        required
                        :error="err('to_email')"
                        hint="A student's or lead's address links the email to their record."
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <Input
                                :id="id"
                                v-model="form.to_email"
                                type="email"
                                autocomplete="off"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>
                    <FormField label="Name (optional)" :error="err('to_name')">
                        <template #default="{ id }">
                            <Input :id="id" v-model="form.to_name" />
                        </template>
                    </FormField>
                </div>

                <div>
                    <button
                        v-if="!showCopies"
                        type="button"
                        class="tiny-action"
                        @click="showCopies = true"
                    >
                        <AppIcon name="add" :size="14" /> Add Cc / Bcc
                    </button>
                    <div v-else class="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Cc"
                            :error="err('cc')"
                            hint="Separate addresses with commas. They see each other."
                        >
                            <template #default="{ id, invalid, describedBy }">
                                <Input
                                    :id="id"
                                    v-model="form.cc"
                                    autocomplete="off"
                                    :aria-invalid="invalid"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                        <FormField
                            label="Bcc"
                            :error="err('bcc')"
                            hint="Hidden from everyone else on the email."
                        >
                            <template #default="{ id, invalid, describedBy }">
                                <Input
                                    :id="id"
                                    v-model="form.bcc"
                                    autocomplete="off"
                                    :aria-invalid="invalid"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                    </div>
                </div>

                <FormField label="Subject" required :error="err('subject')">
                    <template #default="{ id, invalid }">
                        <Input
                            :id="id"
                            v-model="form.subject"
                            maxlength="200"
                            :aria-invalid="invalid"
                        />
                    </template>
                </FormField>

                <FormField
                    id="compose-body"
                    label="Message"
                    required
                    :error="err('body')"
                    hint="Plain text. Links become clickable and placeholders are filled in for each recipient."
                >
                    <template #default="{ id, invalid, describedBy }">
                        <TextArea
                            :id="id"
                            v-model="form.body"
                            :rows="12"
                            :maxlength="20000"
                            :invalid="invalid"
                            :described-by="describedBy"
                        />
                    </template>
                </FormField>

                <label class="flex items-center gap-2 text-[13px]">
                    <Checkbox
                        :model-value="form.include_signature"
                        :disabled="!signature"
                        @update:model-value="
                            (v) => (form.include_signature = v === true)
                        "
                    />
                    {{
                        signature
                            ? 'Add my signature'
                            : 'Add my signature (set one on My email first)'
                    }}
                </label>

                <div class="flex flex-col gap-2">
                    <span class="text-[13px] font-semibold">Attachments</span>
                    <p
                        v-if="prefill?.attachments.length"
                        class="rounded-md border border-border bg-muted/40 px-3 py-1.5 text-[13px]"
                    >
                        Carried over from the original:
                        {{ prefill.attachments.join(', ') }}
                    </p>
                    <ul v-if="form.files.length" class="flex flex-col gap-1">
                        <li
                            v-for="(file, i) in form.files"
                            :key="`${file.name}-${i}`"
                            class="flex items-center justify-between gap-2 rounded-md border border-border px-3 py-1.5 text-[13px]"
                        >
                            <span class="truncate">{{ file.name }}</span>
                            <button
                                type="button"
                                class="tiny-action"
                                @click="removeFile(i)"
                            >
                                Remove
                            </button>
                        </li>
                    </ul>
                    <label
                        v-if="form.files.length < limits.max_files"
                        class="inline-flex w-fit cursor-pointer items-center gap-2 rounded-md border border-dashed border-border px-3 py-2 text-[13px] text-muted-foreground hover:bg-muted/40"
                    >
                        <AppIcon name="attach_file" :size="16" />
                        Add files
                        <input
                            type="file"
                            class="sr-only"
                            multiple
                            :accept="accept"
                            @change="onFiles"
                        />
                    </label>
                    <p class="text-[11px] text-muted-foreground">
                        Up to {{ limits.max_files }} files,
                        {{ limits.max_mb }} MB each:
                        {{ limits.extensions.join(', ').toUpperCase() }}.
                    </p>
                    <p
                        v-if="fileError"
                        class="text-xs text-danger"
                        role="alert"
                    >
                        {{ fileError }}
                    </p>
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        <AppIcon name="send" :size="18" /> Send email
                    </Button>
                </div>
            </div>
        </PanelCard>

        <PanelCard
            title="Placeholders"
            description="Click one to add it to the message."
            class="self-start"
        >
            <ul class="flex flex-col gap-1.5">
                <li v-for="(label, key) in placeholders" :key="key">
                    <button
                        type="button"
                        class="flex w-full items-baseline justify-between gap-2 rounded-md px-2 py-1 text-left text-[13px] hover:bg-muted"
                        @click="insertPlaceholder(String(key))"
                    >
                        <code class="text-primary">{{
                            token(String(key))
                        }}</code>
                        <span class="text-[11px] text-muted-foreground">{{
                            label
                        }}</span>
                    </button>
                </li>
            </ul>
        </PanelCard>
    </form>
</template>
