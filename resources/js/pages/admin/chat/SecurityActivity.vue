<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFilters } from '@/composables/useFilters';
import { formatDateTime } from '@/lib/format';
import { security } from '@/routes/admin/chat';
import { activity } from '@/routes/admin/chat/security';
import {
    destroy as unblockRoute,
    store as blockRoute,
} from '@/routes/admin/chat/security/blocks';
import {
    suspend as suspendRoute,
    unsuspend as unsuspendRoute,
} from '@/routes/admin/chat/security/guests';
import type { Option, Paginated, TableColumn, Tone } from '@/types';

/**
 * REQ §11 G/I: the chat security log, blocked IP addresses and website
 * visitors. Only for security.manage; IP addresses appear nowhere else.
 */
type EventRow = {
    id: number;
    type: string;
    label: string;
    severity: 'info' | 'warning' | 'critical';
    ip: string | null;
    who: string | null;
    guest_id: number | null;
    conversation_url: string | null;
    details: Record<string, unknown> | null;
    created_at: string | null;
};

type BlockRow = {
    id: number;
    ip: string;
    reason: string;
    automatic: boolean;
    by: string | null;
    expires_at: string | null;
    created_at: string | null;
};

type GuestRow = {
    id: number;
    name: string;
    ip: string | null;
    suspended: boolean;
    suspended_until: string | null;
    reason: string | null;
    conversation_url: string | null;
    last_seen_at: string | null;
};

const props = defineProps<{
    events: Paginated<EventRow>;
    filters: { type: string; ip: string };
    types: Option[];
    blocks: BlockRow[];
    guests: GuestRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Chat security', href: security() },
            { title: 'Security log', href: activity() },
        ],
    },
});

const { filters, reset } = useFilters(
    { ...props.filters },
    { only: ['events', 'filters'] },
);

const tone: Record<EventRow['severity'], Tone> = {
    info: 'neutral',
    warning: 'warning',
    critical: 'danger',
};

const eventColumns: TableColumn<EventRow>[] = [
    { key: 'label', label: 'Event' },
    { key: 'who', label: 'Who' },
    { key: 'ip', label: 'IP address', hideBelow: 'md' },
    {
        key: 'created_at',
        label: 'When',
        hideBelow: 'lg',
        format: (r) => formatDateTime(r.created_at),
    },
];

const blockColumns: TableColumn<BlockRow>[] = [
    { key: 'ip', label: 'IP address' },
    { key: 'reason', label: 'Reason' },
    {
        key: 'expires_at',
        label: 'Until',
        hideBelow: 'md',
        format: (r) =>
            r.expires_at ? formatDateTime(r.expires_at) : 'Until removed',
    },
];

const guestColumns: TableColumn<GuestRow>[] = [
    { key: 'name', label: 'Visitor' },
    { key: 'ip', label: 'IP address', hideBelow: 'md' },
    {
        key: 'last_seen_at',
        label: 'Last seen',
        hideBelow: 'lg',
        format: (r) => formatDateTime(r.last_seen_at),
    },
];

const blockForm = useForm({ ip: '', reason: '', hours: '' as string | number });

function block(ip?: string): void {
    if (ip) {
        blockForm.ip = ip;
    }

    blockForm
        .transform((d) => ({
            ...d,
            hours: d.hours === '' ? null : Number(d.hours),
        }))
        .post(blockRoute.url(), {
            preserveScroll: true,
            onSuccess: () => blockForm.reset(),
        });
}

function unblock(row: BlockRow): void {
    router.delete(unblockRoute.url(row.id), { preserveScroll: true });
}

const suspendReason = reactive<Record<number, string>>({});

function suspend(row: GuestRow): void {
    router.post(
        suspendRoute.url(row.id),
        {
            reason: suspendReason[row.id] || 'Suspended by an administrator',
            hours: null,
        },
        { preserveScroll: true },
    );
}

function unsuspend(row: GuestRow): void {
    router.delete(unsuspendRoute.url(row.id), { preserveScroll: true });
}

function detailText(d: Record<string, unknown> | null): string {
    if (!d) {
        return '';
    }

    return Object.entries(d)
        .map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(', ') : String(v)}`)
        .join(' · ');
}
</script>

<template>
    <Head title="Chat security log" />

    <PageHeader
        eyebrow="Chat security"
        title="Security log & blocks"
        description="What the chat safety checks noticed, blocked IP addresses and website visitors. Entries are deleted after the retention period."
    />

    <div class="mb-4 grid gap-4 lg:grid-cols-2">
        <PanelCard
            flush
            title="Blocked IP addresses"
            description="Blocked addresses cannot send portal messages or use website chat. Staff are never blocked."
        >
            <form
                class="grid gap-3 border-b px-5 pb-4 sm:grid-cols-[1fr_1.4fr_100px_auto] sm:items-end"
                @submit.prevent="block()"
            >
                <FormField label="IP address" :error="blockForm.errors.ip">
                    <template #default="{ id, invalid, describedBy }">
                        <Input
                            :id="id"
                            v-model="blockForm.ip"
                            class="font-mono text-xs"
                            placeholder="203.0.113.7"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>
                <FormField label="Reason" :error="blockForm.errors.reason">
                    <template #default="{ id, invalid, describedBy }">
                        <Input
                            :id="id"
                            v-model="blockForm.reason"
                            maxlength="255"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>
                <FormField
                    label="Hours"
                    :error="blockForm.errors.hours"
                    hint="Blank = until removed"
                >
                    <template #default="{ id, invalid, describedBy }">
                        <Input
                            :id="id"
                            v-model="blockForm.hours"
                            type="number"
                            min="1"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>
                <Button type="submit" :disabled="blockForm.processing"
                    >Block</Button
                >
            </form>
            <DataTable
                :columns="blockColumns"
                :rows="blocks"
                caption="Blocked IP addresses"
                empty-icon="shield"
                empty-title="No blocked addresses"
            >
                <template #cell-ip="{ row }">
                    <span class="font-mono text-xs">{{ row.ip }}</span>
                    <StatusBadge
                        v-if="row.automatic"
                        label="Automatic"
                        tone="warning"
                        class="ml-1"
                    />
                </template>
                <template #cell-reason="{ row }">
                    <span class="text-xs">{{ row.reason }}</span>
                    <span
                        v-if="row.by"
                        class="block text-[11px] text-muted-foreground"
                        >by {{ row.by }}</span
                    >
                </template>
                <template #actions="{ row }">
                    <button
                        type="button"
                        class="tiny-action"
                        @click="unblock(row)"
                    >
                        Unblock
                    </button>
                </template>
            </DataTable>
        </PanelCard>

        <PanelCard
            flush
            title="Website visitors"
            description="Suspended visitors, and visitors with security events in the last 7 days."
        >
            <DataTable
                :columns="guestColumns"
                :rows="guests"
                caption="Website visitors"
                empty-icon="person"
                empty-title="No visitors to review"
            >
                <template #cell-name="{ row }">
                    <Link
                        v-if="row.conversation_url"
                        :href="row.conversation_url"
                        class="font-semibold hover:text-primary"
                        >{{ row.name }}</Link
                    >
                    <span v-else class="font-semibold">{{ row.name }}</span>
                    <span
                        v-if="row.suspended"
                        class="block text-[11px] text-danger"
                        >Suspended<template v-if="row.reason"
                            >: {{ row.reason }}</template
                        ></span
                    >
                </template>
                <template #cell-ip="{ row }">
                    <span class="font-mono text-xs">{{ row.ip ?? '—' }}</span>
                </template>
                <template #actions="{ row }">
                    <button
                        v-if="row.suspended"
                        type="button"
                        class="tiny-action"
                        @click="unsuspend(row)"
                    >
                        Unsuspend
                    </button>
                    <button
                        v-else
                        type="button"
                        class="tiny-action"
                        @click="suspend(row)"
                    >
                        Suspend
                    </button>
                    <button
                        v-if="row.ip"
                        type="button"
                        class="tiny-action"
                        @click="
                            blockForm.reason = `Website visitor ${row.name}`;
                            block(row.ip);
                        "
                    >
                        Block IP
                    </button>
                </template>
            </DataTable>
        </PanelCard>
    </div>

    <PanelCard flush title="Security log">
        <div class="flex flex-wrap items-end gap-3 px-5 pb-3">
            <SelectInput
                v-model="filters.type"
                :options="types"
                placeholder="All events"
                label="Filter by event"
                compact
            />
            <Input
                v-model="filters.ip"
                class="h-8 w-44 font-mono text-xs"
                placeholder="Filter by IP"
                aria-label="Filter by IP address"
            />
            <Button
                v-if="filters.type || filters.ip"
                variant="ghost"
                size="sm"
                @click="reset()"
                >Clear filters</Button
            >
        </div>
        <DataTable
            :columns="eventColumns"
            :rows="events.data"
            caption="Security log"
            empty-icon="verified_user"
            empty-title="Nothing recorded"
        >
            <template #cell-label="{ row }">
                <StatusBadge :label="row.label" :tone="tone[row.severity]" />
                <span
                    v-if="row.details"
                    class="mt-0.5 block max-w-[420px] truncate text-[11px] text-muted-foreground"
                    :title="detailText(row.details)"
                    >{{ detailText(row.details) }}</span
                >
            </template>
            <template #cell-who="{ row }">
                <span class="text-xs">{{ row.who ?? '—' }}</span>
            </template>
            <template #cell-ip="{ row }">
                <button
                    v-if="row.ip"
                    type="button"
                    class="font-mono text-xs hover:underline"
                    :title="`Show only ${row.ip}`"
                    @click="filters.ip = row.ip"
                >
                    {{ row.ip }}
                </button>
                <span v-else>—</span>
            </template>
            <template #actions="{ row }">
                <Link
                    v-if="row.conversation_url"
                    :href="row.conversation_url"
                    class="tiny-action"
                    >Conversation</Link
                >
                <button
                    v-if="row.ip"
                    type="button"
                    class="tiny-action"
                    @click="
                        blockForm.reason = row.label;
                        block(row.ip);
                    "
                >
                    <AppIcon name="block" :size="13" /> Block IP
                </button>
            </template>
        </DataTable>
        <TablePagination :meta="events" />
    </PanelCard>
</template>
