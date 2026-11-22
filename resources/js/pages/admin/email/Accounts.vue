<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import TableToolbar from '@/components/panel/TableToolbar.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useFilters } from '@/composables/useFilters';
import { relativeTime } from '@/lib/format';
import {
    destroy,
    index,
    password as passwordRoute,
    provision,
    status as statusRoute,
    store,
    sync,
    test as testRoute,
    update,
    username as usernameRoute,
} from '@/routes/admin/email/accounts';
import { settings as settingsRoute } from '@/routes/admin/email';
import type { EmailAccountRow, Option, Paginated, TableColumn } from '@/types';

type ActivityItem = {
    id: number;
    summary: string;
    email: string | null;
    causer: string | null;
    created_at: string | null;
};

const props = defineProps<{
    accounts: Paginated<EmailAccountRow>;
    filters: { search: string; status: string; domain: string };
    options: { statuses: Option[]; domains: Option[]; staff: Option[] };
    connected: boolean;
    defaultQuotaMb: number | null;
    activity: ActivityItem[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Email accounts', href: index() }] },
});

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['accounts', 'filters'] },
);

const columns: TableColumn<EmailAccountRow>[] = [
    { key: 'email', label: 'Mailbox' },
    { key: 'users', label: 'Assigned to', hideBelow: 'md' },
    { key: 'usage', label: 'Storage', hideBelow: 'lg' },
    { key: 'status', label: 'Status' },
];

const onError = (e: Record<string, string>) =>
    toast.error(Object.values(e)[0] ?? 'Something went wrong.');

// --- Add mailbox -----------------------------------------------------------
const addForm = useForm({
    local_part: '',
    domain: props.options.domains[0]?.value ?? '',
    display_name: '',
    password: '',
    quota_mb: props.defaultQuotaMb ? String(props.defaultQuotaMb) : '',
    users: [] as string[],
});
const addUser = ref('');

function add(): void {
    addForm
        .transform((d) => ({
            ...d,
            password: d.password || null,
            display_name: d.display_name || null,
            quota_mb: d.quota_mb ? Number(d.quota_mb) : null,
            users: d.users.map(Number),
        }))
        .submit(store(), {
            preserveScroll: true,
            onSuccess: () =>
                addForm.reset(
                    'local_part',
                    'display_name',
                    'password',
                    'users',
                ),
        });
}

const addErr = (key: string) =>
    (addForm.errors as Record<string, string | undefined>)[key];

const staffLabel = (id: string | number) =>
    props.options.staff.find((s) => s.value === String(id))?.label ??
    `User #${id}`;

// --- Edit (display name, limit, access) -----------------------------------
const editing = ref<EmailAccountRow | null>(null);
const editForm = useForm({
    display_name: '',
    quota_mb: '',
    users: [] as { id: number; can_send: boolean }[],
});
const editUser = ref('');

function openEdit(account: EmailAccountRow): void {
    editing.value = account;
    editForm.defaults({
        display_name: account.display_name ?? '',
        quota_mb: account.quota_mb ? String(account.quota_mb) : '',
        users: account.users.map((u) => ({ id: u.id, can_send: u.can_send })),
    });
    editForm.reset();
    editForm.clearErrors();
}

function addEditUser(id: string): void {
    if (id && !editForm.users.some((u) => String(u.id) === id)) {
        editForm.users.push({ id: Number(id), can_send: true });
    }
    editUser.value = '';
}

function saveEdit(): void {
    if (!editing.value) return;

    editForm
        .transform((d) => ({
            ...d,
            display_name: d.display_name || null,
            quota_mb: d.quota_mb ? Number(d.quota_mb) : null,
        }))
        .put(update(editing.value.id).url, {
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
            onError,
        });
}

const editStaffOptions = computed(() =>
    props.options.staff.filter(
        (s) => !editForm.users.some((u) => String(u.id) === s.value),
    ),
);
const addStaffOptions = computed(() =>
    props.options.staff.filter((s) => !addForm.users.includes(s.value)),
);

// --- Password ---------------------------------------------------------------
const passwordFor = ref<EmailAccountRow | null>(null);
const passwordForm = useForm({ password: '', generate: false });
const usernameForm = useForm({ username: '' });

function openPassword(account: EmailAccountRow): void {
    passwordFor.value = account;
    passwordForm.reset();
    passwordForm.clearErrors();
    passwordForm.generate = account.on_server;
    usernameForm.reset();
    usernameForm.clearErrors();
    usernameForm.username = account.username ?? '';
}

function saveUsername(): void {
    if (!passwordFor.value) return;

    usernameForm.put(usernameRoute(passwordFor.value.id).url, {
        preserveScroll: true,
    });
}

function savePassword(): void {
    if (!passwordFor.value) return;

    passwordForm
        .transform((d) => ({
            password: d.generate ? null : d.password,
            generate: d.generate,
        }))
        .put(passwordRoute(passwordFor.value.id).url, {
            preserveScroll: true,
            onSuccess: () => (passwordFor.value = null),
            onError: (e) => e.cpanel && toast.error(e.cpanel),
        });
}

// --- Test email --------------------------------------------------------------
const testing = ref<EmailAccountRow | null>(null);
const testForm = useForm({ to: '' });

function sendTest(): void {
    if (!testing.value) return;

    testForm.post(testRoute(testing.value.id).url, {
        preserveScroll: true,
        onSuccess: () => (testing.value = null),
    });
}

// --- Row actions -------------------------------------------------------------
function toggleStatus(account: EmailAccountRow): void {
    router.patch(
        statusRoute(account.id).url,
        { status: account.status === 'active' ? 'suspended' : 'active' },
        { preserveScroll: true, onError },
    );
}

function createOnServer(account: EmailAccountRow): void {
    router.post(
        provision(account.id).url,
        {},
        { preserveScroll: true, onError },
    );
}

const syncing = ref(false);
function syncNow(): void {
    router.post(
        sync().url,
        {},
        {
            preserveScroll: true,
            onStart: () => (syncing.value = true),
            onFinish: () => (syncing.value = false),
            onError,
        },
    );
}

const deleting = ref<EmailAccountRow | null>(null);
const deleteFromServer = ref(false);
const deleteBusy = ref(false);

function openDelete(account: EmailAccountRow): void {
    deleting.value = account;
    deleteFromServer.value = false;
}

function remove(): void {
    if (!deleting.value) return;

    router.delete(destroy(deleting.value.id).url, {
        data: { from_server: deleteFromServer.value },
        preserveScroll: true,
        onStart: () => (deleteBusy.value = true),
        onFinish: () => {
            deleteBusy.value = false;
            deleting.value = null;
        },
        onError,
    });
}
</script>

<template>
    <Head title="Email accounts" />

    <PageHeader
        eyebrow="Email / Roundcube"
        title="Email accounts"
        description="Mailboxes on the cPanel mail server, who can open them in webmail, and their storage limits."
    >
        <template #actions>
            <Button
                v-if="connected"
                variant="outline"
                :disabled="syncing"
                @click="syncNow"
            >
                <AppIcon name="sync" :size="18" /> Sync from cPanel
            </Button>
        </template>
    </PageHeader>

    <div
        v-if="!connected"
        class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-warning/40 bg-warning-soft px-4 py-3 text-[13px] text-warning"
        role="status"
    >
        <AppIcon name="warning" :size="18" />
        <span class="flex-1"
            >cPanel is not connected, so new mailboxes are only recorded here.
            Connect it to create them on the server and open webmail from the
            panel.</span
        >
        <Link :href="settingsRoute()" class="font-semibold underline"
            >Email settings</Link
        >
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.9fr)_minmax(300px,1fr)]">
        <PanelCard flush>
            <TableToolbar
                v-model:search="filters.search"
                search-placeholder="Address, name or assigned user…"
            >
                <template #filters>
                    <SelectInput
                        v-model="filters.status"
                        :options="options.statuses"
                        placeholder="Any status"
                        label="Filter by status"
                        compact
                    />
                    <SelectInput
                        v-if="options.domains.length > 1"
                        v-model="filters.domain"
                        :options="options.domains"
                        placeholder="All domains"
                        label="Filter by domain"
                        compact
                    />
                </template>
                <template
                    v-if="filters.search || filters.status || filters.domain"
                    #actions
                >
                    <Button variant="ghost" size="sm" @click="reset()"
                        >Clear filters</Button
                    >
                </template>
            </TableToolbar>
            <DataTable
                :columns="columns"
                :rows="accounts.data"
                caption="Email accounts"
                empty-title="No mailboxes yet"
                empty-icon="mail"
            >
                <template #cell-email="{ row }">
                    <span class="block font-semibold text-foreground">{{
                        row.email
                    }}</span>
                    <span class="block text-[11px] text-muted-foreground">
                        {{ row.display_name ?? 'No display name' }}
                        <template v-if="!row.on_server">
                            · not on the server</template
                        >
                        <template v-else-if="!row.has_password">
                            · webmail only (no password saved)</template
                        >
                    </span>
                </template>
                <template #cell-users="{ row }">
                    <span
                        v-if="row.users.length === 0"
                        class="text-muted-foreground"
                        >Nobody</span
                    >
                    <span v-else class="text-[13px]">
                        {{ row.users.map((u) => u.name).join(', ') }}
                    </span>
                </template>
                <template #cell-usage="{ row }">
                    <span class="text-[13px]">
                        {{ row.disk_used_mb ?? '—' }} /
                        {{ row.quota_mb ? `${row.quota_mb} MB` : 'Unlimited' }}
                    </span>
                    <span
                        v-if="row.synced_at"
                        class="block text-[11px] text-muted-foreground"
                        >synced {{ relativeTime(row.synced_at) }}</span
                    >
                </template>
                <template #cell-status="{ row }">
                    <StatusBadge
                        :label="row.status_label"
                        :tone="row.status_tone"
                    />
                </template>
                <template #actions="{ row }">
                    <button
                        type="button"
                        class="tiny-action"
                        @click="openEdit(row)"
                    >
                        Edit
                    </button>
                    <button
                        v-if="connected && !row.on_server"
                        type="button"
                        class="tiny-action"
                        @click="createOnServer(row)"
                    >
                        Create on server
                    </button>
                    <button
                        type="button"
                        class="tiny-action"
                        @click="openPassword(row)"
                    >
                        Password
                    </button>
                    <button
                        v-if="row.has_password && row.status === 'active'"
                        type="button"
                        class="tiny-action"
                        @click="
                            testing = row;
                            testForm.reset();
                            testForm.clearErrors();
                        "
                    >
                        Test
                    </button>
                    <button
                        type="button"
                        class="tiny-action"
                        @click="toggleStatus(row)"
                    >
                        {{ row.status === 'active' ? 'Suspend' : 'Resume' }}
                    </button>
                    <button
                        type="button"
                        class="tiny-action"
                        @click="openDelete(row)"
                    >
                        Delete
                    </button>
                </template>
            </DataTable>
            <TablePagination v-if="accounts.last_page > 1" :meta="accounts" />
        </PanelCard>

        <div class="flex flex-col gap-4">
            <PanelCard title="Add mailbox">
                <form class="flex flex-col gap-3" @submit.prevent="add">
                    <FormField
                        label="Address"
                        required
                        :error="
                            addErr('local_part') ??
                            addErr('email') ??
                            addErr('domain')
                        "
                    >
                        <template #default="{ id, invalid }">
                            <div class="flex items-center gap-1.5">
                                <Input
                                    :id="id"
                                    v-model="addForm.local_part"
                                    placeholder="nadia"
                                    autocomplete="off"
                                    spellcheck="false"
                                    :aria-invalid="invalid"
                                />
                                <span class="text-muted-foreground">@</span>
                                <SelectInput
                                    v-if="options.domains.length"
                                    v-model="addForm.domain"
                                    :options="options.domains"
                                    label="Domain"
                                />
                                <Input
                                    v-else
                                    v-model="addForm.domain"
                                    placeholder="example.com"
                                    aria-label="Domain"
                                />
                            </div>
                        </template>
                    </FormField>
                    <FormField
                        label="Display name"
                        :error="addErr('display_name')"
                    >
                        <template #default="{ id }">
                            <Input
                                :id="id"
                                v-model="addForm.display_name"
                                placeholder="Nadia Rahman · RTL Immigration"
                            />
                        </template>
                    </FormField>
                    <FormField
                        label="Password"
                        :error="addErr('password')"
                        :hint="
                            connected
                                ? 'Leave blank to generate a strong one. Staff sign in to webmail from the panel and never need it.'
                                : 'Enter the mailbox\'s real password if you want to send from it here. It is stored encrypted.'
                        "
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <Input
                                :id="id"
                                v-model="addForm.password"
                                type="password"
                                autocomplete="new-password"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>
                    <FormField
                        label="Storage limit (MB)"
                        :error="addErr('quota_mb')"
                        hint="Blank means unlimited."
                    >
                        <template #default="{ id, describedBy }">
                            <Input
                                :id="id"
                                v-model="addForm.quota_mb"
                                type="number"
                                min="1"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>
                    <FormField label="Give access to">
                        <template #default="{ id }">
                            <SelectInput
                                :id="id"
                                v-model="addUser"
                                :options="addStaffOptions"
                                placeholder="Choose staff…"
                                @update:model-value="
                                    (v: string) => {
                                        if (v) addForm.users.push(v);
                                        addUser = '';
                                    }
                                "
                            />
                        </template>
                    </FormField>
                    <ul
                        v-if="addForm.users.length"
                        class="flex flex-wrap gap-1.5"
                    >
                        <li
                            v-for="u in addForm.users"
                            :key="u"
                            class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-[12px]"
                        >
                            {{ staffLabel(u) }}
                            <button
                                type="button"
                                :aria-label="`Remove ${staffLabel(u)}`"
                                @click="
                                    addForm.users = addForm.users.filter(
                                        (x) => x !== u,
                                    )
                                "
                            >
                                <AppIcon name="close" :size="14" />
                            </button>
                        </li>
                    </ul>
                    <p
                        v-if="addErr('cpanel')"
                        class="text-xs text-danger"
                        role="alert"
                    >
                        {{ addErr('cpanel') }}
                    </p>
                    <Button
                        type="submit"
                        :disabled="
                            addForm.processing || !addForm.local_part.trim()
                        "
                    >
                        {{ connected ? 'Create mailbox' : 'Record mailbox' }}
                    </Button>
                </form>
            </PanelCard>

            <PanelCard
                title="Email activity"
                description="Mailbox changes and webmail sign-ins."
            >
                <p
                    v-if="activity.length === 0"
                    class="text-[13px] text-muted-foreground"
                >
                    Nothing yet.
                </p>
                <ul v-else class="flex flex-col gap-2.5">
                    <li
                        v-for="item in activity"
                        :key="item.id"
                        class="text-[13px]"
                    >
                        <span class="font-semibold text-foreground">{{
                            item.summary
                        }}</span>
                        <span v-if="item.email" class="text-muted-foreground">
                            · {{ item.email }}</span
                        >
                        <span class="block text-[11px] text-muted-foreground">
                            {{ item.causer ?? 'System' }} ·
                            {{ relativeTime(item.created_at) }}
                        </span>
                    </li>
                </ul>
            </PanelCard>
        </div>
    </div>

    <!-- Edit -->
    <Dialog
        :open="editing !== null"
        @update:open="(v) => !v && (editing = null)"
    >
        <DialogContent class="rounded-[14px] sm:max-w-[520px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">{{
                    editing?.email
                }}</DialogTitle>
                <DialogDescription class="text-xs"
                    >Anyone listed can open this mailbox in webmail. "Can send"
                    also lets them send from it in the
                    system.</DialogDescription
                >
            </DialogHeader>
            <form class="flex flex-col gap-3.5" @submit.prevent="saveEdit">
                <FormField
                    v-slot="{ id }"
                    label="Display name"
                    :error="editForm.errors.display_name"
                >
                    <Input :id="id" v-model="editForm.display_name" />
                </FormField>
                <FormField
                    v-slot="{ id, describedBy }"
                    label="Storage limit (MB)"
                    :error="editForm.errors.quota_mb"
                    hint="Blank means unlimited."
                >
                    <Input
                        :id="id"
                        v-model="editForm.quota_mb"
                        type="number"
                        min="1"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <div class="flex flex-col gap-2">
                    <span class="text-[13px] font-semibold">Access</span>
                    <p
                        v-if="editForm.users.length === 0"
                        class="text-[13px] text-muted-foreground"
                    >
                        Nobody yet.
                    </p>
                    <ul v-else class="flex flex-col gap-1.5">
                        <li
                            v-for="(u, i) in editForm.users"
                            :key="u.id"
                            class="flex items-center justify-between gap-2 rounded-md border border-border px-3 py-1.5 text-[13px]"
                        >
                            <span class="truncate">{{ staffLabel(u.id) }}</span>
                            <span class="flex shrink-0 items-center gap-3">
                                <label
                                    class="flex items-center gap-1.5 text-[12px]"
                                >
                                    <Checkbox
                                        :model-value="u.can_send"
                                        @update:model-value="
                                            (v) =>
                                                (editForm.users[i].can_send =
                                                    v === true)
                                        "
                                    />
                                    Can send
                                </label>
                                <button
                                    type="button"
                                    class="tiny-action"
                                    @click="editForm.users.splice(i, 1)"
                                >
                                    Remove
                                </button>
                            </span>
                        </li>
                    </ul>
                    <SelectInput
                        v-model="editUser"
                        :options="editStaffOptions"
                        placeholder="Add staff…"
                        label="Add staff"
                        @update:model-value="addEditUser"
                    />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="editing = null"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="editForm.processing"
                        >Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Password -->
    <Dialog
        :open="passwordFor !== null"
        @update:open="(v) => !v && (passwordFor = null)"
    >
        <DialogContent class="rounded-[14px] sm:max-w-[460px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold"
                    >Mailbox login and password</DialogTitle
                >
                <DialogDescription class="text-xs">
                    {{
                        passwordFor?.on_server
                            ? 'Changes the password on the mail server. Phones or mail apps using the old one will need the new one.'
                            : "Saves the mailbox's current password so mail can be sent from it here. Nothing changes on the server."
                    }}
                </DialogDescription>
            </DialogHeader>
            <form
                class="flex items-end gap-2 border-b pb-3.5"
                @submit.prevent="saveUsername"
            >
                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    class="flex-1"
                    label="Login name"
                    :error="usernameForm.errors.username"
                    :hint="`Used to sign in for sending and receiving. Blank uses ${passwordFor?.email ?? 'the address'}.`"
                >
                    <Input
                        :id="id"
                        v-model="usernameForm.username"
                        autocomplete="off"
                        :placeholder="passwordFor?.email"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="usernameForm.processing || !usernameForm.isDirty"
                    >Save login name</Button
                >
            </form>
            <form class="flex flex-col gap-3.5" @submit.prevent="savePassword">
                <label
                    v-if="passwordFor?.on_server"
                    class="flex items-center gap-2 text-[13px]"
                >
                    <Checkbox
                        :model-value="passwordForm.generate"
                        @update:model-value="
                            (v) => (passwordForm.generate = v === true)
                        "
                    />
                    Generate a strong password
                </label>
                <FormField
                    v-if="!passwordForm.generate"
                    v-slot="{ id, invalid }"
                    label="Password"
                    :error="passwordForm.errors.password"
                    hint="At least 12 characters with upper and lower case letters and a number."
                >
                    <Input
                        :id="id"
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="new-password"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="passwordFor = null"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="passwordForm.processing"
                        >Save password</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Test -->
    <Dialog
        :open="testing !== null"
        @update:open="(v) => !v && (testing = null)"
    >
        <DialogContent class="rounded-[14px] sm:max-w-[440px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold"
                    >Send a test email</DialogTitle
                >
                <DialogDescription class="text-xs"
                    >Sends from {{ testing?.email }} through the SMTP server in
                    Email settings.</DialogDescription
                >
            </DialogHeader>
            <form class="flex flex-col gap-3.5" @submit.prevent="sendTest">
                <FormField
                    v-slot="{ id, invalid }"
                    label="Send to"
                    :error="testForm.errors.to"
                >
                    <Input
                        :id="id"
                        v-model="testForm.to"
                        type="email"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="testing = null"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        :disabled="testForm.processing || !testForm.to"
                        >Send test</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="deleting !== null"
        :title="`Delete ${deleting?.email}?`"
        description="Removes the mailbox from this system and from everyone's access."
        confirm-label="Delete"
        destructive
        :loading="deleteBusy"
        @update:open="(v) => !v && (deleting = null)"
        @confirm="remove"
    >
        <label
            v-if="deleting?.on_server && connected"
            class="flex items-start gap-2 text-[13px]"
        >
            <Checkbox
                :model-value="deleteFromServer"
                @update:model-value="(v) => (deleteFromServer = v === true)"
            />
            <span
                >Also delete it from the mail server.
                <strong>All mail in it is erased.</strong></span
            >
        </label>
    </ConfirmDialog>
</template>
