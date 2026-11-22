<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { settings } from '@/routes/admin/notifications';
import { update } from '@/routes/admin/notifications/settings';

/**
 * REQ §20 Notification Settings and Notification Templates: what each role
 * receives by default per category, and each notification's channels and
 * email wording.
 */
type Channels = { in_app: boolean; email: boolean };

const props = defineProps<{
    categories: { key: string; label: string }[];
    roles: { key: string; label: string; defaults: Record<string, Channels> }[];
    templates: {
        type: string;
        label: string;
        category: string;
        mandatory: boolean;
        in_app: boolean;
        email: boolean;
        email_subject: string;
        email_body: string;
    }[];
    placeholders: string[];
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Notification settings', href: settings().url }],
});

const form = useForm({
    roles: Object.fromEntries(
        props.roles.map((r) => [
            r.key,
            Object.fromEntries(
                props.categories.map((c) => [c.key, { ...r.defaults[c.key] }]),
            ),
        ]),
    ) as Record<string, Record<string, Channels>>,
    templates: Object.fromEntries(
        props.templates.map((t) => [
            t.type,
            {
                in_app: t.in_app,
                email: t.email,
                email_subject: t.email_subject,
                email_body: t.email_body,
            },
        ]),
    ) as Record<
        string,
        {
            in_app: boolean;
            email: boolean;
            email_subject: string;
            email_body: string;
        }
    >,
});

const byCategory = computed(() =>
    props.categories
        .map((c) => ({
            ...c,
            items: props.templates.filter((t) => t.category === c.key),
        }))
        .filter((c) => c.items.length),
);

function save(): void {
    form.put(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notification settings" />

    <PageHeader
        eyebrow="Notifications"
        title="Templates & settings"
        description="Choose what each role receives by default, and how each notification is delivered and worded. People can still change their own choices; security alerts always reach the in-app centre."
    >
        <template #actions>
            <Button :disabled="form.processing" @click="save"
                >Save changes</Button
            >
        </template>
    </PageHeader>

    <PanelCard
        class="mb-4"
        title="Defaults by role"
        description="Per category: show in the notification centre (In-app) and send an email copy (Email)."
        flush
    >
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-[13px]">
                <caption class="sr-only">
                    Default notification channels for each role and category
                </caption>
                <thead>
                    <tr
                        class="border-b text-left text-xs text-muted-foreground"
                    >
                        <th scope="col" class="px-5 py-3 font-semibold">
                            Role
                        </th>
                        <th
                            v-for="c in categories"
                            :key="c.key"
                            scope="col"
                            class="px-3 py-3 text-center font-semibold"
                        >
                            {{ c.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="r in roles" :key="r.key">
                        <th
                            scope="row"
                            class="px-5 py-3 text-left font-semibold"
                        >
                            {{ r.label }}
                        </th>
                        <td
                            v-for="c in categories"
                            :key="c.key"
                            class="px-3 py-3"
                        >
                            <div class="flex justify-center gap-4">
                                <label
                                    class="flex flex-col items-center gap-1 text-[11px] text-muted-foreground"
                                >
                                    <Checkbox
                                        :model-value="
                                            form.roles[r.key][c.key].in_app
                                        "
                                        :aria-label="`${r.label}: ${c.label} in-app`"
                                        @update:model-value="
                                            (v) =>
                                                (form.roles[r.key][
                                                    c.key
                                                ].in_app = v === true)
                                        "
                                    />
                                    In-app
                                </label>
                                <label
                                    class="flex flex-col items-center gap-1 text-[11px] text-muted-foreground"
                                >
                                    <Checkbox
                                        :model-value="
                                            form.roles[r.key][c.key].email
                                        "
                                        :aria-label="`${r.label}: ${c.label} email`"
                                        @update:model-value="
                                            (v) =>
                                                (form.roles[r.key][
                                                    c.key
                                                ].email = v === true)
                                        "
                                    />
                                    Email
                                </label>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PanelCard>

    <PanelCard
        v-for="group in byCategory"
        :key="group.key"
        class="mb-4"
        :title="`${group.label} notifications`"
        :description="`Placeholders for the email wording: ${placeholders.join(' ')}. Leave blank to use the notification's own text.`"
    >
        <div class="divide-y">
            <div
                v-for="t in group.items"
                :key="t.type"
                class="grid gap-3 py-4 first:pt-0 last:pb-0 lg:grid-cols-[220px_1fr]"
            >
                <div>
                    <p class="text-[13px] font-semibold">{{ t.label }}</p>
                    <p v-if="t.mandatory" class="text-xs text-muted-foreground">
                        Mandatory: always shown in-app.
                    </p>
                    <div class="mt-2 flex flex-col gap-1.5 text-[13px]">
                        <label class="flex items-center gap-2">
                            <Checkbox
                                :model-value="form.templates[t.type].in_app"
                                :disabled="t.mandatory"
                                @update:model-value="
                                    (v) =>
                                        (form.templates[t.type].in_app =
                                            v === true)
                                "
                            />
                            In-app enabled
                        </label>
                        <label class="flex items-center gap-2">
                            <Checkbox
                                :model-value="form.templates[t.type].email"
                                @update:model-value="
                                    (v) =>
                                        (form.templates[t.type].email =
                                            v === true)
                                "
                            />
                            Email allowed
                        </label>
                    </div>
                </div>
                <div class="grid gap-3">
                    <FormField label="Email subject">
                        <Input
                            v-model="form.templates[t.type].email_subject"
                            maxlength="150"
                            placeholder="{title}"
                        />
                    </FormField>
                    <FormField label="Email message">
                        <TextArea
                            v-model="form.templates[t.type].email_body"
                            :rows="3"
                            :maxlength="3000"
                            placeholder="{body}"
                        />
                    </FormField>
                </div>
            </div>
        </div>
    </PanelCard>

    <div class="flex justify-end">
        <Button :disabled="form.processing" @click="save">Save changes</Button>
    </div>
</template>
