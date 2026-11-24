<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { autoReplies } from '@/routes/admin/chat';
import { update } from '@/routes/admin/chat/auto-replies';

/** Live Chat → Auto-replies: the predefined answers the website chat gives visitors. */
type Rule = {
    id: string;
    name: string;
    keywords: string[];
    reply: string;
    active: boolean;
};

const props = defineProps<{
    settings: {
        enabled: boolean;
        assistant_name: string;
        fallback: string;
        rules: Rule[];
    };
    limits: { rules: number; keywords: number; reply: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Auto-replies', href: autoReplies() }],
    },
});

// Keywords are edited as one comma-separated line and split on save.
type Row = Omit<Rule, 'keywords'> & { keywords: string; key: number };

let nextKey = 0;
const toRow = (r: Rule): Row => ({
    ...r,
    keywords: r.keywords.join(', '),
    key: nextKey++,
});

const form = reactive({
    enabled: props.settings.enabled,
    assistant_name: props.settings.assistant_name,
    fallback: props.settings.fallback,
    rules: props.settings.rules.map(toRow),
});
const saving = ref(false);
const errors = ref<Record<string, string>>({});

function add(): void {
    if (form.rules.length < props.limits.rules) {
        form.rules.push(
            toRow({ id: '', name: '', keywords: [], reply: '', active: true }),
        );
    }
}

function remove(i: number): void {
    form.rules.splice(i, 1);
}

function move(i: number, by: -1 | 1): void {
    const j = i + by;

    if (j >= 0 && j < form.rules.length) {
        [form.rules[i], form.rules[j]] = [form.rules[j], form.rules[i]];
    }
}

function save(): void {
    router.put(
        update().url,
        {
            enabled: form.enabled,
            assistant_name: form.assistant_name,
            fallback: form.fallback,
            rules: form.rules.map((r) => ({
                id: r.id,
                name: r.name,
                active: r.active,
                reply: r.reply,
                keywords: r.keywords
                    .split(/[,\n]/)
                    .map((k) => k.trim())
                    .filter(Boolean),
            })),
        },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onSuccess: () => (errors.value = {}),
            onError: (e) => (errors.value = e),
        },
    );
}
</script>

<template>
    <Head title="Auto-replies" />

    <PageHeader
        eyebrow="Live Chat"
        title="Auto-replies"
        description="Ready-made answers the website chat gives visitors while they wait for a person. Replies are always shown as automated."
    />

    <form class="flex max-w-3xl flex-col gap-4" @submit.prevent="save">
        <PanelCard
            title="General"
            description="Automatic replies only run in website chats, and stop as soon as a team member replies."
        >
            <div class="flex flex-col gap-4">
                <label class="flex items-center gap-2 text-[13px]">
                    <Checkbox
                        :model-value="form.enabled"
                        @update:model-value="(v) => (form.enabled = v === true)"
                    />
                    Reply automatically in website chats
                </label>
                <FormField
                    label="Assistant name"
                    :error="errors.assistant_name"
                    hint="Shown on automatic replies, next to an “Automated” badge."
                >
                    <template #default="{ id }">
                        <Input
                            :id="id"
                            v-model="form.assistant_name"
                            maxlength="40"
                        />
                    </template>
                </FormField>
                <FormField
                    label="When nothing matches"
                    :error="errors.fallback"
                    hint="Sent once, when no reply below fits the message."
                >
                    <template #default="{ id }">
                        <TextArea
                            :id="id"
                            v-model="form.fallback"
                            :rows="2"
                            :maxlength="limits.reply"
                        />
                    </template>
                </FormField>
            </div>
        </PanelCard>

        <PanelCard
            title="Replies"
            description="The first reply with the best keyword match is sent. You can use {site_name}, {phone} and {email} in a reply."
        >
            <div class="flex flex-col gap-4">
                <p
                    v-if="!form.rules.length"
                    class="text-[13px] text-muted-foreground"
                >
                    No replies yet. Visitors will only get the message above.
                </p>

                <fieldset
                    v-for="(rule, i) in form.rules"
                    :key="rule.key"
                    class="flex flex-col gap-3 rounded-lg border p-4"
                    :class="rule.active ? '' : 'opacity-60'"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Input
                            v-model="rule.name"
                            class="min-w-0 flex-1"
                            maxlength="80"
                            placeholder="Name, e.g. Fees"
                            :aria-label="`Name of reply ${i + 1}`"
                            :aria-invalid="!!errors[`rules.${i}.name`]"
                        />
                        <label class="flex items-center gap-1.5 text-[12px]">
                            <Checkbox
                                :model-value="rule.active"
                                @update:model-value="
                                    (v) => (rule.active = v === true)
                                "
                            />
                            Active
                        </label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="i === 0"
                            aria-label="Move up"
                            @click="move(i, -1)"
                            ><AppIcon name="arrow_upward" :size="16"
                        /></Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="i === form.rules.length - 1"
                            aria-label="Move down"
                            @click="move(i, 1)"
                            ><AppIcon name="arrow_downward" :size="16"
                        /></Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Delete reply"
                            @click="remove(i)"
                            ><AppIcon name="delete" :size="16"
                        /></Button>
                    </div>
                    <p
                        v-if="errors[`rules.${i}.name`]"
                        class="text-xs text-danger"
                    >
                        {{ errors[`rules.${i}.name`] }}
                    </p>

                    <FormField
                        label="Keywords"
                        :error="
                            errors[`rules.${i}.keywords`] ??
                            errors[`rules.${i}.keywords.0`]
                        "
                        hint="Separate with commas. A keyword matches whole words or phrases, such as “student visa”."
                    >
                        <template #default="{ id }">
                            <TextArea
                                :id="id"
                                v-model="rule.keywords"
                                :rows="2"
                                placeholder="fees, cost, price"
                            />
                        </template>
                    </FormField>
                    <FormField
                        label="Reply"
                        :error="errors[`rules.${i}.reply`]"
                    >
                        <template #default="{ id }">
                            <TextArea
                                :id="id"
                                v-model="rule.reply"
                                :rows="3"
                                :maxlength="limits.reply"
                            />
                        </template>
                    </FormField>
                </fieldset>

                <div>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.rules.length >= limits.rules"
                        @click="add"
                        ><AppIcon name="add" :size="16" /> Add reply</Button
                    >
                </div>
            </div>
        </PanelCard>

        <div>
            <Button type="submit" :disabled="saving">Save auto-replies</Button>
        </div>
    </form>
</template>
