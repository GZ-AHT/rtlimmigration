<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import SearchInput from '@/components/panel/SearchInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import {
    availability,
    calendar,
    index,
    show,
} from '@/routes/admin/consultations';
import type { Paginated, Tone } from '@/types';

type Row = {
    id: number;
    reference: string;
    name: string;
    email: string;
    phone: string;
    subject: string;
    status: string;
    status_label: string;
    status_tone: Tone;
    starts_at: string;
    duration_minutes: number;
    meeting_url: string | null;
    counselor_name: string | null;
};

const props = defineProps<{
    bookings: Paginated<Row>;
    filters: { status: string; search: string };
    counts: Record<string, number>;
    can: { manage: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Video consultations', href: index() }] },
});

const status = ref(props.filters.status);
const search = ref(props.filters.search);

function load(): void {
    router.get(
        index().url,
        {
            status: status.value || undefined,
            search: search.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch(status, load);
watch(search, load);

const tabs = [
    { key: '', label: 'All' },
    { key: 'pending', label: 'Pending' },
    { key: 'confirmed', label: 'Confirmed' },
    { key: 'completed', label: 'Completed' },
    { key: 'cancelled', label: 'Cancelled' },
].map((t) => ({
    ...t,
    count: t.key
        ? props.counts[t.key]
        : Object.values(props.counts).reduce((a, b) => a + b, 0),
}));
</script>

<template>
    <Head title="Video consultations" />

    <PageHeader
        eyebrow="Video consultations"
        title="Bookings"
        description="Requests from the website. Approving a request emails the customer the confirmation and meeting link."
    >
        <template #actions>
            <Button as-child variant="outline">
                <Link :href="calendar()"
                    ><AppIcon name="calendar_month" :size="16" /> Calendar</Link
                >
            </Button>
            <Button v-if="can.manage" as-child variant="outline">
                <Link :href="availability()"
                    ><AppIcon name="schedule" :size="16" /> Availability</Link
                >
            </Button>
        </template>
    </PageHeader>

    <PanelTabs v-model="status" label="Booking status" :tabs="tabs" />

    <PanelCard>
        <div class="mb-4 max-w-sm">
            <SearchInput
                v-model="search"
                placeholder="Search name, email, phone or reference"
            />
        </div>

        <EmptyState
            v-if="!bookings.data.length"
            icon="video_call"
            title="No bookings found"
            description="New requests appear here when a visitor books from the website."
        />

        <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[940px] text-left text-[13px]">
                <thead class="text-[11px] text-muted-foreground uppercase">
                    <tr>
                        <th class="px-3 py-2 font-bold">Reference</th>
                        <th class="px-3 py-2 font-bold">Customer</th>
                        <th class="px-3 py-2 font-bold">Subject</th>
                        <th class="px-3 py-2 font-bold">When</th>
                        <th class="px-3 py-2 font-bold">Counselor</th>
                        <th class="px-3 py-2 font-bold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="b in bookings.data"
                        :key="b.id"
                        class="border-t hover:bg-muted/40"
                    >
                        <td class="px-3 py-3 font-semibold">
                            <Link
                                :href="show(b.id)"
                                class="text-primary hover:underline"
                                >{{ b.reference }}</Link
                            >
                        </td>
                        <td class="px-3 py-3">
                            <span class="block font-semibold">{{
                                b.name
                            }}</span>
                            <span
                                class="block text-[12px] text-muted-foreground"
                                >{{ b.email }} · {{ b.phone }}</span
                            >
                        </td>
                        <td class="px-3 py-3">{{ b.subject }}</td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            {{ formatDateTime(b.starts_at) }}
                            <span
                                class="block text-[12px] text-muted-foreground"
                                >{{ b.duration_minutes }} min</span
                            >
                        </td>
                        <td class="px-3 py-3">
                            <span v-if="b.counselor_name">{{
                                b.counselor_name
                            }}</span>
                            <span v-else class="text-muted-foreground"
                                >Not assigned</span
                            >
                        </td>
                        <td class="px-3 py-3">
                            <StatusBadge
                                :label="b.status_label"
                                :tone="b.status_tone"
                            />
                            <span
                                v-if="
                                    b.status === 'confirmed' && !b.meeting_url
                                "
                                class="mt-1 block text-[11px] text-warning"
                                >No meeting link</span
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <TablePagination :meta="bookings" />
    </PanelCard>
</template>
