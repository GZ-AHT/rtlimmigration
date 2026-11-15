<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/composables/useInitials';
import { formatDateTime, relativeTime } from '@/lib/format';
import {
    destroy,
    edit,
    index,
    invite,
    passwordReset,
    restore,
    status as statusRoute,
    verification,
} from '@/routes/admin/users';
import type { ActivityEntry, TableColumn } from '@/types';

type UserDetail = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    role_labels: string[];
    status: 'active' | 'inactive';
    deleted: boolean;
    email_verified_at: string | null;
    two_factor_enabled: boolean;
    last_login_at: string | null;
    last_login_ip: string | null;
    created_at: string | null;
    is_self: boolean;
    can: {
        update: boolean;
        change_access: boolean;
        delete: boolean;
        restore: boolean;
    };
};

type Login = {
    id: number;
    event: 'login' | 'failed' | 'logout';
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
};

const props = defineProps<{
    user: UserDetail;
    logins: Login[];
    activity: ActivityEntry[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Users', href: index() }] },
});

const confirm = ref<null | 'deactivate' | 'delete'>(null);
const busy = ref(false);

function post(
    url: { url: string; method: string },
    data: Record<string, string> = {},
): void {
    router.visit(url.url, {
        method: url.method as 'post' | 'patch' | 'delete',
        data,
        preserveScroll: true,
        onStart: () => (busy.value = true),
        // Business-rule refusals (e.g. last Super Admin) come back as
        // validation errors; show them since this page has no form fields.
        onError: (errors) => {
            const first = Object.values(errors)[0];

            if (first) {
                toast.error(first);
            }
        },
        onFinish: () => {
            busy.value = false;
            confirm.value = null;
        },
    });
}

function setStatus(value: 'active' | 'inactive'): void {
    post(statusRoute(props.user.id), { status: value });
}

const loginColumns: TableColumn<Login>[] = [
    { key: 'event', label: 'Event' },
    {
        key: 'created_at',
        label: 'When',
        format: (r) => formatDateTime(r.created_at),
    },
    { key: 'ip_address', label: 'IP address', hideBelow: 'sm' },
    {
        key: 'user_agent',
        label: 'Device',
        hideBelow: 'lg',
        class: 'max-w-[280px] truncate',
    },
];

const loginTone = {
    login: 'success',
    failed: 'danger',
    logout: 'neutral',
} as const;
const loginLabel = {
    login: 'Signed in',
    failed: 'Failed attempt',
    logout: 'Signed out',
} as const;
</script>

<template>
    <Head :title="user.name" />

    <PageHeader
        eyebrow="User profile"
        :title="user.name"
        :description="user.email"
    >
        <template #actions>
            <Button
                v-if="user.can.restore"
                :disabled="busy"
                @click="post(restore(user.id))"
            >
                <AppIcon name="restore_from_trash" :size="18" /> Restore user
            </Button>
            <template v-if="user.can.update">
                <Button variant="outline" as-child>
                    <Link :href="edit(user.id)"
                        ><AppIcon name="edit" :size="18" /> Edit</Link
                    >
                </Button>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" aria-label="More actions">
                            <AppIcon name="more_horiz" :size="18" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <DropdownMenuItem
                            v-if="!user.last_login_at"
                            @select="post(invite(user.id))"
                        >
                            <AppIcon name="forward_to_inbox" :size="16" />
                            Resend invitation
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            @select="post(passwordReset(user.id))"
                        >
                            <AppIcon name="lock_reset" :size="16" /> Send
                            password reset link
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="!user.email_verified_at"
                            @select="post(verification(user.id))"
                        >
                            <AppIcon name="mark_email_read" :size="16" /> Resend
                            verification email
                        </DropdownMenuItem>
                        <template v-if="user.can.change_access">
                            <DropdownMenuItem
                                v-if="user.status === 'inactive'"
                                @select="setStatus('active')"
                            >
                                <AppIcon name="check_circle" :size="16" />
                                Activate
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-else
                                class="text-danger"
                                @select="confirm = 'deactivate'"
                            >
                                <AppIcon name="block" :size="16" /> Deactivate
                            </DropdownMenuItem>
                        </template>
                        <DropdownMenuItem
                            v-if="user.can.delete"
                            class="text-danger"
                            @select="confirm = 'delete'"
                        >
                            <AppIcon name="delete" :size="16" /> Delete user
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[minmax(300px,1fr)_minmax(0,1.8fr)]">
        <div class="flex flex-col gap-4">
            <PanelCard>
                <div class="flex items-center gap-3.5">
                    <span
                        class="grid size-14 shrink-0 place-items-center rounded-full bg-accent font-display text-lg font-extrabold text-accent-foreground"
                    >
                        {{ getInitials(user.name) }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-display text-lg font-bold">
                            {{ user.name }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ user.role_labels.join(', ') || 'No role' }}
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-1.5">
                    <StatusBadge
                        v-if="user.deleted"
                        label="Deleted"
                        tone="danger"
                    />
                    <StatusBadge
                        v-else
                        :label="
                            user.status === 'active' ? 'Active' : 'Inactive'
                        "
                        :tone="user.status === 'active' ? 'success' : 'neutral'"
                    />
                    <StatusBadge
                        :label="
                            user.email_verified_at
                                ? 'Email verified'
                                : 'Email not verified'
                        "
                        :tone="user.email_verified_at ? 'success' : 'warning'"
                    />
                    <StatusBadge
                        :label="user.two_factor_enabled ? '2FA on' : '2FA off'"
                        :tone="user.two_factor_enabled ? 'info' : 'neutral'"
                    />
                </div>
                <dl class="mt-5 grid gap-3 text-[13px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Email</dt>
                        <dd class="truncate font-medium">{{ user.email }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Created</dt>
                        <dd class="font-medium">
                            {{ formatDateTime(user.created_at) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Last sign-in</dt>
                        <dd class="font-medium">
                            {{
                                user.last_login_at
                                    ? relativeTime(user.last_login_at)
                                    : 'Never'
                            }}
                        </dd>
                    </div>
                    <div
                        v-if="user.last_login_ip"
                        class="flex justify-between gap-3"
                    >
                        <dt class="text-muted-foreground">Last IP</dt>
                        <dd class="font-medium">{{ user.last_login_ip }}</dd>
                    </div>
                </dl>
                <p
                    v-if="user.is_self"
                    class="mt-4 rounded-lg bg-info-soft p-3 text-xs text-info"
                >
                    This is your account.
                </p>
            </PanelCard>

            <PanelCard
                title="Activity"
                description="Changes to this account and actions they took."
            >
                <EmptyState
                    v-if="!activity.length"
                    icon="history"
                    title="No activity yet"
                />
                <ol v-else class="relative ml-1.5 border-l">
                    <li
                        v-for="entry in activity"
                        :key="entry.id"
                        class="relative pb-4 pl-5 last:pb-0"
                    >
                        <span
                            class="absolute top-1 -left-[5px] size-2.5 rounded-full border-2 border-card bg-primary"
                        />
                        <p class="text-[13px] font-medium">
                            {{ entry.summary }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            <template
                                v-if="
                                    entry.subject && entry.subject !== user.name
                                "
                                >{{ entry.subject }} ·
                            </template>
                            {{
                                entry.causer
                                    ? `by ${entry.causer}`
                                    : 'by system'
                            }}
                            · {{ relativeTime(entry.created_at) }}
                        </p>
                    </li>
                </ol>
            </PanelCard>
        </div>

        <PanelCard
            flush
            title="Login history"
            description="The latest 15 sign-ins, sign-outs and failed attempts."
        >
            <DataTable
                class="mt-4"
                :columns="loginColumns"
                :rows="logins"
                caption="Login history"
                empty-icon="login"
                empty-title="No sign-ins yet"
            >
                <template #cell-event="{ row }">
                    <StatusBadge
                        :label="loginLabel[row.event]"
                        :tone="loginTone[row.event]"
                    />
                </template>
            </DataTable>
        </PanelCard>
    </div>

    <ConfirmDialog
        :open="confirm === 'deactivate'"
        title="Deactivate this user?"
        :description="`${user.name} will be signed out immediately and won't be able to sign in until reactivated.`"
        confirm-label="Deactivate"
        destructive
        :loading="busy"
        @update:open="(v) => !v && (confirm = null)"
        @confirm="setStatus('inactive')"
    />
    <ConfirmDialog
        :open="confirm === 'delete'"
        title="Delete this user?"
        :description="`${user.name} will lose access. Their records and history are kept, and the account can be restored later.`"
        confirm-label="Delete user"
        destructive
        :loading="busy"
        @update:open="(v) => !v && (confirm = null)"
        @confirm="post(destroy(user.id))"
    />
</template>
