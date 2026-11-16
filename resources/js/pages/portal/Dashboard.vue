<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import NotificationItem from '@/components/panel/NotificationItem.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import ProgressBar from '@/components/panel/ProgressBar.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { dashboard, file as fileRoute } from '@/routes/portal';
import { index as applicationsIndex } from '@/routes/portal/applications';
import { index as paymentsIndex } from '@/routes/portal/payments';
import { edit as profileEdit } from '@/routes/portal/profile';
import type {
    AppNotification,
    ApplicationRow,
    Completeness,
    FileRow,
    MoneyTotals,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Overview', href: dashboard() }],
    },
});

defineProps<{
    student: {
        name: string;
        student_number: string;
        email: string;
        phone: string | null;
    } | null;
    completeness: Completeness | null;
    files: FileRow[];
    counselor: { name: string; email: string; phone: string | null } | null;
    money: MoneyTotals | null;
    applications: (ApplicationRow & { progress: number })[];
    activity: AppNotification[];
}>();
</script>

<template>
    <Head title="Dashboard" />

    <PageHeader
        eyebrow="Student portal"
        :title="student ? `Welcome, ${student.name}` : 'Welcome'"
        :description="
            student
                ? `Student number ${student.student_number}`
                : 'Your student record is being set up. Please contact your counselor.'
        "
    />

    <template v-if="student && money && completeness">
        <div class="mb-4 grid grid-cols-3 gap-2.5 sm:gap-4">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Total fees</p>
                <p class="mt-1 font-display text-lg font-extrabold sm:text-2xl">
                    {{ money.total }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Paid</p>
                <p
                    class="mt-1 font-display text-lg font-extrabold text-success sm:text-2xl"
                >
                    {{ money.paid }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Due</p>
                <p
                    class="mt-1 font-display text-lg font-extrabold sm:text-2xl"
                    :class="{ 'text-danger': money.due_minor > 0 }"
                >
                    {{ money.due }}
                </p>
            </div>
        </div>

        <div
            v-if="money.next"
            class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4"
        >
            <p class="flex items-center gap-2.5 text-[13px] text-warning">
                <AppIcon name="request_quote" :size="18" />
                <span>
                    <b>Next payment:</b> {{ money.next.type }} ·
                    {{ money.next.amount }}
                    <template v-if="money.next.due_date">
                        · due {{ formatDate(money.next.due_date) }}</template
                    >
                </span>
            </p>
            <Button size="sm" as-child>
                <Link :href="paymentsIndex()">Pay now</Link>
            </Button>
        </div>

        <div
            class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(300px,1fr)]"
        >
            <div class="flex min-w-0 flex-col gap-4">
                <PanelCard
                    v-if="applications.length"
                    title="Application progress"
                    description="Where each of your applications stands."
                >
                    <ul class="divide-y">
                        <li
                            v-for="a in applications"
                            :key="a.id"
                            class="py-3 first:pt-0 last:pb-0"
                        >
                            <div
                                class="mb-2 flex flex-wrap items-center justify-between gap-2"
                            >
                                <Link
                                    :href="applicationsIndex()"
                                    class="text-[13px] font-semibold hover:text-primary"
                                    >{{ a.visa_type
                                    }}<template v-if="a.country">
                                        · {{ a.country }}</template
                                    ></Link
                                >
                                <StatusBadge
                                    :label="a.status_label"
                                    :tone="a.status_tone"
                                />
                            </div>
                            <ProgressBar
                                :value="a.progress"
                                :label="a.stage ?? 'Not started'"
                            />
                        </li>
                    </ul>
                </PanelCard>
                <PanelCard
                    title="My file"
                    description="Your counselor opens a file for each application."
                >
                    <EmptyState
                        v-if="!files.length"
                        icon="folder_open"
                        title="No file opened yet"
                        description="Your counselor will open your file after your consultation."
                    />
                    <ul v-else class="divide-y">
                        <li
                            v-for="f in files"
                            :key="f.id"
                            class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <div>
                                <Link
                                    :href="fileRoute()"
                                    class="text-[13px] font-semibold hover:text-primary"
                                    >{{ f.file_number }}</Link
                                >
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        [f.service, f.country]
                                            .filter(Boolean)
                                            .join(' · ') ||
                                        'Details to be confirmed'
                                    }}
                                    · opened {{ formatDate(f.opened_at) }}
                                </p>
                            </div>
                            <StatusBadge
                                :label="f.status_label"
                                :tone="f.status_tone"
                            />
                        </li>
                    </ul>
                </PanelCard>

                <PanelCard title="Recent activity">
                    <EmptyState
                        v-if="!activity.length"
                        icon="notifications_off"
                        title="No updates yet"
                        description="Payment and document updates will appear here."
                    />
                    <div v-else class="-mx-3 flex flex-col">
                        <NotificationItem
                            v-for="n in activity"
                            :key="n.id"
                            :notification="n"
                        />
                    </div>
                </PanelCard>
            </div>

            <div class="flex flex-col gap-4">
                <PanelCard title="My profile">
                    <ProgressBar
                        :value="completeness.percent"
                        label="Complete"
                    />
                    <Button
                        v-if="completeness.percent < 100"
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        as-child
                    >
                        <Link :href="profileEdit()">Complete my profile</Link>
                    </Button>
                </PanelCard>
                <PanelCard title="My counselor">
                    <template v-if="counselor">
                        <p class="text-[13px] font-semibold">
                            {{ counselor.name }}
                        </p>
                        <p class="mt-1 text-xs">
                            <a
                                :href="`mailto:${counselor.email}`"
                                class="hover:text-primary"
                                >{{ counselor.email }}</a
                            >
                        </p>
                        <p v-if="counselor.phone" class="text-xs">
                            <a
                                :href="`tel:${counselor.phone}`"
                                class="hover:text-primary"
                                >{{ counselor.phone }}</a
                            >
                        </p>
                    </template>
                    <p v-else class="text-[13px] text-muted-foreground">
                        A counselor will be assigned to you soon.
                    </p>
                </PanelCard>
            </div>
        </div>
    </template>
</template>
