<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import type { MailboxMessageDetail } from '@/types';

const props = defineProps<{
    message: MailboxMessageDetail;
    urls: {
        inbox: string;
        sent: string;
        compose: string;
        reply: string;
        reply_all: string;
        forward: string;
    };
}>();

const confirmOpen = ref(false);
const working = ref(false);
const inTrash = props.message.folder === 'trash';
const base = `${props.urls.inbox}/${props.message.id}`;

function markUnread(): void {
    router.patch(`${base}/read`, { read: false }, { onSuccess: back });
}

function back(): void {
    router.visit(`${props.urls.inbox}?folder=${props.message.folder}`);
}

function remove(): void {
    working.value = true;
    router.delete(base, {
        onFinish: () => {
            working.value = false;
            confirmOpen.value = false;
        },
    });
}

function restore(): void {
    router.patch(`${base}/restore`, {}, { preserveScroll: true });
}

const size = (bytes: number): string =>
    bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
</script>

<template>
    <Head :title="message.subject ?? '(no subject)'" />

    <PageHeader eyebrow="Email" :title="message.subject ?? '(no subject)'">
        <template #actions>
            <Button variant="outline" as-child>
                <Link :href="`${urls.inbox}?folder=${message.folder}`"
                    ><AppIcon name="arrow_back" :size="18" /> Back</Link
                >
            </Button>
        </template>
    </PageHeader>

    <PanelCard flush>
        <div class="flex flex-wrap gap-2 border-b px-4 py-3">
            <Button size="sm" as-child>
                <Link :href="urls.reply"
                    ><AppIcon name="reply" :size="16" /> Reply</Link
                >
            </Button>
            <Button size="sm" variant="outline" as-child>
                <Link :href="urls.reply_all"
                    ><AppIcon name="reply_all" :size="16" />
                    <span class="max-sm:sr-only">Reply all</span></Link
                >
            </Button>
            <Button size="sm" variant="outline" as-child>
                <Link :href="urls.forward"
                    ><AppIcon name="forward" :size="16" />
                    <span class="max-sm:sr-only">Forward</span></Link
                >
            </Button>
            <span class="flex-1" />
            <Button
                v-if="message.folder !== 'inbox'"
                size="sm"
                variant="outline"
                @click="restore"
            >
                <AppIcon name="move_to_inbox" :size="16" />
                <span class="max-sm:sr-only">Move to Inbox</span>
            </Button>
            <Button size="sm" variant="outline" @click="markUnread">
                <AppIcon name="mark_email_unread" :size="16" />
                <span class="max-sm:sr-only">Mark unread</span>
            </Button>
            <Button
                size="sm"
                variant="outline"
                class="text-danger"
                @click="confirmOpen = true"
            >
                <AppIcon name="delete" :size="16" />
                <span class="max-sm:sr-only">{{
                    inTrash ? 'Delete for good' : 'Delete'
                }}</span>
            </Button>
        </div>

        <dl
            class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 border-b px-4 py-3 text-[13px]"
        >
            <dt class="text-muted-foreground">From</dt>
            <dd class="min-w-0 break-words">
                <strong>{{ message.from_name ?? message.from_email }}</strong>
                <span v-if="message.from_name" class="text-muted-foreground">
                    &lt;{{ message.from_email }}&gt;</span
                >
            </dd>
            <dt class="text-muted-foreground">To</dt>
            <dd class="min-w-0 break-words">
                {{ message.to.join(', ') || message.mailbox }}
            </dd>
            <template v-if="message.cc.length">
                <dt class="text-muted-foreground">Cc</dt>
                <dd class="min-w-0 break-words">{{ message.cc.join(', ') }}</dd>
            </template>
            <dt class="text-muted-foreground">Date</dt>
            <dd>{{ formatDateTime(message.received_at) }}</dd>
            <dt class="text-muted-foreground">Mailbox</dt>
            <dd class="min-w-0 break-words">{{ message.mailbox }}</dd>
        </dl>

        <div class="px-4 py-4">
            <!-- Sanitised on the server: no scripts, styles, forms or remote images. -->
            <div
                v-if="message.body_html"
                class="email-body max-w-full overflow-x-auto text-[14px] leading-relaxed break-words [&_a]:text-primary [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:pl-3 [&_img]:h-auto [&_img]:max-w-full [&_table]:max-w-full"
                v-html="message.body_html"
            />
            <pre
                v-else-if="message.body_text"
                class="font-sans text-[14px] leading-relaxed break-words whitespace-pre-wrap"
                >{{ message.body_text }}</pre>
            <p v-else class="text-sm text-muted-foreground">
                This email has no text.
            </p>
            <p
                v-if="message.body_html"
                class="mt-4 text-[11px] text-muted-foreground"
            >
                Images from other websites are not loaded, to protect your
                privacy.
            </p>
        </div>

        <div v-if="message.attachments.length" class="border-t px-4 py-3">
            <h2 class="mb-2 text-[13px] font-semibold">
                Attachments ({{ message.attachments.length }})
            </h2>
            <ul class="flex flex-wrap gap-2">
                <li v-for="a in message.attachments" :key="a.index">
                    <a
                        v-if="a.available"
                        :href="`${a.url}`"
                        download
                        class="inline-flex max-w-full items-center gap-2 rounded-lg border border-border px-3 py-2 text-[13px] hover:bg-muted"
                    >
                        <AppIcon name="attach_file" :size="16" />
                        <span class="truncate">{{ a.name }}</span>
                        <span class="text-[11px] text-muted-foreground">{{
                            size(a.size)
                        }}</span>
                    </a>
                    <span
                        v-else
                        class="inline-flex items-center gap-2 rounded-lg border border-dashed border-border px-3 py-2 text-[13px] text-muted-foreground"
                        title="Too large to keep here. Open it in webmail."
                        >{{ a.name }} (open in webmail)</span
                    >
                </li>
            </ul>
        </div>
    </PanelCard>

    <ConfirmDialog
        v-model:open="confirmOpen"
        :title="inTrash ? 'Delete this email for good?' : 'Move to Trash?'"
        :description="
            inTrash
                ? 'It is removed from the mailbox too and cannot be restored.'
                : 'It moves to Trash in your mailbox as well, and you can restore it from there.'
        "
        :confirm-label="inTrash ? 'Delete for good' : 'Move to Trash'"
        destructive
        :loading="working"
        @confirm="remove"
    />
</template>
