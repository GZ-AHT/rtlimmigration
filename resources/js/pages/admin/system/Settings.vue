<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import SchemaFields from '@/components/cms/SchemaFields.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { clone } from '@/lib/clone';
import { settings } from '@/routes/admin/system';
import { clear } from '@/routes/admin/system/cache';
import { update } from '@/routes/admin/system/settings';
import type { FieldDef, MediaItem } from '@/types';

type Group = {
    label: string;
    description: string;
    fields: Record<string, FieldDef>;
};
type LogEntry = {
    time: string;
    level: string;
    message: string;
    detail: string;
};

const props = defineProps<{
    tab: string;
    tabs: { key: string; label: string }[];
    groups: Record<string, Group>;
    values: Record<string, Record<string, unknown>>;
    secrets: Record<string, Record<string, boolean>>;
    overview: Record<string, Record<string, string>> | null;
    storage: Record<string, string> | null;
    logs: {
        file: string | null;
        entries: LogEntry[];
        level: string;
    } | null;
    levels: string[];
    caches: string[];
    maintenanceActive: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'System settings', href: settings() }] },
});

const values = reactive(
    clone(props.values) as Record<string, Record<string, unknown>>,
);
const media = reactive<Record<number, MediaItem>>({});
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const confirmAll = ref(false);

function go(tab: string, extra: Record<string, string> = {}): void {
    router.get(
        settings().url,
        { tab, ...extra },
        { preserveScroll: true, replace: true },
    );
}

// A saved secret is never sent back: say so, and let a blank keep it.
const fieldsFor = (key: string): Record<string, FieldDef> =>
    Object.fromEntries(
        Object.entries(props.groups[key].fields).map(([name, f]) => [
            name,
            f.type === 'secret' && props.secrets[key]?.[name]
                ? {
                      ...f,
                      hint: 'A key is saved and hidden. Leave blank to keep it, or type a new one to replace it.',
                  }
                : f,
        ]),
    );

function save(key: string): void {
    router.put(update(key).url, values[key] as Record<string, never>, {
        preserveScroll: true,
        onStart: () => (saving.value = true),
        onFinish: () => (saving.value = false),
        onSuccess: () => (errors.value = {}),
        onError: (e) => (errors.value = e),
    });
}

const cacheLabels: Record<string, string> = {
    application: 'Application cache',
    route: 'Route cache',
    view: 'View cache',
    config: 'Configuration cache',
};
const levelOptions = computed(() => ['', ...props.levels]);
const levelTone = (l: string) =>
    ['emergency', 'alert', 'critical', 'error'].includes(l)
        ? 'danger'
        : l === 'warning'
          ? 'warning'
          : 'neutral';
</script>

<template>
    <Head title="System settings" />

    <PageHeader
        eyebrow="System"
        title="System settings"
        description="Server information, maintenance, security and integrations."
    />

    <PanelTabs
        :model-value="tab"
        label="System settings"
        :tabs="tabs"
        @update:model-value="(t) => go(t)"
    />

    <template v-if="tab === 'overview' && overview">
        <div class="grid gap-4 lg:grid-cols-2">
            <PanelCard
                v-for="(rows, section) in overview"
                :key="section"
                :title="String(section)"
            >
                <dl class="flex flex-col gap-2 text-[13px]">
                    <div
                        v-for="(value, label) in rows"
                        :key="label"
                        class="flex justify-between gap-4 border-b border-dashed pb-2 last:border-0"
                    >
                        <dt class="text-muted-foreground">{{ label }}</dt>
                        <dd class="text-right font-medium break-all">
                            {{ value }}
                        </dd>
                    </div>
                </dl>
            </PanelCard>
            <PanelCard
                v-if="storage"
                title="Storage"
                description="Disk space used by uploaded files, documents and backups."
            >
                <dl class="flex flex-col gap-2 text-[13px]">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Public files</dt>
                        <dd class="font-medium">{{ storage.public }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Private files</dt>
                        <dd class="font-medium">{{ storage.private }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Backups</dt>
                        <dd class="font-medium">{{ storage.backups }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Disk free / total</dt>
                        <dd class="font-medium">
                            {{ storage.free }} / {{ storage.total }}
                        </dd>
                    </div>
                </dl>
            </PanelCard>
        </div>
    </template>

    <PanelCard
        v-else-if="tab === 'cache'"
        title="Cache tools"
        description="Clear stored copies when a change doesn't show up. The site rebuilds them on the next visit, so it may be a little slower for a moment."
    >
        <div class="flex flex-wrap gap-2">
            <Button
                v-for="c in caches"
                :key="c"
                variant="outline"
                @click="router.post(clear(c).url, {}, { preserveScroll: true })"
                >Clear {{ cacheLabels[c]?.toLowerCase() ?? c }}</Button
            >
            <Button @click="confirmAll = true">Clear everything</Button>
        </div>
    </PanelCard>

    <PanelCard
        v-else-if="tab === 'logs' && logs"
        title="Error log"
        :description="
            logs.file
                ? `Latest entries from ${logs.file}, newest first.`
                : 'No log file yet.'
        "
    >
        <template #actions>
            <select
                :value="logs.level"
                class="h-9 rounded-lg border bg-card px-3 text-[13px]"
                aria-label="Filter by level"
                @change="
                    (e) =>
                        go('logs', {
                            level: (e.target as HTMLSelectElement).value,
                        })
                "
            >
                <option v-for="l in levelOptions" :key="l" :value="l">
                    {{ l || 'All levels' }}
                </option>
            </select>
        </template>
        <p
            v-if="logs.entries.length === 0"
            class="py-6 text-center text-[13px] text-muted-foreground"
        >
            Nothing to show.
        </p>
        <ul v-else class="flex flex-col divide-y">
            <li v-for="(e, i) in logs.entries" :key="i" class="py-2.5">
                <div class="flex flex-wrap items-center gap-2 text-[12px]">
                    <StatusBadge :label="e.level" :tone="levelTone(e.level)" />
                    <span class="text-muted-foreground">{{ e.time }}</span>
                </div>
                <p class="mt-1 font-mono text-[12px] break-words">
                    {{ e.message }}
                </p>
                <details v-if="e.detail" class="mt-1">
                    <summary
                        class="cursor-pointer text-[12px] text-muted-foreground"
                    >
                        Details
                    </summary>
                    <pre
                        class="mt-1 max-h-64 overflow-auto rounded bg-muted/40 p-2 text-[11px] whitespace-pre-wrap"
                        >{{ e.detail }}</pre>
                </details>
            </li>
        </ul>
    </PanelCard>

    <template v-else-if="groups[tab]">
        <p v-if="tab === 'maintenance'" class="mb-3 text-[13px]" role="status">
            <StatusBadge
                :label="
                    maintenanceActive
                        ? 'Visitors see the maintenance page now'
                        : 'The website is live'
                "
                :tone="maintenanceActive ? 'warning' : 'success'"
            />
        </p>
        <PanelCard
            :title="groups[tab].label"
            :description="groups[tab].description"
        >
            <form
                class="flex max-w-3xl flex-col gap-4"
                @submit.prevent="save(tab)"
            >
                <SchemaFields
                    v-model="values[tab]"
                    :fields="fieldsFor(tab)"
                    :media="media"
                    :errors="errors"
                />
                <div>
                    <Button type="submit" :disabled="saving"
                        >Save {{ groups[tab].label.toLowerCase() }}</Button
                    >
                </div>
            </form>
        </PanelCard>
    </template>

    <ConfirmDialog
        :open="confirmAll"
        title="Clear all caches?"
        description="Application, route, view and configuration caches will be rebuilt as needed."
        confirm-label="Clear everything"
        @update:open="(v) => (confirmAll = v)"
        @confirm="
            router.post(
                clear('all').url,
                {},
                { preserveScroll: true, onFinish: () => (confirmAll = false) },
            )
        "
    />
</template>
