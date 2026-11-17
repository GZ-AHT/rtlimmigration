<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatCard from '@/components/panel/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { security } from '@/routes/admin/chat';
import { update } from '@/routes/admin/chat/security';
import type { Option } from '@/types';

/** REQ §11 G: chat security settings (Super Admin, security.manage). */
type LimitKey =
    | 'messages_per_minute'
    | 'messages_per_hour'
    | 'ip_messages_per_minute'
    | 'conversation_messages_per_minute'
    | 'guest_conversations_per_hour'
    | 'duplicate_window_seconds'
    | 'repeat_threshold'
    | 'violations_before_cooldown'
    | 'cooldown_minutes'
    | 'auto_block_after_cooldowns'
    | 'auto_block_hours'
    | 'max_attachment_kb'
    | 'max_files'
    | 'retention_days'
    | 'guest_session_days'
    | 'alert_cooldown_minutes';

const props = defineProps<{
    settings: {
        guest_chat_enabled: boolean;
        limits: Record<LimitKey, number>;
        attachment_types: string[];
        blocked_keywords: string[];
        blocked_domains: string[];
        trusted_domains: string[];
        captcha_mode: string;
        turnstile_site_key: string;
        has_turnstile_secret: boolean;
    };
    limits: Record<LimitKey, { default: number; min: number; max: number }>;
    attachmentTypes: Option[];
    captchaModes: Option[];
    malwareScanner: string | null;
    stats: {
        queue: number;
        moderation: Record<string, number>;
        events: { type: string; label: string; count: number }[];
        blocks: number;
        suspended: number;
    };
    urls: { moderation: string; activity: string };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Chat security', href: security() }] },
});

const groups: {
    title: string;
    description: string;
    keys: [LimitKey, string, string][];
}[] = [
    {
        title: 'Message limits',
        description:
            'Students and website visitors. Staff are not limited here.',
        keys: [
            ['messages_per_minute', 'Messages per minute, per sender', ''],
            ['messages_per_hour', 'Messages per hour, per sender', ''],
            [
                'ip_messages_per_minute',
                'Messages per minute, per IP address',
                'Keep this higher than the per-sender limit: students may share a network.',
            ],
            [
                'conversation_messages_per_minute',
                'Messages per minute, per conversation',
                'Everyone in the conversation together.',
            ],
            [
                'guest_conversations_per_hour',
                'New website chats per hour, per IP address',
                '',
            ],
        ],
    },
    {
        title: 'Spam and cooldowns',
        description:
            'What happens when someone keeps hitting the limits or repeats themselves.',
        keys: [
            [
                'duplicate_window_seconds',
                'Refuse an identical message within (seconds)',
                '0 turns this off.',
            ],
            [
                'repeat_threshold',
                'Hold a message sent this many times in 10 minutes',
                '',
            ],
            [
                'violations_before_cooldown',
                'Limit hits before a cooldown',
                'Counted over one hour.',
            ],
            ['cooldown_minutes', 'Cooldown length (minutes)', ''],
            [
                'auto_block_after_cooldowns',
                "Block a website visitor's IP after this many cooldowns in a day",
                '0 turns automatic blocking off. Students are never blocked automatically.',
            ],
            ['auto_block_hours', 'Automatic block length (hours)', ''],
        ],
    },
    {
        title: 'Privacy, retention and alerts',
        description:
            'Moderation history and the activity log are audit records and are always kept.',
        keys: [
            [
                'retention_days',
                'Keep the security log for (days)',
                'Older entries, including their IP addresses, are deleted daily.',
            ],
            [
                'guest_session_days',
                'Website visitor sessions last (days)',
                "After this, the visitor's cookie stops working and their IP address and browser details are removed.",
            ],
            [
                'alert_cooldown_minutes',
                'Repeat the same alert at most every (minutes)',
                '',
            ],
        ],
    },
];

const form = useForm({
    guest_chat_enabled: props.settings.guest_chat_enabled,
    limits: { ...props.settings.limits },
    attachment_types: [...props.settings.attachment_types],
    blocked_keywords: props.settings.blocked_keywords.join('\n'),
    blocked_domains: props.settings.blocked_domains.join('\n'),
    trusted_domains: props.settings.trusted_domains.join('\n'),
    captcha_mode: props.settings.captcha_mode,
    turnstile_site_key: props.settings.turnstile_site_key,
    turnstile_secret: '',
    clear_turnstile_secret: false,
});

const lines = (v: string): string[] =>
    v
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean);

function submit(): void {
    form.transform((d) => ({
        ...d,
        blocked_keywords: lines(d.blocked_keywords),
        blocked_domains: lines(d.blocked_domains),
        trusted_domains: lines(d.trusted_domains),
    })).put(update.url(), {
        preserveScroll: true,
        onSuccess: () =>
            form.reset('turnstile_secret', 'clear_turnstile_secret'),
    });
}

const err = (key: string): string | undefined =>
    (form.errors as Record<string, string | undefined>)[key];

/** First error for a list field, whichever line it is on. */
const listErr = (key: string): string | undefined =>
    err(key) ??
    Object.entries(form.errors as Record<string, string>).find(([k]) =>
        k.startsWith(key + '.'),
    )?.[1];

function toggleType(value: string, on: boolean): void {
    form.attachment_types = on
        ? [...new Set([...form.attachment_types, value])]
        : form.attachment_types.filter((t) => t !== value);
}

const captchaMissing = computed(
    () =>
        form.captcha_mode !== 'off' &&
        (!form.turnstile_site_key ||
            (!props.settings.has_turnstile_secret && !form.turnstile_secret) ||
            form.clear_turnstile_secret),
);
</script>

<template>
    <Head title="Chat security" />

    <PageHeader
        eyebrow="Live chat"
        title="Chat security"
        description="Spam protection, content rules, attachments and CAPTCHA for portal messages and website chat."
    >
        <template #actions>
            <Button variant="outline" as-child>
                <Link :href="urls.moderation"
                    ><AppIcon name="flag" :size="18" /> Moderation</Link
                >
            </Button>
            <Button variant="outline" as-child>
                <Link :href="urls.activity"
                    ><AppIcon name="list_alt" :size="18" /> Security log &amp;
                    blocks</Link
                >
            </Button>
        </template>
    </PageHeader>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Needs review" :value="stats.queue" icon="flag" />
        <StatCard
            label="Held or blocked (7 days)"
            :value="
                (stats.moderation.pending ?? 0) +
                (stats.moderation.blocked ?? 0)
            "
            icon="block"
        />
        <StatCard
            label="Blocked IP addresses"
            :value="stats.blocks"
            icon="shield"
        />
        <StatCard
            label="Suspended visitors"
            :value="stats.suspended"
            icon="person_off"
        />
    </div>

    <PanelCard
        v-if="stats.events.length"
        title="Security events, last 7 days"
        class="mb-4"
    >
        <ul class="flex flex-wrap gap-2 text-xs">
            <li
                v-for="e in stats.events"
                :key="e.type"
                class="rounded-full bg-muted px-2.5 py-1"
            >
                {{ e.label }} <b class="ml-1">{{ e.count }}</b>
            </li>
        </ul>
    </PanelCard>

    <form class="grid gap-4 lg:grid-cols-2" @submit.prevent="submit">
        <div class="flex flex-col gap-4">
            <PanelCard
                title="Website chat"
                description="Lets visitors without an account chat with your team from the website. The Tawk.to widget is not affected."
            >
                <label class="flex items-center gap-2 text-[13px]">
                    <Checkbox
                        :model-value="form.guest_chat_enabled"
                        @update:model-value="
                            (v) => (form.guest_chat_enabled = v === true)
                        "
                    />
                    Turn on website chat
                </label>

                <div class="mt-4 flex flex-col gap-4">
                    <FormField
                        label="CAPTCHA (Cloudflare Turnstile)"
                        :error="err('captcha_mode')"
                        hint="Asked before a website chat starts, and again if a visitor behaves suspiciously."
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <SelectInput
                                :id="id"
                                v-model="form.captcha_mode"
                                :options="captchaModes"
                                :invalid="invalid"
                                :described-by="describedBy"
                            />
                        </template>
                    </FormField>
                    <FormField
                        label="Turnstile site key"
                        :error="err('turnstile_site_key')"
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <Input
                                :id="id"
                                v-model="form.turnstile_site_key"
                                autocomplete="off"
                                spellcheck="false"
                                class="font-mono text-xs"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>
                    <FormField
                        label="Turnstile secret key"
                        :error="err('turnstile_secret')"
                        :hint="
                            settings.has_turnstile_secret
                                ? 'A secret is saved (encrypted). Leave blank to keep it.'
                                : 'Stored encrypted. Never sent to the browser.'
                        "
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <Input
                                :id="id"
                                v-model="form.turnstile_secret"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="
                                    settings.has_turnstile_secret
                                        ? '••••••••'
                                        : ''
                                "
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>
                    <label
                        v-if="settings.has_turnstile_secret"
                        class="flex items-center gap-2 text-xs"
                    >
                        <Checkbox
                            :model-value="form.clear_turnstile_secret"
                            @update:model-value="
                                (v) =>
                                    (form.clear_turnstile_secret = v === true)
                            "
                        />
                        Remove the saved secret
                    </label>
                    <p
                        v-if="captchaMissing"
                        class="flex gap-2 rounded-lg bg-warning-soft p-2.5 text-xs text-warning"
                        role="status"
                    >
                        <AppIcon name="info" :size="16" class="shrink-0" />
                        Both Turnstile keys are needed before a CAPTCHA can be
                        shown. Until then, visitors that look automated are
                        refused instead.
                    </p>
                </div>
            </PanelCard>

            <PanelCard
                title="Attachments"
                description="Chat is text-only unless you switch on file types here. Website visitors can never attach files."
            >
                <fieldset class="flex flex-col gap-2">
                    <legend class="sr-only">Allowed file types</legend>
                    <label
                        v-for="t in attachmentTypes"
                        :key="t.value"
                        class="flex items-center gap-2 text-[13px]"
                    >
                        <Checkbox
                            :model-value="
                                form.attachment_types.includes(t.value)
                            "
                            @update:model-value="
                                (v) => toggleType(t.value, v === true)
                            "
                        />
                        Allow {{ t.label }}
                    </label>
                </fieldset>
                <p
                    v-if="!form.attachment_types.length"
                    class="mt-2 text-xs text-muted-foreground"
                >
                    No file types selected: messages are text only.
                </p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Largest file (KB)"
                        :error="err('limits.max_attachment_kb')"
                        :hint="`${limits.max_attachment_kb.min}–${limits.max_attachment_kb.max} KB`"
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <Input
                                :id="id"
                                v-model.number="form.limits.max_attachment_kb"
                                type="number"
                                :min="limits.max_attachment_kb.min"
                                :max="limits.max_attachment_kb.max"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>
                    <FormField
                        label="Files per message"
                        :error="err('limits.max_files')"
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <Input
                                :id="id"
                                v-model.number="form.limits.max_files"
                                type="number"
                                :min="limits.max_files.min"
                                :max="limits.max_files.max"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">
                    Every file is checked on the server: its real type is read
                    from its contents, its signature must match, scripts and
                    embedded code are refused, and it is stored privately under
                    a random name.
                    <template v-if="malwareScanner">
                        Virus scanning: {{ malwareScanner }}.</template
                    >
                    <template v-else>
                        No virus scanner is set up on this server (set
                        CHAT_MALWARE_SCANNER=clamav to use ClamAV).</template
                    >
                </p>
            </PanelCard>

            <PanelCard
                title="Content rules"
                description="One entry per line. Links and unfamiliar words are fine on their own; only these rules and clear spam signs hold a message."
            >
                <div class="flex flex-col gap-4">
                    <FormField
                        label="Blocked keywords and phrases"
                        :error="listErr('blocked_keywords')"
                        hint="A message containing one is held for review."
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <textarea
                                :id="id"
                                v-model="form.blocked_keywords"
                                rows="5"
                                spellcheck="false"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </template>
                    </FormField>
                    <FormField
                        label="Blocked domains"
                        :error="listErr('blocked_domains')"
                        hint="e.g. example.com. Covers its subdomains too."
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <textarea
                                :id="id"
                                v-model="form.blocked_domains"
                                rows="4"
                                spellcheck="false"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </template>
                    </FormField>
                    <FormField
                        label="Trusted domains"
                        :error="listErr('trusted_domains')"
                        hint="Links here are never flagged: universities, embassies, your own site."
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <textarea
                                :id="id"
                                v-model="form.trusted_domains"
                                rows="4"
                                spellcheck="false"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </template>
                    </FormField>
                </div>
            </PanelCard>
        </div>

        <div class="flex flex-col gap-4">
            <PanelCard
                v-for="g in groups"
                :key="g.title"
                :title="g.title"
                :description="g.description"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        v-for="[key, text, hint] in g.keys"
                        :key="key"
                        :label="text"
                        :error="err('limits.' + key)"
                        :hint="
                            [hint, `Default ${limits[key].default}`]
                                .filter(Boolean)
                                .join(' ')
                        "
                    >
                        <template #default="{ id, invalid, describedBy }">
                            <Input
                                :id="id"
                                v-model.number="form.limits[key]"
                                type="number"
                                :min="limits[key].min"
                                :max="limits[key].max"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>
                </div>
            </PanelCard>

            <Button
                type="submit"
                size="lg"
                :disabled="form.processing"
                class="self-start"
                >Save security settings</Button
            >
        </div>
    </form>
</template>
