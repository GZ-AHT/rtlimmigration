<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import SearchInput from '@/components/panel/SearchInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import TablePagination from '@/components/panel/TablePagination.vue';
import { audit } from '@/routes/admin/system';
import type { Paginated } from '@/types';

type Row = Record<string, string | number | null>;

const props = defineProps<{
    tab: 'activity' | 'logins';
    rows: Paginated<Row>;
    filters: { search: string; log: string; event: string };
    logNames: string[];
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Audit logs', href: audit() }] },
});

const search = ref(props.filters.search);
watch(search, (v) => go({ search: v || undefined }));

function go(extra: Record<string, string | undefined>): void {
    router.get(
        audit().url,
        { tab: props.tab, search: props.filters.search || undefined, ...extra },
        { preserveState: true, replace: true },
    );
}

const eventTone = (e: string | number | null) =>
    e === 'failed' ? 'danger' : e === 'login' ? 'success' : 'neutral';
</script>

<template>
    <Head title="Audit logs" />

    <PageHeader
        eyebrow="Security"
        title="Audit logs"
        description="What staff changed, and who signed in. IP addresses are shown here only."
    />

    <PanelTabs
        :model-value="tab"
        label="Log type"
        :tabs="[
            { key: 'activity', label: 'Admin activity' },
            { key: 'logins', label: 'Sign-ins' },
        ]"
        @update:model-value="(t) => router.get(audit().url, { tab: t })"
    />

    <PanelCard :title="`${rows.total} entries`">
        <template #actions>
            <SearchInput
                v-model="search"
                :placeholder="
                    tab === 'logins' ? 'Email or IP…' : 'Search actions…'
                "
                label="Search logs"
            />
            <select
                v-if="tab === 'activity'"
                :value="filters.log"
                class="h-9 rounded-lg border bg-card px-3 text-[13px]"
                aria-label="Log type"
                @change="
                    (e) =>
                        go({
                            log:
                                (e.target as HTMLSelectElement).value ||
                                undefined,
                        })
                "
            >
                <option value="">All areas</option>
                <option v-for="n in logNames" :key="n" :value="n">
                    {{ n }}
                </option>
            </select>
            <select
                v-else
                :value="filters.event"
                class="h-9 rounded-lg border bg-card px-3 text-[13px]"
                aria-label="Event"
                @change="
                    (e) =>
                        go({
                            event:
                                (e.target as HTMLSelectElement).value ||
                                undefined,
                        })
                "
            >
                <option value="">All events</option>
                <option value="login">Sign-ins</option>
                <option value="failed">Failed</option>
                <option value="logout">Sign-outs</option>
            </select>
        </template>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                <thead class="text-[11px] text-muted-foreground uppercase">
                    <tr v-if="tab === 'activity'">
                        <th class="py-2 pr-3 font-semibold">When</th>
                        <th class="py-2 pr-3 font-semibold">Who</th>
                        <th class="py-2 pr-3 font-semibold">Area</th>
                        <th class="py-2 font-semibold">Action</th>
                    </tr>
                    <tr v-else>
                        <th class="py-2 pr-3 font-semibold">When</th>
                        <th class="py-2 pr-3 font-semibold">Who</th>
                        <th class="py-2 pr-3 font-semibold">Event</th>
                        <th class="py-2 pr-3 font-semibold">IP address</th>
                        <th class="py-2 font-semibold">Browser</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="r in rows.data"
                        :key="Number(r.id)"
                        class="border-t align-top"
                    >
                        <td
                            class="py-2 pr-3 whitespace-nowrap text-muted-foreground"
                        >
                            {{ r.when }}
                        </td>
                        <td class="py-2 pr-3">{{ r.who }}</td>
                        <template v-if="tab === 'activity'">
                            <td class="py-2 pr-3">{{ r.log }}</td>
                            <td class="py-2">
                                {{ r.description }}
                                <span
                                    v-if="r.subject"
                                    class="block text-[11px] text-muted-foreground"
                                    >{{ r.subject }}</span
                                >
                            </td>
                        </template>
                        <template v-else>
                            <td class="py-2 pr-3">
                                <StatusBadge
                                    :label="String(r.event)"
                                    :tone="eventTone(r.event)"
                                />
                            </td>
                            <td class="py-2 pr-3 font-mono text-[12px]">
                                {{ r.ip }}
                            </td>
                            <td class="py-2 text-[12px] text-muted-foreground">
                                {{ r.agent }}
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
        <TablePagination :meta="rows" />
    </PanelCard>
</template>
