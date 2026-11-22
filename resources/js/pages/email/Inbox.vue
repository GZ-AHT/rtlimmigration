<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmailFolderTabs from '@/components/panel/EmailFolderTabs.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { relativeTime } from '@/lib/format';
import type { MailFolderCounts, MailboxMessageRow, Paginated } from '@/types';

type Folder = 'inbox' | 'drafts' | 'spam' | 'trash';

const props = defineProps<{
    folder: Folder;
    q: string;
    account: number | null;
    messages: Paginated<MailboxMessageRow>;
    counts: MailFolderCounts;
    accounts: {
        id: number;
        email: string;
        ready: boolean;
        synced_at: string | null;
        error: string | null;
    }[];
    urls: { inbox: string; sent: string; sync: string; compose: string };
}>();

const TITLES: Record<Folder, string> = {
    inbox: 'Inbox',
    drafts: 'Drafts',
    spam: 'Spam',
    trash: 'Trash',
};

const search = useForm({ q: props.q, account: props.account ?? '' });
const syncing = useForm({});
const ready = computed(() => props.accounts.filter((a) => a.ready));
const problems = computed(() => props.accounts.filter((a) => a.error));
const lastSync = computed(() =>
    props.accounts
        .map((a) => a.synced_at)
        .filter((d): d is string => !!d)
        .sort()
        .at(-1),
);

function runSearch(): void {
    router.get(
        props.urls.inbox,
        {
            folder: props.folder,
            q: search.q || undefined,
            account: search.account || undefined,
        },
        { preserveScroll: true, preserveState: true },
    );
}

function sync(): void {
    syncing.post(props.urls.sync, { preserveScroll: true });
}

const sender = (m: MailboxMessageRow): string =>
    m.from_name || m.from_email || 'Unknown sender';
</script>

<template>
    <Head :title="TITLES[folder]" />

    <PageHeader
        eyebrow="Email"
        :title="TITLES[folder]"
        description="Mail received in your mailboxes. Webmail is still available for anything not shown here."
    >
        <template #actions>
            <Button
                variant="outline"
                :disabled="syncing.processing || ready.length === 0"
                data-test="inbox-sync"
                @click="sync"
            >
                <AppIcon
                    name="sync"
                    :size="18"
                    :class="syncing.processing && 'animate-spin'"
                />
                Sync
            </Button>
            <Button as-child>
                <Link :href="urls.compose"
                    ><AppIcon name="edit" :size="18" /> Compose</Link
                >
            </Button>
        </template>
    </PageHeader>

    <EmailFolderTabs :active="folder" :urls="urls" :counts="counts" />

    <div
        v-for="a in problems"
        :key="a.id"
        class="mb-3 flex items-start gap-2 rounded-lg border border-danger/30 bg-danger/5 px-3 py-2 text-[13px] text-danger"
        role="alert"
    >
        <AppIcon name="error" :size="16" class="mt-0.5" />
        <span
            ><strong>{{ a.email }}:</strong> {{ a.error }} Showing what was
            received before.</span
        >
    </div>
    <p
        v-if="accounts.length > 0 && ready.length === 0"
        class="mb-3 rounded-lg border border-border bg-muted/40 px-3 py-2 text-[13px] text-muted-foreground"
    >
        Receiving is not set up for your mailbox yet. An administrator needs to
        save the mailbox password and the IMAP server under Email → Settings.
    </p>

    <PanelCard flush>
        <form
            class="flex flex-col gap-2 border-b px-4 py-3 sm:flex-row sm:items-center"
            role="search"
            @submit.prevent="runSearch"
        >
            <div class="relative flex-1">
                <AppIcon
                    name="search"
                    :size="18"
                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search.q"
                    type="search"
                    maxlength="100"
                    placeholder="Search sender, subject or text"
                    aria-label="Search email"
                    class="pl-9"
                />
            </div>
            <select
                v-if="accounts.length > 1"
                v-model="search.account"
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Mailbox"
                @change="runSearch"
            >
                <option value="">All mailboxes</option>
                <option v-for="a in accounts" :key="a.id" :value="a.id">
                    {{ a.email }}
                </option>
            </select>
            <Button type="submit" variant="secondary">Search</Button>
        </form>

        <EmptyState
            v-if="messages.data.length === 0"
            :icon="folder === 'trash' ? 'delete' : 'inbox'"
            :title="
                q
                    ? 'No email matches your search'
                    : `${TITLES[folder]} is empty`
            "
            :description="
                q
                    ? 'Try a different word, or clear the search.'
                    : folder === 'inbox' && ready.length > 0
                      ? 'New mail arrives here every few minutes. Press Sync to check now.'
                      : undefined
            "
        />

        <ul v-else class="divide-y divide-border" aria-label="Messages">
            <li v-for="m in messages.data" :key="m.id">
                <Link
                    :href="`${urls.inbox}/${m.id}`"
                    class="flex gap-3 px-4 py-3 transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                    :class="!m.is_read && 'bg-primary/[0.04]'"
                >
                    <span
                        class="mt-1.5 size-2 shrink-0 rounded-full"
                        :class="m.is_read ? 'bg-transparent' : 'bg-primary'"
                        :aria-label="m.is_read ? undefined : 'Unread'"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="flex items-baseline gap-2">
                            <span
                                class="min-w-0 flex-1 truncate text-[13.5px]"
                                :class="
                                    m.is_read
                                        ? 'text-foreground'
                                        : 'font-bold text-foreground'
                                "
                                >{{ sender(m) }}</span
                            >
                            <AppIcon
                                v-if="m.has_attachments"
                                name="attach_file"
                                :size="14"
                                class="shrink-0 text-muted-foreground"
                                aria-label="Has attachments"
                            />
                            <time
                                class="shrink-0 text-[11px] text-muted-foreground"
                                :datetime="m.received_at ?? undefined"
                                >{{ relativeTime(m.received_at) }}</time
                            >
                        </span>
                        <span
                            class="block truncate text-[13px]"
                            :class="
                                m.is_read
                                    ? 'text-foreground'
                                    : 'font-semibold text-foreground'
                            "
                            >{{ m.subject ?? '(no subject)' }}</span
                        >
                        <span
                            class="block truncate text-[12px] text-muted-foreground"
                            >{{ m.preview }}</span
                        >
                        <span
                            v-if="accounts.length > 1 && m.mailbox"
                            class="block truncate text-[11px] text-muted-foreground"
                            >{{ m.mailbox }}</span
                        >
                    </span>
                </Link>
            </li>
        </ul>
        <TablePagination v-if="messages.last_page > 1" :meta="messages" />
    </PanelCard>

    <p v-if="lastSync" class="mt-3 text-[11px] text-muted-foreground">
        Last checked {{ relativeTime(lastSync) }}. Mail is checked automatically
        every few minutes.
    </p>
</template>
