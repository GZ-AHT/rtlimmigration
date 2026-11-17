<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import ProgressBar from '@/components/panel/ProgressBar.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { relativeTime } from '@/lib/format';
import type {
    EmailAccountRow,
    EmailMessageRow,
    Paginated,
    TableColumn,
} from '@/types';

type MyAccount = EmailAccountRow & {
    can_send: boolean;
    webmail_url: string | null;
};

const props = defineProps<{
    accounts: MyAccount[];
    webmailReady: boolean;
    servers: {
        imap: { host: string; port: number };
        smtp: { host: string; port: number; encryption: string };
    } | null;
    signature: string;
    sent: Paginated<EmailMessageRow>;
    urls: { compose: string; signature: string };
    canManage: boolean;
    csrfToken: string;
}>();

const signatureForm = useForm({ body: props.signature });

function saveSignature(): void {
    signatureForm.put(props.urls.signature, { preserveScroll: true });
}

const columns: TableColumn<EmailMessageRow>[] = [
    { key: 'subject', label: 'Email' },
    { key: 'status', label: 'Status' },
    { key: 'open_count', label: 'Opened', hideBelow: 'md', align: 'right' },
    {
        key: 'created_at',
        label: 'Sent',
        hideBelow: 'sm',
        format: (r) => relativeTime(r.created_at),
    },
];
</script>

<template>
    <Head title="My email" />

    <PageHeader
        eyebrow="Email"
        title="My email"
        description="Open your mailboxes in webmail, or send from here so the email is kept on the student's or lead's record."
    >
        <template #actions>
            <Button as-child>
                <Link :href="urls.compose"
                    ><AppIcon name="edit" :size="18" /> Compose</Link
                >
            </Button>
        </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,1fr)]">
        <div class="flex flex-col gap-4">
            <PanelCard title="My mailboxes">
                <EmptyState
                    v-if="accounts.length === 0"
                    icon="mail"
                    title="No mailbox assigned to you"
                    :description="
                        canManage
                            ? 'Assign yourself one in Email accounts.'
                            : 'Ask an administrator to assign you a mailbox. You can still send from the system address.'
                    "
                />
                <ul v-else class="flex flex-col divide-y divide-border">
                    <li
                        v-for="account in accounts"
                        :key="account.id"
                        class="flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-full bg-primary/10 text-primary"
                            aria-hidden="true"
                            ><AppIcon name="mail" :size="20"
                        /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-foreground">
                                {{ account.email }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ account.display_name ?? 'No display name' }}
                                <template v-if="account.quota_mb">
                                    · {{ account.disk_used_mb ?? 0 }} of
                                    {{ account.quota_mb }} MB used
                                </template>
                                <template v-if="!account.can_send">
                                    · webmail only
                                </template>
                            </p>
                            <ProgressBar
                                v-if="account.usage_percent !== null"
                                class="mt-2 max-w-60"
                                :value="account.usage_percent"
                                :label="`${account.email} storage used`"
                            />
                        </div>
                        <StatusBadge
                            v-if="account.status !== 'active'"
                            :label="account.status_label"
                            :tone="account.status_tone"
                        />
                        <form
                            v-if="account.webmail_url && webmailReady"
                            :action="account.webmail_url"
                            method="post"
                            target="_blank"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                :value="csrfToken"
                            />
                            <Button type="submit" variant="outline" size="sm">
                                <AppIcon name="open_in_new" :size="16" /> Open
                                webmail
                            </Button>
                        </form>
                        <span v-else class="text-xs text-muted-foreground">
                            {{
                                !webmailReady
                                    ? 'Webmail is not connected yet'
                                    : 'Not on the mail server'
                            }}
                        </span>
                    </li>
                </ul>
            </PanelCard>

            <PanelCard title="Sent from the system" flush>
                <DataTable
                    :columns="columns"
                    :rows="sent.data"
                    caption="Emails you sent from the system"
                    empty-title="Nothing sent from here yet"
                    empty-icon="outgoing_mail"
                >
                    <template #cell-subject="{ row }">
                        <span
                            class="block truncate font-semibold text-foreground"
                            >{{ row.subject }}</span
                        >
                        <span class="block text-[11px] text-muted-foreground"
                            >To {{ row.to_name ?? row.to_email }}
                            <template v-if="row.recipient"
                                >· {{ row.recipient }}</template
                            ></span
                        >
                    </template>
                    <template #cell-status="{ row }">
                        <StatusBadge
                            :label="row.status_label"
                            :tone="row.status_tone"
                        />
                    </template>
                    <template #cell-open_count="{ row }">
                        {{ row.open_count > 0 ? row.open_count : '—' }}
                    </template>
                </DataTable>
                <TablePagination v-if="sent.last_page > 1" :meta="sent" />
            </PanelCard>
        </div>

        <PanelCard
            title="My signature"
            description="Added under emails you send from the system. Webmail has its own signature setting."
            class="self-start"
        >
            <form class="flex flex-col gap-3" @submit.prevent="saveSignature">
                <TextArea
                    v-model="signatureForm.body"
                    :rows="6"
                    :maxlength="2000"
                    placeholder="Best regards,&#10;Your name&#10;Counselor, RTL Immigration"
                    aria-label="Email signature"
                    :invalid="!!signatureForm.errors.body"
                />
                <p
                    v-if="signatureForm.errors.body"
                    class="text-xs text-danger"
                    role="alert"
                >
                    {{ signatureForm.errors.body }}
                </p>
                <Button
                    type="submit"
                    :disabled="
                        signatureForm.processing || !signatureForm.isDirty
                    "
                    >Save signature</Button
                >
            </form>
        </PanelCard>
    </div>
</template>
