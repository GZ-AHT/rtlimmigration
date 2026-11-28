<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { clone } from '@/lib/clone';
import { availability } from '@/routes/admin/consultations';
import { update } from '@/routes/admin/consultations/availability';
import { update as updateMeeting } from '@/routes/admin/consultations/meeting-default';

/** The schedule customers can book from. Only what is set here is ever shown to them. */
type Schedule = {
    enabled: boolean;
    timezone: string;
    duration: number;
    buffer: number;
    min_notice_hours: number;
    max_days_ahead: number;
    auto_confirm: boolean;
    weekly: Record<string, { enabled: boolean; start: string; end: string }>;
    breaks: { start: string; end: string }[];
    blocked: {
        date: string;
        from: string | null;
        to: string | null;
        reason: string;
    }[];
};

const props = defineProps<{
    schedule: Schedule;
    days: Record<string, string>;
    durations: number[];
    timezones: string[];
    defaultMeeting: { provider: string; url: string } | null;
    providers: Record<string, string>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Availability', href: availability() }] },
});

const form = reactive(clone(props.schedule) as Schedule);
const saving = ref(false);
const errors = ref<Record<string, string>>({});

const providerOptions = Object.entries(props.providers).map(
    ([value, label]) => ({ value, label }),
);
const meetingForm = useForm({
    meeting_provider: props.defaultMeeting?.provider ?? 'google_meet',
    meeting_url: props.defaultMeeting?.url ?? '',
});
const meetingExample = 'https://meet.google.com/abc-defg-hij';
const saveMeeting = () =>
    meetingForm
        .transform((data) => ({
            ...data,
            meeting_url: data.meeting_url || null,
        }))
        .put(updateMeeting().url, { preserveScroll: true });

const durationOptions = props.durations.map((d) => ({
    value: String(d),
    label: `${d} minutes`,
}));
const zoneOptions = props.timezones.map((z) => ({ value: z, label: z }));
const duration = ref(String(form.duration));

const addBreak = () => form.breaks.push({ start: '13:00', end: '14:00' });
const addBlocked = () =>
    form.blocked.push({ date: '', from: null, to: null, reason: '' });

function save(): void {
    router.put(
        update().url,
        {
            ...form,
            duration: Number(duration.value),
            buffer: Number(form.buffer),
            min_notice_hours: Number(form.min_notice_hours),
            max_days_ahead: Number(form.max_days_ahead),
            blocked: form.blocked.map((b) => ({
                ...b,
                from: b.from || null,
                to: b.to || null,
            })),
        } as unknown as Record<string, never>,
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
    <Head title="Consultation availability" />

    <PageHeader
        eyebrow="Video consultations"
        title="Availability"
        description="Set when customers can book a video consultation. Changes apply to the website straight away; existing bookings are not moved."
    />

    <form class="mb-4 max-w-3xl" @submit.prevent="saveMeeting">
        <PanelCard
            title="Default meeting link"
            description="Used for every video consultation unless another link is entered on the booking. Requests waiting for approval follow changes made here; confirmed bookings keep their link."
        >
            <div class="grid gap-3 sm:grid-cols-[170px_1fr]">
                <FormField
                    label="Platform"
                    :error="meetingForm.errors.meeting_provider"
                >
                    <template #default="{ id }"
                        ><SelectInput
                            :id="id"
                            v-model="meetingForm.meeting_provider"
                            :options="providerOptions"
                    /></template>
                </FormField>
                <FormField
                    label="Meeting link (https)"
                    hint="Leave empty to use no default link."
                    :error="meetingForm.errors.meeting_url"
                >
                    <template #default="{ id }"
                        ><Input
                            :id="id"
                            v-model="meetingForm.meeting_url"
                            type="url"
                            :placeholder="meetingExample"
                    /></template>
                </FormField>
            </div>
            <Button
                type="submit"
                class="mt-3"
                :disabled="meetingForm.processing"
                >Save meeting link</Button
            >
        </PanelCard>
    </form>

    <form class="flex max-w-3xl flex-col gap-4" @submit.prevent="save">
        <PanelCard title="Booking rules">
            <div class="grid gap-4 sm:grid-cols-2">
                <label
                    class="flex items-center gap-2 text-[13px] sm:col-span-2"
                >
                    <Checkbox
                        :model-value="form.enabled"
                        @update:model-value="(v) => (form.enabled = v === true)"
                    />
                    Accept video consultation bookings on the website
                </label>
                <FormField
                    label="Working hours are in this timezone"
                    :error="errors.timezone"
                >
                    <template #default="{ id }"
                        ><SelectInput
                            :id="id"
                            v-model="form.timezone"
                            :options="zoneOptions"
                    /></template>
                </FormField>
                <FormField label="Appointment length" :error="errors.duration">
                    <template #default="{ id }"
                        ><SelectInput
                            :id="id"
                            v-model="duration"
                            :options="durationOptions"
                    /></template>
                </FormField>
                <FormField
                    label="Buffer between appointments (minutes)"
                    :error="errors.buffer"
                >
                    <template #default="{ id }"
                        ><Input
                            :id="id"
                            v-model="form.buffer"
                            type="number"
                            min="0"
                            max="120"
                    /></template>
                </FormField>
                <FormField
                    label="Minimum notice (hours)"
                    hint="How far ahead a booking must be made."
                    :error="errors.min_notice_hours"
                >
                    <template #default="{ id }"
                        ><Input
                            :id="id"
                            v-model="form.min_notice_hours"
                            type="number"
                            min="0"
                            max="720"
                    /></template>
                </FormField>
                <FormField
                    label="Booking window (days ahead)"
                    hint="How far in the future customers can book."
                    :error="errors.max_days_ahead"
                >
                    <template #default="{ id }"
                        ><Input
                            :id="id"
                            v-model="form.max_days_ahead"
                            type="number"
                            min="1"
                            max="365"
                    /></template>
                </FormField>
                <label class="flex items-start gap-2 text-[13px] sm:col-span-2">
                    <Checkbox
                        class="mt-0.5"
                        :model-value="form.auto_confirm"
                        @update:model-value="
                            (v) => (form.auto_confirm = v === true)
                        "
                    />
                    <span
                        >Confirm bookings automatically<span
                            class="block text-[12px] text-muted-foreground"
                            >Off by default: every request waits for you to
                            approve it.</span
                        ></span
                    >
                </label>
            </div>
        </PanelCard>

        <PanelCard
            title="Weekly hours"
            description="Days and hours customers can book."
        >
            <div class="flex flex-col gap-2.5">
                <div
                    v-for="(label, key) in days"
                    :key="key"
                    class="grid grid-cols-[110px_1fr] items-center gap-3 sm:grid-cols-[130px_120px_120px_1fr]"
                >
                    <label
                        class="flex items-center gap-2 text-[13px] font-semibold"
                    >
                        <Checkbox
                            :model-value="form.weekly[key].enabled"
                            @update:model-value="
                                (v) => (form.weekly[key].enabled = v === true)
                            "
                        />
                        {{ label }}
                    </label>
                    <Input
                        v-model="form.weekly[key].start"
                        type="time"
                        :disabled="!form.weekly[key].enabled"
                        :aria-label="`${label} opens`"
                    />
                    <Input
                        v-model="form.weekly[key].end"
                        type="time"
                        :disabled="!form.weekly[key].enabled"
                        :aria-label="`${label} closes`"
                    />
                    <p
                        v-if="errors[`weekly.${key}.end`]"
                        class="col-span-2 text-xs text-danger sm:col-span-1"
                        role="alert"
                    >
                        {{ errors[`weekly.${key}.end`] }}
                    </p>
                </div>
            </div>
        </PanelCard>

        <PanelCard
            title="Daily breaks"
            description="No appointments are offered during these times, every day."
        >
            <div class="flex flex-col gap-2.5">
                <div
                    v-for="(b, i) in form.breaks"
                    :key="i"
                    class="flex flex-wrap items-center gap-2"
                >
                    <Input
                        v-model="b.start"
                        type="time"
                        class="w-32"
                        :aria-label="`Break ${i + 1} starts`"
                    />
                    <span class="text-muted-foreground">to</span>
                    <Input
                        v-model="b.end"
                        type="time"
                        class="w-32"
                        :aria-label="`Break ${i + 1} ends`"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Remove break"
                        @click="form.breaks.splice(i, 1)"
                        ><AppIcon name="delete" :size="16"
                    /></Button>
                    <p
                        v-if="errors[`breaks.${i}.end`]"
                        class="w-full text-xs text-danger"
                        role="alert"
                    >
                        {{ errors[`breaks.${i}.end`] }}
                    </p>
                </div>
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addBreak"
                        ><AppIcon name="add" :size="16" /> Add break</Button
                    >
                </div>
            </div>
        </PanelCard>

        <PanelCard
            title="Unavailable dates and times"
            description="Block a whole day (holiday) or just part of it. Leave the times empty to block the whole day."
        >
            <div class="flex flex-col gap-3">
                <div
                    v-for="(b, i) in form.blocked"
                    :key="i"
                    class="flex flex-wrap items-center gap-2 rounded-lg border p-3"
                >
                    <Input
                        v-model="b.date"
                        type="date"
                        class="w-44"
                        :aria-label="`Blocked date ${i + 1}`"
                    />
                    <Input
                        :model-value="b.from ?? ''"
                        type="time"
                        @update:model-value="
                            (v) => (b.from = String(v) || null)
                        "
                        class="w-32"
                        :aria-label="`From ${i + 1}`"
                    />
                    <span class="text-muted-foreground">to</span>
                    <Input
                        :model-value="b.to ?? ''"
                        type="time"
                        @update:model-value="(v) => (b.to = String(v) || null)"
                        class="w-32"
                        :aria-label="`To ${i + 1}`"
                    />
                    <Input
                        v-model="b.reason"
                        class="min-w-0 flex-1"
                        maxlength="120"
                        placeholder="Reason (private)"
                        :aria-label="`Reason ${i + 1}`"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Remove"
                        @click="form.blocked.splice(i, 1)"
                        ><AppIcon name="delete" :size="16"
                    /></Button>
                    <p
                        v-if="
                            errors[`blocked.${i}.date`] ||
                            errors[`blocked.${i}.to`]
                        "
                        class="w-full text-xs text-danger"
                        role="alert"
                    >
                        {{
                            errors[`blocked.${i}.date`] ??
                            errors[`blocked.${i}.to`]
                        }}
                    </p>
                </div>
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addBlocked"
                        ><AppIcon name="add" :size="16" /> Block a date or
                        time</Button
                    >
                </div>
            </div>
        </PanelCard>

        <div>
            <Button type="submit" :disabled="saving">Save availability</Button>
        </div>
    </form>
</template>
