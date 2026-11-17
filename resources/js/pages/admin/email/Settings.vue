<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { settings as settingsRoute } from '@/routes/admin/email';
import { test, update } from '@/routes/admin/email/settings';

const props = defineProps<{
    settings: {
        cpanel_host: string;
        cpanel_port: number;
        cpanel_username: string;
        has_cpanel_token: boolean;
        verify_tls: boolean;
        domains: string[];
        default_quota_mb: number | null;
        webmail_url: string;
        smtp_host: string;
        smtp_port: number;
        smtp_encryption: 'ssl' | 'tls';
        imap_host: string;
        imap_port: number;
        tracking_enabled: boolean;
    };
    webmailUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Email settings', href: settingsRoute() }],
    },
});

const s = props.settings;
const form = useForm({
    cpanel_host: s.cpanel_host,
    cpanel_port: String(s.cpanel_port),
    cpanel_username: s.cpanel_username,
    cpanel_token: '',
    clear_cpanel_token: false,
    verify_tls: s.verify_tls,
    domains: s.domains.join(', '),
    default_quota_mb: s.default_quota_mb ? String(s.default_quota_mb) : '',
    webmail_url: s.webmail_url,
    smtp_host: s.smtp_host,
    smtp_port: String(s.smtp_port),
    smtp_encryption: s.smtp_encryption,
    imap_host: s.imap_host,
    imap_port: String(s.imap_port),
    tracking_enabled: s.tracking_enabled,
});

function save(): void {
    form.transform((d) => ({
        ...d,
        cpanel_port: Number(d.cpanel_port),
        smtp_port: Number(d.smtp_port),
        imap_port: Number(d.imap_port),
        default_quota_mb: d.default_quota_mb
            ? Number(d.default_quota_mb)
            : null,
        domains: d.domains
            .split(/[\s,]+/)
            .map((x) => x.trim())
            .filter(Boolean),
    })).submit(update(), {
        preserveScroll: true,
        onSuccess: () => form.reset('cpanel_token', 'clear_cpanel_token'),
    });
}

const webmailPlaceholder = 'https://server.example.com:2096';
const webmailHint = `Blank uses ${props.webmailUrl || 'https://your-server:2096'}. Staff land in Roundcube there.`;

const testing = ref(false);
function testConnection(): void {
    router.post(
        test().url,
        {},
        {
            preserveScroll: true,
            onStart: () => (testing.value = true),
            onFinish: () => (testing.value = false),
            onError: (e) => toast.error(e.cpanel ?? 'Connection failed.'),
        },
    );
}

const err = (key: string) =>
    (form.errors as Record<string, string | undefined>)[key] ??
    (key === 'domains'
        ? Object.entries(form.errors as Record<string, string>).find(([k]) =>
              k.startsWith('domains.'),
          )?.[1]
        : undefined);
</script>

<template>
    <Head title="Email settings" />

    <PageHeader
        eyebrow="Email / Roundcube"
        title="Email settings"
        description="The cPanel mail server: creating mailboxes, webmail sign-in and sending through SMTP."
    />

    <form class="grid gap-4 lg:grid-cols-2" @submit.prevent="save">
        <PanelCard
            title="cPanel connection"
            description="Used to create and manage mailboxes and to sign staff in to webmail without a password."
        >
            <div class="flex flex-col gap-3.5">
                <div class="grid gap-3 sm:grid-cols-[1fr_110px]">
                    <FormField
                        v-slot="{ id, invalid, describedBy }"
                        label="Server"
                        :error="err('cpanel_host')"
                        hint="e.g. server.example.com"
                    >
                        <Input
                            :id="id"
                            v-model="form.cpanel_host"
                            autocomplete="off"
                            spellcheck="false"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Port"
                        :error="err('cpanel_port')"
                    >
                        <Input
                            :id="id"
                            v-model="form.cpanel_port"
                            type="number"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                </div>
                <FormField
                    v-slot="{ id, invalid }"
                    label="cPanel username"
                    :error="err('cpanel_username')"
                >
                    <Input
                        :id="id"
                        v-model="form.cpanel_username"
                        autocomplete="off"
                        spellcheck="false"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    label="API token"
                    :error="err('cpanel_token')"
                    :hint="
                        settings.has_cpanel_token
                            ? 'A token is saved. Leave blank to keep it.'
                            : 'Create one in cPanel → Security → Manage API Tokens.'
                    "
                >
                    <Input
                        :id="id"
                        v-model="form.cpanel_token"
                        type="password"
                        autocomplete="off"
                        :placeholder="
                            settings.has_cpanel_token ? '••••••••••••' : ''
                        "
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <label
                    v-if="settings.has_cpanel_token"
                    class="flex items-center gap-2 text-[13px]"
                >
                    <Checkbox
                        :model-value="form.clear_cpanel_token"
                        @update:model-value="
                            (v) => (form.clear_cpanel_token = v === true)
                        "
                    />
                    Remove the saved token
                </label>
                <label class="flex items-center gap-2 text-[13px]">
                    <Checkbox
                        :model-value="form.verify_tls"
                        @update:model-value="
                            (v) => (form.verify_tls = v === true)
                        "
                    />
                    Check the server's SSL certificate (turn off only for a
                    self-signed certificate)
                </label>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="testing || !settings.has_cpanel_token"
                        @click="testConnection"
                    >
                        <AppIcon name="cable" :size="18" /> Test saved
                        connection
                    </Button>
                    <span
                        v-if="!settings.has_cpanel_token"
                        class="text-[11px] text-muted-foreground"
                        >Save a token first.</span
                    >
                </div>
            </div>
        </PanelCard>

        <PanelCard title="Mailboxes and webmail">
            <div class="flex flex-col gap-3.5">
                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    label="Mail domains"
                    :error="err('domains')"
                    hint="Comma-separated. New mailboxes can only use these. Test the connection to see the domains cPanel has."
                >
                    <Input
                        :id="id"
                        v-model="form.domains"
                        placeholder="rtlimmigration.com"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    label="Default storage limit (MB)"
                    :error="err('default_quota_mb')"
                    hint="Suggested for new mailboxes. Blank means unlimited."
                >
                    <Input
                        :id="id"
                        v-model="form.default_quota_mb"
                        type="number"
                        min="1"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, invalid, describedBy }"
                    label="Webmail address"
                    :error="err('webmail_url')"
                    :hint="webmailHint"
                >
                    <Input
                        :id="id"
                        v-model="form.webmail_url"
                        :placeholder="webmailPlaceholder"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <label class="flex items-start gap-2 text-[13px]">
                    <Checkbox
                        :model-value="form.tracking_enabled"
                        @update:model-value="
                            (v) => (form.tracking_enabled = v === true)
                        "
                    />
                    <span
                        >Track opens and link clicks on email sent from the
                        system. Opens are approximate: many mail apps block the
                        tracking image.</span
                    >
                </label>
            </div>
        </PanelCard>

        <PanelCard
            title="SMTP (sending)"
            description="Used when staff send from a mailbox in the system. Blank server uses the cPanel server."
        >
            <div class="flex flex-col gap-3.5">
                <FormField
                    v-slot="{ id, invalid }"
                    label="SMTP server"
                    :error="err('smtp_host')"
                >
                    <Input
                        :id="id"
                        v-model="form.smtp_host"
                        :placeholder="form.cpanel_host || 'mail.example.com'"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id, invalid }"
                        label="Port"
                        :error="err('smtp_port')"
                    >
                        <Input
                            :id="id"
                            v-model="form.smtp_port"
                            type="number"
                            :aria-invalid="invalid"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id }"
                        label="Security"
                        :error="err('smtp_encryption')"
                    >
                        <SelectInput
                            :id="id"
                            v-model="form.smtp_encryption"
                            :options="[
                                {
                                    value: 'ssl',
                                    label: 'SSL (usually port 465)',
                                },
                                {
                                    value: 'tls',
                                    label: 'STARTTLS (usually port 587)',
                                },
                            ]"
                        />
                    </FormField>
                </div>
            </div>
        </PanelCard>

        <PanelCard
            title="IMAP (receiving)"
            description="Shown to staff who set up a phone or desktop mail app. Roundcube on cPanel is already configured."
        >
            <div class="flex flex-col gap-3.5">
                <FormField
                    v-slot="{ id, invalid }"
                    label="IMAP server"
                    :error="err('imap_host')"
                >
                    <Input
                        :id="id"
                        v-model="form.imap_host"
                        :placeholder="form.cpanel_host || 'mail.example.com'"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, invalid }"
                    label="Port"
                    :error="err('imap_port')"
                >
                    <Input
                        :id="id"
                        v-model="form.imap_port"
                        type="number"
                        :aria-invalid="invalid"
                    />
                </FormField>
            </div>
        </PanelCard>

        <div class="flex justify-end lg:col-span-2">
            <Button type="submit" :disabled="form.processing"
                >Save settings</Button
            >
        </div>
    </form>
</template>
