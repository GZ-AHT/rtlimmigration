<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
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
    view: 'sent' | 'trash';
    trashCount: number;
    urls: { compose: string; signature: string; message: string };
    canManage: boolean;
    csrfToken: string;
}>();

const signatureForm = useForm({ body: props.signature });

function saveSignature(): void {
    signatureForm.put(props.urls.signature, { preserveScroll: true });
}

// Removing an email only hides the dashboard copy; it can be restored.
const deleting = ref<EmailMessageRow | null>(null);
const deleteOpen = ref(false);
const working = ref(false);

function askDelete(row: EmailMessageRow): void {
    deleting.value = row;
    deleteOpen.value = true;
}

function confirmDelete(): void {
    if (!deleting.value) {
        return;
    }

    working.value = true;
    router.delete(`${props.urls.message}/messages/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            working.value = false;
            deleteOpen.value = false;
        },
    });
}

function restore(row: EmailMessageRow): void {
    router.patch(
        `${props.urls.message}/messages/${row.id}/restore`,
        {},
        { preserveScroll: true },
    );
}

const composeUrl = (kind: 'reply' | 'forward', row: EmailMessageRow): string =>
    `${props.urls.compose}?${kind}=${row.id}`;

const columns: TableColumn<EmailMessageRow>[] = [
    { key: 'subject', label: 'Email' },
    { key: 'status', label: 'Status', hideBelow: 'sm' },
    { key: 'open_count', label: 'Opened', hideBelow: 'md', align: 'right' },
    {
        key: 'created_at',
        label: 'Sent',
        hideBelow: 'sm',
        format: (r) => relativeTime(r.created_at),
    },
    { key: 'actions', label: 'Actions', align: 'right' },
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

            <PanelCard
                :title="view === 'trash' ? 'Deleted' : 'Sent from the system'"
                flush
            >
                <div class="flex gap-2 border-b px-5 py-2.5 text-[13px]">
                    <Link
                        :href="urls.message"
                        class="rounded-md px-2.5 py-1"
                        :class="
                            view === 'sent'
                                ? 'bg-muted font-semibold'
                                : 'text-muted-foreground hover:bg-muted'
                        "
                        preserve-scroll
                        >Sent</Link
                    >
                    <Link
                        :href="urls.message"
                        :data="{ view: 'trash' }"
                        class="rounded-md px-2.5 py-1"
                        :class="
                            view === 'trash'
                                ? 'bg-muted font-semibold'
                                : 'text-muted-foreground hover:bg-muted'
                        "
                        preserve-scroll
                        >Deleted<span
                            v-if="trashCount"
                            class="ml-1 rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-bold"
                            >{{ trashCount }}</span
                        ></Link
                    >
                </div>
                <DataTable
                    :columns="columns"
                    :rows="sent.data"
                    :caption="
                        view === 'trash'
                            ? 'Emails you removed from your sent list'
                            : 'Emails you sent from the system'
                    "
                    :empty-title="
                        view === 'trash'
                            ? 'Nothing deleted'
                            : 'Nothing sent from here yet'
                    "
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
                        <StatusBadge
                            class="mt-1 sm:hidden"
                            :label="row.status_label"
                            :tone="row.status_tone"
                        />
                        <span
                            v-if="row.cc.length || row.bcc.length"
                            class="block truncate text-[11px] text-muted-foreground"
                            ><template v-if="row.cc.length"
                                >Cc {{ row.cc.join(', ') }}</template
                            ><template v-if="row.cc.length && row.bcc.length">
                                · </template
                            ><template v-if="row.bcc.length"
                                >Bcc {{ row.bcc.length }}</template
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
                    <template #cell-actions="{ row }">
                        <div class="flex flex-nowrap justify-end gap-1.5">
                            <template v-if="view === 'sent'">
                                <Link
                                    :href="composeUrl('reply', row)"
                                    class="tiny-action"
                                    :aria-label="`Reply to ${row.subject}`"
                                    ><AppIcon name="reply" :size="14" />
                                    <span class="max-sm:sr-only"
                                        >Reply</span
                                    ></Link
                                >
                                <Link
                                    :href="composeUrl('forward', row)"
                                    class="tiny-action"
                                    :aria-label="`Forward ${row.subject}`"
                                    ><AppIcon name="forward" :size="14" />
                                    <span class="max-sm:sr-only"
                                        >Forward</span
                                    ></Link
                                >
                                <button
                                    v-if="row.status !== 'queued'"
                                    type="button"
                                    class="tiny-action"
                                    :aria-label="`Delete ${row.subject}`"
                                    @click="askDelete(row)"
                                >
                                    <AppIcon name="delete" :size="14" />
                                    <span class="max-sm:sr-only">Delete</span>
                                </button>
                            </template>
                            <button
                                v-else
                                type="button"
                                class="tiny-action"
                                :aria-label="`Restore ${row.subject}`"
                                @click="restore(row)"
                            >
                                <AppIcon name="restore_from_trash" :size="14" />
                                <span class="max-sm:sr-only">Restore</span>
                            </button>
                        </div>
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

    <ConfirmDialog
        v-model:open="deleteOpen"
        title="Delete this email?"
        description="It disappears from your Sent list only. The delivery log, the person's history and your mailbox are not touched, and you can restore it from Deleted."
        confirm-label="Delete"
        destructive
        :loading="working"
        @confirm="confirmDelete"
    />
</template>
