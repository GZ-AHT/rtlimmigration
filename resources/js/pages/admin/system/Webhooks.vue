<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
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
import {
    destroy,
    index,
    secret as renewSecret,
    store,
    test,
    update,
} from '@/routes/admin/system/webhooks';

type Delivery = {
    id: number;
    event: string;
    ok: boolean;
    status: number | null;
    error: string | null;
    ms: number | null;
    attempt: number;
    when: string;
};
type Hook = {
    id: number;
    name: string;
    url: string;
    events: string[];
    is_active: boolean;
    failure_count: number;
    last_status: number | null;
    last_delivered_at: string | null;
    deliveries: Delivery[];
};

defineProps<{
    webhooks: Hook[];
    events: { value: string; label: string }[];
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Webhooks', href: index() }] },
});

const editing = ref<Hook | null>(null);
const open = ref(false);
const deleting = ref<Hook | null>(null);
const revealed = ref<{ name: string; secret: string } | null>(null);
const form = useForm({
    name: '',
    url: '',
    events: [] as string[],
    is_active: true,
});
const urlHint = 'Must be a public https:// address.';

// The signing secret arrives once, as flash data after creating or renewing.
let stop: (() => void) | undefined;
onMounted(() => {
    stop = router.on('flash', (event) => {
        const data = (event as CustomEvent).detail?.flash?.webhook_secret;

        if (data) {
            revealed.value = data;
        }
    });
});
onBeforeUnmount(() => stop?.());

function openNew(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(h: Hook): void {
    editing.value = h;
    Object.assign(form, {
        name: h.name,
        url: h.url,
        events: [...h.events],
        is_active: h.is_active,
    });
    form.clearErrors();
    open.value = true;
}

function toggle(event: string, on: boolean): void {
    form.events = on
        ? [...form.events, event]
        : form.events.filter((e) => e !== event);
}

function save(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (editing.value) {
        form.put(update(editing.value.id).url, options);
    } else {
        form.post(store().url, options);
    }
}
</script>

<template>
    <Head title="Webhooks" />

    <PageHeader
        eyebrow="Integrations"
        title="Webhooks"
        description="Send a signed message to another system when something happens here. Messages carry ids and statuses only, never personal details."
    >
        <template #actions>
            <Button @click="openNew"
                ><AppIcon name="add" :size="18" /> Add webhook</Button
            >
        </template>
    </PageHeader>

    <p
        v-if="webhooks.length === 0"
        class="rounded-lg border border-dashed p-8 text-center text-[13px] text-muted-foreground"
    >
        No webhooks yet.
    </p>

    <div class="flex flex-col gap-4">
        <PanelCard v-for="h in webhooks" :key="h.id" :title="h.name">
            <template #actions>
                <StatusBadge
                    :label="h.is_active ? 'Active' : 'Off'"
                    :tone="h.is_active ? 'success' : 'neutral'"
                />
            </template>
            <p class="font-mono text-[12px] break-all">{{ h.url }}</p>
            <p class="mt-1 text-[12px] text-muted-foreground">
                {{ h.events.join(', ') }}
            </p>
            <p
                v-if="!h.is_active && h.failure_count >= 25"
                class="mt-2 text-[12px] text-danger"
            >
                Switched off after {{ h.failure_count }} failed deliveries in a
                row. Fix the receiver, then turn it back on.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" class="tiny-action" @click="openEdit(h)">
                    Edit
                </button>
                <button
                    type="button"
                    class="tiny-action"
                    @click="
                        router.post(
                            test(h.id).url,
                            {},
                            { preserveScroll: true },
                        )
                    "
                >
                    Send test
                </button>
                <button
                    type="button"
                    class="tiny-action"
                    @click="
                        router.post(
                            renewSecret(h.id).url,
                            {},
                            { preserveScroll: true },
                        )
                    "
                >
                    New secret
                </button>
                <button type="button" class="tiny-action" @click="deleting = h">
                    Delete
                </button>
            </div>
            <details v-if="h.deliveries.length" class="mt-3">
                <summary class="cursor-pointer text-[12px] font-semibold">
                    Recent deliveries ({{ h.deliveries.length }})
                </summary>
                <ul class="mt-2 flex flex-col gap-1 text-[12px]">
                    <li
                        v-for="d in h.deliveries"
                        :key="d.id"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <StatusBadge
                            :label="d.ok ? 'Delivered' : 'Failed'"
                            :tone="d.ok ? 'success' : 'danger'"
                        />
                        <span class="font-mono">{{ d.event }}</span>
                        <span class="text-muted-foreground"
                            >{{ d.status ?? d.error }} · {{ d.ms }} ms ·
                            {{ d.when }}</span
                        >
                    </li>
                </ul>
            </details>
        </PanelCard>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="rounded-[14px] sm:max-w-[520px]">
            <DialogHeader
                ><DialogTitle class="font-display text-xl font-extrabold">{{
                    editing ? 'Edit webhook' : 'Add webhook'
                }}</DialogTitle></DialogHeader
            >
            <form class="flex flex-col gap-3.5" @submit.prevent="save">
                <FormField
                    v-slot="{ id, invalid }"
                    label="Name"
                    required
                    :error="form.errors.name"
                >
                    <Input
                        :id="id"
                        v-model="form.name"
                        maxlength="120"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Receiving address"
                    required
                    :error="form.errors.url"
                    :hint="urlHint"
                >
                    <Input
                        :id="id"
                        v-model="form.url"
                        maxlength="500"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1 text-[13px] font-medium">
                        Send me when…
                    </legend>
                    <label
                        v-for="e in events"
                        :key="e.value"
                        class="flex items-center gap-2 text-[13px]"
                    >
                        <Checkbox
                            :model-value="form.events.includes(e.value)"
                            @update:model-value="
                                (v) => toggle(e.value, v === true)
                            "
                        />
                        {{ e.label }}
                    </label>
                    <p
                        v-if="form.errors.events"
                        class="text-[12px] text-danger"
                    >
                        {{ form.errors.events }}
                    </p>
                </fieldset>
                <label class="flex items-center gap-2 text-[13px]"
                    ><Checkbox
                        :model-value="form.is_active"
                        @update:model-value="
                            (v) => (form.is_active = v === true)
                        "
                    />
                    Active</label
                >
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="revealed !== null"
        @update:open="(v) => !v && (revealed = null)"
    >
        <DialogContent class="rounded-[14px] sm:max-w-[520px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold"
                    >Signing secret for {{ revealed?.name }}</DialogTitle
                >
                <DialogDescription>
                    Copy it now and give it to the receiving system. It can't be
                    shown again. Each message carries an
                    <code>X-RTL-Signature</code> header: an HMAC-SHA256 of
                    <code>timestamp.body</code> using this secret.
                </DialogDescription>
            </DialogHeader>
            <code
                class="block rounded-md bg-muted p-3 text-[13px] break-all select-all"
                >{{ revealed?.secret }}</code
            >
            <DialogFooter>
                <Button @click="revealed = null">Done</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="deleting !== null"
        title="Delete this webhook?"
        description="Its delivery history is deleted too."
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
