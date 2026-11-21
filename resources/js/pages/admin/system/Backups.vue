<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import SchemaFields from '@/components/cms/SchemaFields.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { clone } from '@/lib/clone';
import {
    destroy,
    download,
    index,
    restore,
    store,
} from '@/routes/admin/system/backups';
import { update } from '@/routes/admin/system/settings';
import type { FieldDef, MediaItem } from '@/types';

type Row = {
    id: number;
    type: 'database' | 'files';
    trigger: string;
    filename: string;
    size: string;
    status: 'running' | 'done' | 'failed';
    error: string | null;
    by: string | null;
    when: string | null;
    restored_at: string | null;
};

const props = defineProps<{
    backups: Row[];
    canRestore: boolean;
    phrase: string;
    groups: Record<
        string,
        { label: string; description: string; fields: Record<string, FieldDef> }
    >;
    values: Record<string, Record<string, unknown>>;
    running: boolean;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Backups', href: index() }] },
});

const values = reactive(
    clone(props.values) as Record<string, Record<string, unknown>>,
);
const media = reactive<Record<number, MediaItem>>({});
const errors = ref<Record<string, string>>({});
const deleting = ref<Row | null>(null);
const restoring = ref<Row | null>(null);
const form = useForm({ password: '', phrase: '' });

// Backups run after the response, so the list refreshes itself while one is running.
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    timer = setInterval(() => {
        if (props.running) {
            router.reload({ only: ['backups', 'running'] });
        }
    }, 4000);
});
onBeforeUnmount(() => clearInterval(timer));

const tone = (s: Row['status']) =>
    s === 'done' ? 'success' : s === 'failed' ? 'danger' : 'warning';

function saveSettings(): void {
    router.put(update('backups').url, values.backups as Record<string, never>, {
        preserveScroll: true,
        onSuccess: () => (errors.value = {}),
        onError: (e) => (errors.value = e),
    });
}

function runRestore(): void {
    if (!restoring.value) {
        return;
    }

    form.post(restore(restoring.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            restoring.value = null;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Backups" />

    <PageHeader
        eyebrow="System"
        title="Backups"
        description="Copies of the database and uploaded files. They contain personal data, so keep downloads somewhere safe."
    >
        <template #actions>
            <Button
                variant="outline"
                @click="
                    router.post(
                        store().url,
                        { type: 'files' },
                        { preserveScroll: true },
                    )
                "
                >Back up files</Button
            >
            <Button
                @click="
                    router.post(
                        store().url,
                        { type: 'database' },
                        { preserveScroll: true },
                    )
                "
                >Back up database</Button
            >
        </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.8fr)_minmax(280px,1fr)]">
        <PanelCard :title="`Backups (${backups.length})`">
            <p
                v-if="backups.length === 0"
                class="py-6 text-center text-[13px] text-muted-foreground"
            >
                No backups yet.
            </p>
            <ul v-else class="flex flex-col divide-y">
                <li v-for="b in backups" :key="b.id" class="py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <StatusBadge :label="b.status" :tone="tone(b.status)" />
                        <span class="text-[13px] font-semibold">{{
                            b.type === 'database' ? 'Database' : 'Files'
                        }}</span>
                        <span class="text-[12px] text-muted-foreground"
                            >{{ b.trigger }} · {{ b.size }} · {{ b.when }}
                            <template v-if="b.by"> · {{ b.by }}</template></span
                        >
                    </div>
                    <p
                        class="mt-0.5 font-mono text-[11px] text-muted-foreground"
                    >
                        {{ b.filename }}
                    </p>
                    <p v-if="b.error" class="mt-1 text-[12px] text-danger">
                        {{ b.error }}
                    </p>
                    <p
                        v-if="b.restored_at"
                        class="mt-1 text-[12px] text-muted-foreground"
                    >
                        Restored {{ b.restored_at }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <a
                            v-if="b.status === 'done'"
                            :href="download(b.id).url"
                            class="tiny-action"
                            >Download</a
                        >
                        <button
                            v-if="
                                canRestore &&
                                b.type === 'database' &&
                                b.status === 'done'
                            "
                            type="button"
                            class="tiny-action"
                            @click="
                                restoring = b;
                                form.reset();
                                form.clearErrors();
                            "
                        >
                            Restore…
                        </button>
                        <button
                            type="button"
                            class="tiny-action"
                            @click="deleting = b"
                        >
                            Delete
                        </button>
                    </div>
                </li>
            </ul>
        </PanelCard>

        <PanelCard
            v-for="(group, key) in groups"
            :key="key"
            :title="group.label"
            :description="group.description"
            class="self-start"
        >
            <form class="flex flex-col gap-4" @submit.prevent="saveSettings">
                <SchemaFields
                    v-model="values[key]"
                    :fields="group.fields"
                    :media="media"
                    :errors="errors"
                />
                <div><Button type="submit">Save</Button></div>
            </form>
        </PanelCard>
    </div>

    <Dialog
        :open="restoring !== null"
        @update:open="(v) => !v && (restoring = null)"
    >
        <DialogContent class="rounded-[14px] sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold"
                    >Restore this backup?</DialogTitle
                >
                <DialogDescription>
                    Everything in the database is replaced with the data from
                    {{ restoring?.when }}. Changes made since then are lost (a
                    safety backup of the current data is made first). You may be
                    signed out afterwards. Uploaded files are not changed.
                </DialogDescription>
            </DialogHeader>
            <form class="flex flex-col gap-3.5" @submit.prevent="runRestore">
                <FormField
                    v-slot="{ id, invalid }"
                    label="Your password"
                    required
                    :error="form.errors.password"
                >
                    <Input
                        :id="id"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Type RESTORE to confirm"
                    required
                    :error="form.errors.phrase"
                >
                    <Input
                        :id="id"
                        v-model="form.phrase"
                        autocomplete="off"
                        :placeholder="phrase"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="restoring = null"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="form.processing"
                        >Restore database</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="deleting !== null"
        title="Delete this backup?"
        description="The file is removed from the server. Download it first if you want to keep it."
        confirm-label="Delete"
        destructive
        @update:open="(v) => !v && (deleting = null)"
        @confirm="
            deleting &&
            router.delete(destroy(deleting.id).url, {
                preserveScroll: true,
                onFinish: () => (deleting = null),
            })
        "
    />
</template>
