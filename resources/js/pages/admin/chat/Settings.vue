<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import DataTable from '@/components/panel/DataTable.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { relativeTime } from '@/lib/format';
import { settings as settingsRoute } from '@/routes/admin/chat';
import { update } from '@/routes/admin/chat/settings';
import type { TableColumn } from '@/types';

type Delivery = {
    id: number;
    event: string | null;
    external_id: string | null;
    signature_valid: boolean;
    result: string | null;
    deliveries: number;
    processed: boolean;
    created_at: string | null;
};

type WebhookEnv = 'local' | 'staging' | 'production';

const webhookEnvs: { key: WebhookEnv; label: string; hint: string }[] = [
    {
        key: 'local',
        label: 'Local development',
        hint: 'http:// is allowed. Use a tunnel address if you test webhooks locally.',
    },
    {
        key: 'staging',
        label: 'Staging',
        hint: 'Must be a public https:// address.',
    },
    {
        key: 'production',
        label: 'Production',
        hint: 'Must be a public https:// address.',
    },
];

const props = defineProps<{
    settings: {
        enabled: boolean;
        property_id: string;
        widget_id: string;
        show_on_website: boolean;
        show_in_portal: boolean;
        portal_tags: string[];
        has_api_key: boolean;
        has_webhook_secret: boolean;
        webhook_urls: Record<WebhookEnv, string>;
    };
    webhookUrl: string;
    defaultWebhookUrl: string;
    environment: WebhookEnv;
    maxTags: number;
    tawkUrl: string;
    deliveries: Delivery[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Chat settings', href: settingsRoute() }],
    },
});

const form = useForm({
    enabled: props.settings.enabled,
    property_id: props.settings.property_id,
    widget_id: props.settings.widget_id,
    show_on_website: props.settings.show_on_website,
    show_in_portal: props.settings.show_in_portal,
    portal_tags: props.settings.portal_tags.join(', '),
    api_key: '',
    webhook_secret: '',
    clear_api_key: false,
    clear_webhook_secret: false,
    webhook_urls: { ...props.settings.webhook_urls },
});

function save(): void {
    form.transform((d) => ({
        ...d,
        portal_tags: d.portal_tags
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean),
    })).submit(update(), {
        preserveScroll: true,
        onSuccess: () =>
            form.reset(
                'api_key',
                'webhook_secret',
                'clear_api_key',
                'clear_webhook_secret',
            ),
    });
}

const envLabel = (key: WebhookEnv, label: string): string =>
    key === props.environment ? `${label} (this server)` : label;

function envPlaceholder(key: WebhookEnv): string {
    if (key === props.environment) {
        return props.defaultWebhookUrl;
    }

    return key === 'local'
        ? 'http://localhost:8000/chat/webhook/tawk'
        : 'https://your-domain/chat/webhook/tawk';
}

const copied = ref<string | null>(null);

async function copyText(key: string, text: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;
        setTimeout(() => {
            if (copied.value === key) {
                copied.value = null;
            }
        }, 2000);
    } catch {
        toast.error('Copy failed. Select the address and copy it by hand.');
    }
}

const err = (key: string) =>
    (form.errors as Record<string, string | undefined>)[key];

const columns: TableColumn<Delivery>[] = [
    { key: 'event', label: 'Event' },
    { key: 'result', label: 'Result' },
    {
        key: 'created_at',
        label: 'Received',
        hideBelow: 'md',
        format: (r) =>
            relativeTime(r.created_at) +
            (r.deliveries > 1 ? ` · ×${r.deliveries}` : ''),
    },
];
</script>

<template>
    <Head title="Chat settings" />

    <PageHeader
        eyebrow="Live chat"
        title="Tawk.to settings"
        description="Connect the chat widget and let Tawk.to report chats back to this system."
    >
        <template #actions>
            <Button variant="outline" as-child>
                <a :href="tawkUrl" target="_blank" rel="noopener noreferrer"
                    ><AppIcon name="open_in_new" :size="18" /> Tawk.to
                    dashboard</a
                >
            </Button>
        </template>
    </PageHeader>

    <div
        v-if="settings.enabled && (!settings.has_api_key || !deliveries.length)"
        class="mb-4 flex items-start gap-2.5 rounded-xl border border-warning/40 bg-warning-soft p-3.5 text-[13px] text-warning"
        role="status"
    >
        <AppIcon name="info" :size="18" class="mt-px shrink-0" />
        <ul class="flex flex-col gap-1.5">
            <li v-if="!deliveries.length">
                <b>Tawk.to hasn't sent this server anything yet.</b> Check that
                Tawk.to → Administration → Webhooks has a webhook pointing at
                <span class="font-mono break-all">{{ webhookUrl }}</span> with
                Chat Start, Chat End and Chat Transcript ticked, and that this
                address is reachable from the internet. Even a delivery with a
                wrong secret would be listed below.
            </li>
            <li v-if="!settings.has_api_key">
                <b>Secure Mode is off.</b> Chats are recorded but not linked to
                students, so they won't show in a student's portal or reach
                their counselor. Save the Secure Mode API key below.
            </li>
        </ul>
    </div>

    <form
        class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(300px,1fr)]"
        @submit.prevent="save"
    >
        <div class="flex min-w-0 flex-col gap-4">
            <PanelCard
                title="Chat widget"
                description="In Tawk.to: Administration → Chat Widget. The widget link looks like embed.tawk.to/PROPERTY_ID/WIDGET_ID."
            >
                <div class="flex flex-col gap-4">
                    <label class="flex items-start gap-2.5 text-[13px]">
                        <Checkbox
                            :model-value="form.enabled"
                            class="mt-0.5"
                            @update:model-value="
                                (v) => (form.enabled = v === true)
                            "
                        />
                        <span
                            ><b>Live chat is on</b><br /><span
                                class="text-muted-foreground"
                                >Show the chat widget to the audiences
                                below.</span
                            ></span
                        >
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Property ID"
                            :error="err('property_id')"
                        >
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="form.property_id"
                                    autocomplete="off"
                                    spellcheck="false"
                                    :aria-invalid="invalid"
                                />
                            </template>
                        </FormField>
                        <FormField label="Widget ID" :error="err('widget_id')">
                            <template #default="{ id, invalid }">
                                <Input
                                    :id="id"
                                    v-model="form.widget_id"
                                    autocomplete="off"
                                    spellcheck="false"
                                    placeholder="default"
                                    :aria-invalid="invalid"
                                />
                            </template>
                        </FormField>
                    </div>
                    <fieldset class="flex flex-col gap-2.5 text-[13px]">
                        <legend class="mb-2 text-xs font-semibold">
                            Show the widget to
                        </legend>
                        <label class="flex items-center gap-2.5">
                            <Checkbox
                                :model-value="form.show_on_website"
                                @update:model-value="
                                    (v) => (form.show_on_website = v === true)
                                "
                            />
                            Website visitors
                        </label>
                        <label class="flex items-center gap-2.5">
                            <Checkbox
                                :model-value="form.show_in_portal"
                                @update:model-value="
                                    (v) => (form.show_in_portal = v === true)
                                "
                            />
                            Signed-in students, in the student portal
                        </label>
                        <p class="text-xs text-muted-foreground">
                            Staff never see the widget. They answer chats in
                            Tawk.to.
                        </p>
                    </fieldset>
                    <FormField
                        label="Tags for student chats"
                        :error="err('portal_tags') ?? err('portal_tags.0')"
                        :hint="`Comma-separated, up to ${maxTags}. Added in Tawk.to to every chat a student starts from the portal.`"
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <Input
                                :id="id"
                                v-model="form.portal_tags"
                                placeholder="student"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>
                </div>
            </PanelCard>

            <PanelCard
                title="Secure Mode"
                description="Tawk.to then trusts the name, email, student number and file number we send for signed-in students, and their chats are linked to their student record and counselor. In Tawk.to: Administration → Property Settings → Secure Mode."
            >
                <FormField
                    label="Secure Mode API key"
                    :error="err('api_key')"
                    :hint="
                        settings.has_api_key
                            ? 'A key is saved. Leave blank to keep it.'
                            : 'Not set: chats are not linked to students.'
                    "
                >
                    <template #default="{ id, invalid, describedBy }">
                        <Input
                            :id="id"
                            v-model="form.api_key"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                settings.has_api_key ? '••••••••' : ''
                            "
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>
                <label
                    v-if="settings.has_api_key"
                    class="mt-2 flex items-center gap-2 text-xs"
                >
                    <Checkbox
                        :model-value="form.clear_api_key"
                        @update:model-value="
                            (v) => (form.clear_api_key = v === true)
                        "
                    />
                    Remove the saved key
                </label>
            </PanelCard>
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard
                title="Webhooks"
                description="In Tawk.to: Administration → Webhooks. Create one with this address and tick Chat Start, Chat End, Chat Transcript and New Ticket. Then paste its secret key here."
            >
                <div class="flex flex-col gap-4">
                    <FormField
                        label="Webhook address for this server"
                        :hint="`This server runs as ${environment}. Paste this address into Tawk.to.`"
                    >
                        <template #default="{ id, describedBy }">
                            <div class="flex gap-2">
                                <Input
                                    :id="id"
                                    :model-value="webhookUrl"
                                    readonly
                                    class="font-mono text-xs"
                                    :aria-describedby="describedBy"
                                    @focus="
                                        (
                                            $event.target as HTMLInputElement
                                        ).select()
                                    "
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-9"
                                    @click="copyText('active', webhookUrl)"
                                    >{{
                                        copied === 'active' ? 'Copied' : 'Copy'
                                    }}</Button
                                >
                            </div>
                        </template>
                    </FormField>
                    <fieldset class="flex flex-col gap-3">
                        <legend class="mb-1 text-xs font-semibold">
                            Address per environment
                        </legend>
                        <p class="text-xs text-muted-foreground">
                            Leave blank to use this server's own address. Each
                            address must end with
                            <code class="font-mono">/chat/webhook/tawk</code>.
                        </p>
                        <FormField
                            v-for="env in webhookEnvs"
                            :key="env.key"
                            :label="envLabel(env.key, env.label)"
                            :error="err('webhook_urls.' + env.key)"
                            :hint="env.hint"
                        >
                            <template #default="{ id, invalid, describedBy }">
                                <div class="flex gap-2">
                                    <Input
                                        :id="id"
                                        v-model="form.webhook_urls[env.key]"
                                        type="url"
                                        inputmode="url"
                                        autocomplete="off"
                                        spellcheck="false"
                                        class="font-mono text-xs"
                                        :placeholder="envPlaceholder(env.key)"
                                        :aria-invalid="invalid"
                                        :aria-describedby="describedBy"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-9"
                                        :disabled="!form.webhook_urls[env.key]"
                                        :aria-label="
                                            'Copy the ' + env.label + ' address'
                                        "
                                        @click="
                                            copyText(
                                                env.key,
                                                form.webhook_urls[env.key],
                                            )
                                        "
                                        >{{
                                            copied === env.key
                                                ? 'Copied'
                                                : 'Copy'
                                        }}</Button
                                    >
                                </div>
                            </template>
                        </FormField>
                    </fieldset>
                    <FormField
                        label="Webhook secret key"
                        :error="err('webhook_secret')"
                        :hint="
                            settings.has_webhook_secret
                                ? 'A secret is saved. Leave blank to keep it.'
                                : 'Not set: every webhook is rejected.'
                        "
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <Input
                                :id="id"
                                v-model="form.webhook_secret"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="
                                    settings.has_webhook_secret
                                        ? '••••••••'
                                        : ''
                                "
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>
                    <label
                        v-if="settings.has_webhook_secret"
                        class="flex items-center gap-2 text-xs"
                    >
                        <Checkbox
                            :model-value="form.clear_webhook_secret"
                            @update:model-value="
                                (v) => (form.clear_webhook_secret = v === true)
                            "
                        />
                        Remove the saved secret
                    </label>
                </div>
            </PanelCard>

            <Button
                type="submit"
                size="lg"
                :disabled="form.processing"
                class="self-start"
                >Save settings</Button
            >
        </div>
    </form>

    <PanelCard
        flush
        title="Recent webhook deliveries"
        description="The last 25 events Tawk.to sent. Rejected ones are kept without their content."
        class="mt-4"
    >
        <DataTable
            :columns="columns"
            :rows="deliveries"
            caption="Recent webhook deliveries"
            empty-icon="webhook"
            empty-title="Nothing received yet"
        >
            <template #cell-event="{ row }">
                <span class="font-mono text-xs">{{ row.event ?? '—' }}</span>
            </template>
            <template #cell-result="{ row }">
                <StatusBadge
                    :label="row.result ?? 'Pending'"
                    :tone="
                        !row.signature_valid
                            ? 'danger'
                            : row.processed
                              ? 'success'
                              : 'warning'
                    "
                />
            </template>
        </DataTable>
    </PanelCard>
</template>
