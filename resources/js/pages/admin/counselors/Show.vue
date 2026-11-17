<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatCard from '@/components/panel/StatCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { getInitials } from '@/composables/useInitials';
import { formatDateTime, relativeTime } from '@/lib/format';
import { edit, index } from '@/routes/admin/counselors';
import { show as showUser } from '@/routes/admin/users';
import type {
    ActivityEntry,
    EmailMessageRow,
    LeadRow,
    Stat,
    StudentRow,
    TableColumn,
    Tone,
} from '@/types';

type CounselorDetail = {
    id: number;
    user_id: number;
    name: string;
    email: string;
    phone: string | null;
    designation: string | null;
    bio: string | null;
    availability_label: string;
    availability_tone: Tone;
    max_active_leads: number | null;
    account_active: boolean;
    last_login_at: string | null;
    can: { update: boolean; view_user: boolean };
};

defineProps<{
    counselor: CounselorDetail;
    performance: (Stat & { hint?: string | null })[];
    leads: LeadRow[];
    students: StudentRow[];
    activity: ActivityEntry[];
    emails: EmailMessageRow[] | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Counselors', href: index() }] },
});

const leadColumns: TableColumn<LeadRow>[] = [
    { key: 'name', label: 'Lead' },
    { key: 'status', label: 'Status' },
    {
        key: 'next_follow_up_at',
        label: 'Next follow-up',
        hideBelow: 'md',
        format: (r) =>
            r.next_follow_up_at ? formatDateTime(r.next_follow_up_at) : '—',
    },
];
const studentColumns: TableColumn<StudentRow>[] = [
    { key: 'name', label: 'Student' },
    { key: 'student_number', label: 'Student no.', hideBelow: 'sm' },
];
</script>

<template>
    <Head :title="counselor.name" />

    <PageHeader
        eyebrow="Counselor profile"
        :title="counselor.name"
        :description="counselor.designation ?? counselor.email"
    >
        <template #actions>
            <Button v-if="counselor.can.view_user" variant="outline" as-child>
                <Link :href="showUser(counselor.user_id)"
                    ><AppIcon name="manage_accounts" :size="18" /> Account</Link
                >
            </Button>
            <Button v-if="counselor.can.update" variant="outline" as-child>
                <Link :href="edit(counselor.id)"
                    ><AppIcon name="edit" :size="18" /> Edit</Link
                >
            </Button>
        </template>
    </PageHeader>

    <div class="mb-6 grid grid-cols-2 gap-2.5 sm:gap-4 xl:grid-cols-4">
        <StatCard
            v-for="s in performance"
            :key="s.key"
            :label="s.label"
            :value="s.value"
            :icon="s.icon"
            :hint="s.hint ?? undefined"
        />
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(300px,1fr)_minmax(0,1.8fr)]">
        <div class="flex flex-col gap-4">
            <PanelCard>
                <div class="flex items-center gap-3.5">
                    <span
                        class="grid size-14 shrink-0 place-items-center rounded-full bg-accent font-display text-lg font-extrabold text-accent-foreground"
                        >{{ getInitials(counselor.name) }}</span
                    >
                    <div class="min-w-0">
                        <p class="truncate font-display text-lg font-bold">
                            {{ counselor.name }}
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <StatusBadge
                                :label="counselor.availability_label"
                                :tone="counselor.availability_tone"
                            />
                            <StatusBadge
                                v-if="!counselor.account_active"
                                label="Account inactive"
                                tone="danger"
                            />
                        </div>
                    </div>
                </div>
                <dl class="mt-5 grid gap-3 text-[13px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Email</dt>
                        <dd class="truncate font-medium">
                            {{ counselor.email }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Phone</dt>
                        <dd class="font-medium">
                            {{ counselor.phone ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Lead limit</dt>
                        <dd class="font-medium">
                            {{ counselor.max_active_leads ?? 'None' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Last sign-in</dt>
                        <dd class="font-medium">
                            {{
                                counselor.last_login_at
                                    ? relativeTime(counselor.last_login_at)
                                    : 'Never'
                            }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="counselor.bio"
                    class="mt-4 rounded-lg bg-muted p-3 text-[13px] whitespace-pre-line"
                >
                    {{ counselor.bio }}
                </p>
            </PanelCard>

            <PanelCard
                title="Activity log"
                description="Recent actions by this counselor."
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
                            {{ entry.subject ? `${entry.subject} · ` : ''
                            }}{{ entry.summary }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            {{ relativeTime(entry.created_at) }}
                        </p>
                    </li>
                </ol>
            </PanelCard>
        </div>

        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard
                flush
                title="Open leads"
                description="The 10 most recent."
            >
                <DataTable
                    class="mt-4"
                    :columns="leadColumns"
                    :rows="leads"
                    caption="Open leads"
                    empty-icon="person_search"
                    empty-title="No open leads"
                >
                    <template #cell-name="{ row }">
                        <Link
                            :href="row.url!"
                            class="font-semibold text-foreground hover:text-primary"
                            >{{ row.name }}</Link
                        >
                        <span class="block text-[11px] text-muted-foreground">{{
                            row.lead_number
                        }}</span>
                    </template>
                    <template #cell-status="{ row }"
                        ><StatusBadge
                            :label="row.status_label"
                            :tone="row.status_tone"
                    /></template>
                </DataTable>
            </PanelCard>
            <PanelCard
                flush
                title="Assigned students"
                description="The 10 most recent."
            >
                <DataTable
                    class="mt-4"
                    :columns="studentColumns"
                    :rows="students"
                    caption="Assigned students"
                    empty-icon="school"
                    empty-title="No students assigned"
                >
                    <template #cell-name="{ row }">
                        <Link
                            :href="row.url!"
                            class="font-semibold text-foreground hover:text-primary"
                            >{{ row.name }}</Link
                        >
                        <span class="block text-[11px] text-muted-foreground">{{
                            row.email
                        }}</span>
                    </template>
                </DataTable>
            </PanelCard>
            <PanelCard
                v-if="emails"
                flush
                title="Email history"
                description="Sent by or to this counselor from the system. The 15 most recent."
            >
                <EmptyState
                    v-if="!emails.length"
                    icon="mail"
                    title="No emails yet"
                />
                <ul v-else class="divide-y">
                    <li
                        v-for="email in emails"
                        :key="email.id"
                        class="flex items-center justify-between gap-3 px-4 py-2.5"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-semibold">
                                {{ email.subject }}
                            </p>
                            <p
                                class="truncate text-[11px] text-muted-foreground"
                            >
                                {{ email.from_email }} → {{ email.to_email }} ·
                                {{ relativeTime(email.created_at) }}
                            </p>
                        </div>
                        <StatusBadge
                            :label="email.status_label"
                            :tone="email.status_tone"
                        />
                    </li>
                </ul>
            </PanelCard>
        </div>
    </div>
</template>
