<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import PhoneInput from '@/components/chat/PhoneInput.vue';
import type { Country } from '@/components/chat/PhoneInput.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { headers } from '@/composables/useMessageThread';
import { consultationRequests } from '@/composables/useConsultationBooking';
import { book, code, availability, verify } from '@/routes/consultation';

/**
 * "Book a video consultation" popup: verify an email, give your details, pick
 * an available time, confirm. Every check and every available time comes from
 * the server (admin schedule); the browser only shows what it is given and
 * displays times in the visitor's own timezone.
 */
type Step = 'email' | 'code' | 'details' | 'time' | 'confirm' | 'done';
const STEPS: { key: Step; label: string }[] = [
    { key: 'email', label: 'Email' },
    { key: 'details', label: 'Details' },
    { key: 'time', label: 'Date & time' },
    { key: 'confirm', label: 'Confirm' },
];
const ORDER: Step[] = ['email', 'code', 'details', 'time', 'confirm', 'done'];

type Booking = {
    reference: string;
    name: string;
    email: string;
    subject: string;
    starts_at: string;
    duration_minutes: number;
    timezone: string;
    status: string;
    status_label: string;
    type: string;
};

const open = ref(false);
const step = ref<Step>('email');
const direction = ref<'fwd' | 'back'>('fwd');
const busy = ref(false);
const error = ref<string | null>(null);
const errors = ref<Record<string, string>>({});

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
const tzOffset = computed(
    () =>
        new Intl.DateTimeFormat('en', { timeZoneName: 'shortOffset' })
            .formatToParts(new Date())
            .find((p) => p.type === 'timeZoneName')?.value ?? '',
);

const email = ref('');
const otp = ref('');
const resendIn = ref(0);
let resendTimer: ReturnType<typeof setInterval> | undefined;

const subjects = ref<string[]>([]);
const countries = ref<Country[]>([]);
const duration = ref(30);
const form = reactive({
    name: '',
    phone: '',
    country: 'BD',
    subject: '',
    message: '',
});

const slots = ref<string[]>([]);
const loadingSlots = ref(false);
const month = ref(startOfMonth(new Date()));
const selectedDay = ref<string | null>(null);
const selectedSlot = ref<string | null>(null);
const maxDays = ref(60);
const booking = ref<Booking | null>(null);

function startOfMonth(d: Date): Date {
    return new Date(d.getFullYear(), d.getMonth(), 1);
}

const key = (d: Date): string =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const dayKeyOf = (iso: string): string => key(new Date(iso));
const todayKey = computed(() => key(new Date()));

const fmtDate = (iso: string): string =>
    new Intl.DateTimeFormat('en', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(iso));
const fmtTime = (iso: string): string =>
    new Intl.DateTimeFormat('en', {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(iso));
const fmtRange = (iso: string): string =>
    `${fmtTime(iso)} – ${fmtTime(new Date(new Date(iso).getTime() + duration.value * 60000).toISOString())}`;

const slotsByDay = computed(() => {
    const map = new Map<string, string[]>();

    for (const s of slots.value) {
        const k = dayKeyOf(s);
        map.set(k, [...(map.get(k) ?? []), s]);
    }

    return map;
});

const monthLabel = computed(() =>
    new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric' }).format(
        month.value,
    ),
);

/** Six weeks of cells, Sunday first. */
const cells = computed(() => {
    const first = month.value;
    const start = new Date(
        first.getFullYear(),
        first.getMonth(),
        1 - first.getDay(),
    );

    return Array.from({ length: 42 }, (_, i) => {
        const d = new Date(
            start.getFullYear(),
            start.getMonth(),
            start.getDate() + i,
        );
        const k = key(d);

        return {
            k,
            day: d.getDate(),
            inMonth: d.getMonth() === first.getMonth(),
            available: slotsByDay.value.has(k),
            past: k < todayKey.value,
        };
    });
});

const canGoBack = computed(() => month.value > startOfMonth(new Date()));
const canGoForward = computed(() => {
    const limit = new Date();
    limit.setDate(limit.getDate() + maxDays.value);

    return (
        startOfMonth(
            new Date(month.value.getFullYear(), month.value.getMonth() + 1, 1),
        ) <= limit
    );
});

const daySlots = computed(() =>
    selectedDay.value ? (slotsByDay.value.get(selectedDay.value) ?? []) : [],
);
const hasAnyThisMonth = computed(() =>
    cells.value.some((c) => c.inMonth && c.available),
);

/* ------------------------------ transport ------------------------------ */

async function call<T>(
    url: string,
    method: 'GET' | 'POST',
    body?: unknown,
): Promise<{
    ok: boolean;
    status: number;
    data: T & { message?: string; errors?: Record<string, string[]> };
}> {
    const response = await fetch(url, {
        method,
        headers: {
            ...headers(),
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        credentials: 'same-origin',
        ...(body ? { body: JSON.stringify(body) } : {}),
    });
    const data = (await response.json().catch(() => ({}))) as never;

    return { ok: response.ok, status: response.status, data };
}

function fail(r: {
    status: number;
    data: { message?: string; errors?: Record<string, string[]> };
}): void {
    errors.value = Object.fromEntries(
        Object.entries(r.data.errors ?? {}).map(([k, v]) => [k, v[0]]),
    );
    error.value =
        r.status === 404
            ? 'Video consultations are not available right now. Please contact us instead.'
            : r.status === 429
              ? 'Too many attempts. Please wait a minute and try again.'
              : r.status === 419
                ? 'This page expired. Reload it and try again.'
                : Object.keys(errors.value).length
                  ? null
                  : (r.data.message ??
                    'Something went wrong. Please try again.');
}

async function run(task: () => Promise<void>): Promise<void> {
    busy.value = true;
    error.value = null;
    errors.value = {};

    try {
        await task();
    } catch {
        error.value = 'Could not reach us. Check your connection and retry.';
    } finally {
        busy.value = false;
    }
}

function go(to: Step): void {
    direction.value =
        ORDER.indexOf(to) >= ORDER.indexOf(step.value) ? 'fwd' : 'back';
    error.value = null;
    errors.value = {};
    step.value = to;
}

/* ------------------------------ steps ------------------------------ */

function startResendTimer(seconds: number): void {
    clearInterval(resendTimer);
    resendIn.value = seconds;
    resendTimer = setInterval(() => {
        resendIn.value = Math.max(0, resendIn.value - 1);

        if (resendIn.value === 0) {
            clearInterval(resendTimer);
        }
    }, 1000);
}

async function sendCode(): Promise<void> {
    if (!email.value.trim()) {
        errors.value = { email: 'Enter your email address.' };

        return;
    }

    await run(async () => {
        const r = await call<{ resend_in: number }>(code.url(), 'POST', {
            email: email.value.trim(),
        });

        if (!r.ok) {
            return fail(r);
        }

        startResendTimer(r.data.resend_in);
        otp.value = '';
        go('code');
    });
}

async function verifyCode(): Promise<void> {
    if (otp.value.trim().length < 6) {
        errors.value = { code: 'Enter the 6-digit code from your email.' };

        return;
    }

    await run(async () => {
        const r = await call<{
            email: string;
            name: string | null;
            phone: string | null;
            country: string;
            subjects: string[];
            countries: Country[];
            duration: number;
        }>(verify.url(), 'POST', {
            email: email.value.trim(),
            code: otp.value.trim(),
        });

        if (!r.ok) {
            return fail(r);
        }

        email.value = r.data.email;
        subjects.value = r.data.subjects;
        countries.value = r.data.countries;
        duration.value = r.data.duration;
        form.name = form.name || r.data.name || '';
        form.phone = form.phone || r.data.phone || '';
        form.country = r.data.country;
        go('details');
    });
}

function detailsNext(): void {
    errors.value = {
        ...(form.name.trim().length >= 2
            ? {}
            : { name: 'Enter your full name.' }),
        ...(form.phone.trim() ? {} : { phone: 'Enter your phone number.' }),
        ...(form.subject
            ? {}
            : { subject: 'Choose what the consultation is about.' }),
    };

    if (Object.keys(errors.value).length === 0) {
        go('time');
        void loadSlots();
    }
}

async function loadSlots(): Promise<void> {
    const first = month.value;
    const from = new Date(
        first.getFullYear(),
        first.getMonth(),
        1 - first.getDay(),
    );
    const to = new Date(
        from.getFullYear(),
        from.getMonth(),
        from.getDate() + 42,
    );
    loadingSlots.value = true;

    try {
        const url = new URL(availability.url(), window.location.origin);
        url.searchParams.set('from', from.toISOString());
        url.searchParams.set('to', to.toISOString());
        const r = await call<{
            slots: string[];
            duration: number;
            max_days_ahead: number;
        }>(url.toString(), 'GET');

        if (!r.ok) {
            return fail(r);
        }

        slots.value = r.data.slots;
        duration.value = r.data.duration;
        maxDays.value = r.data.max_days_ahead;

        // Pick the first free day of a month nobody has chosen yet.
        if (!selectedDay.value || !slotsByDay.value.has(selectedDay.value)) {
            selectedDay.value = null;
            selectedSlot.value = null;
        }
    } catch {
        error.value =
            'Could not load the available times. Check your connection and retry.';
    } finally {
        loadingSlots.value = false;
    }
}

function changeMonth(by: number): void {
    month.value = new Date(
        month.value.getFullYear(),
        month.value.getMonth() + by,
        1,
    );
    void loadSlots();
}

function pickDay(k: string): void {
    selectedDay.value = k;
    selectedSlot.value = null;
}

function timeNext(): void {
    if (!selectedSlot.value) {
        error.value = 'Choose a date and a time.';

        return;
    }

    go('confirm');
}

async function confirmBooking(): Promise<void> {
    await run(async () => {
        const r = await call<{ booking: Booking }>(book.url(), 'POST', {
            name: form.name.trim(),
            phone: form.phone.trim(),
            country: form.country,
            subject: form.subject,
            message: form.message.trim() || null,
            starts_at: selectedSlot.value,
            timezone,
        });

        if (r.ok) {
            booking.value = r.data.booking;
            go('done');

            return;
        }

        fail(r);

        if (errors.value.starts_at) {
            // Someone else took it: back to the calendar with fresh times.
            const message = errors.value.starts_at;
            selectedSlot.value = null;
            go('time');
            error.value = message;
            await loadSlots();
        } else if (
            errors.value.phone ||
            errors.value.name ||
            errors.value.subject
        ) {
            go('details');
            fail(r);
        } else if (errors.value.email) {
            go('email');
            fail(r);
        }
    });
}

/* ------------------------------ open / close ------------------------------ */

function reset(): void {
    step.value = 'email';
    direction.value = 'fwd';
    email.value = '';
    otp.value = '';
    form.name = '';
    form.phone = '';
    form.subject = '';
    form.message = '';
    slots.value = [];
    selectedDay.value = null;
    selectedSlot.value = null;
    month.value = startOfMonth(new Date());
    booking.value = null;
    error.value = null;
    errors.value = {};
}

watch(consultationRequests, () => {
    if (step.value === 'done') {
        reset();
    }

    open.value = true;
});

onBeforeUnmount(() => clearInterval(resendTimer));

const progress = computed(() =>
    step.value === 'code' ? 0 : STEPS.findIndex((s) => s.key === step.value),
);

const field =
    'h-[50px] w-full rounded-[10px] border border-site-field-border bg-site-field px-[13px] text-sm text-site-ink outline-none placeholder:text-site-muted focus:border-site-red focus:ring-3 focus:ring-site-red/15 aria-invalid:border-site-red';
const label = 'mb-2 block text-sm leading-[19.5px] font-medium text-site-ink';
const primary =
    'inline-flex h-[50px] items-center justify-center gap-2 rounded-[12px] bg-site-red px-6 text-sm font-semibold text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none disabled:opacity-60';
const secondary =
    'inline-flex h-[50px] items-center justify-center gap-2 rounded-[12px] border border-site-line px-5 text-sm font-semibold text-site-ink transition-colors hover:bg-site-sand focus-visible:ring-4 focus-visible:ring-site-red/20 focus-visible:outline-none';
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-dvh gap-0 overflow-y-auto border-site-line bg-white p-0 font-site text-site-ink max-sm:h-dvh max-sm:max-w-none max-sm:rounded-none sm:max-h-[92vh] sm:max-w-[640px] sm:rounded-[20px]"
        >
            <div class="px-5 pt-6 pb-2 sm:px-8 sm:pt-8">
                <p
                    class="text-[11px] font-semibold tracking-wide text-site-red uppercase"
                >
                    Video consultation
                </p>
                <DialogTitle
                    class="mt-1 text-[22px] leading-tight font-bold sm:text-[26px]"
                >
                    Book a video consultation
                </DialogTitle>
                <DialogDescription class="mt-1 text-sm text-site-muted">
                    Talk to an adviser face to face, online.
                </DialogDescription>

                <!-- Progress -->
                <ol
                    v-if="step !== 'done'"
                    class="mt-5 grid grid-cols-4 gap-2"
                    aria-label="Booking steps"
                >
                    <li
                        v-for="(s, i) in STEPS"
                        :key="s.key"
                        :aria-current="i === progress ? 'step' : undefined"
                    >
                        <span
                            class="block h-1.5 rounded-full transition-colors duration-300"
                            :class="
                                i <= progress ? 'bg-site-red' : 'bg-site-line'
                            "
                        />
                        <span
                            class="mt-1.5 block truncate text-[11px] font-medium"
                            :class="
                                i === progress
                                    ? 'text-site-ink'
                                    : 'text-site-muted'
                            "
                            >{{ i + 1 }}. {{ s.label }}</span
                        >
                    </li>
                </ol>
            </div>

            <div class="relative px-5 pt-4 pb-6 sm:px-8 sm:pb-8">
                <Transition :name="`consult-${direction}`" mode="out-in">
                    <!-- 1. Email -->
                    <form
                        v-if="step === 'email'"
                        key="email"
                        class="flex flex-col gap-4"
                        novalidate
                        @submit.prevent="sendCode"
                    >
                        <p class="text-sm text-site-muted">
                            First, confirm your email address. We will send a
                            6-digit code.
                        </p>
                        <div>
                            <label for="cb-email" :class="label"
                                >Email address *</label
                            >
                            <input
                                id="cb-email"
                                v-model="email"
                                type="email"
                                autocomplete="email"
                                inputmode="email"
                                maxlength="255"
                                :class="field"
                                :aria-invalid="!!errors.email || undefined"
                                placeholder="you@example.com"
                            />
                            <p
                                v-if="errors.email"
                                class="mt-1.5 text-xs text-site-red"
                                role="alert"
                            >
                                {{ errors.email }}
                            </p>
                        </div>
                        <p
                            v-if="error"
                            class="text-sm text-site-red"
                            role="alert"
                        >
                            {{ error }}
                        </p>
                        <button type="submit" :class="primary" :disabled="busy">
                            {{ busy ? 'Sending…' : 'Send verification code' }}
                        </button>
                    </form>

                    <!-- 1b. Code -->
                    <form
                        v-else-if="step === 'code'"
                        key="code"
                        class="flex flex-col gap-4"
                        novalidate
                        @submit.prevent="verifyCode"
                    >
                        <p class="text-sm text-site-muted">
                            We sent a 6-digit code to
                            <b class="text-site-ink">{{ email }}</b
                            >. It expires in 5 minutes.
                        </p>
                        <div>
                            <label for="cb-code" :class="label"
                                >Verification code *</label
                            >
                            <input
                                id="cb-code"
                                v-model="otp"
                                type="text"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                :class="[
                                    field,
                                    'text-center text-2xl font-semibold tracking-[0.5em]',
                                ]"
                                :aria-invalid="!!errors.code || undefined"
                                placeholder="000000"
                            />
                            <p
                                v-if="errors.code"
                                class="mt-1.5 text-xs text-site-red"
                                role="alert"
                            >
                                {{ errors.code }}
                            </p>
                        </div>
                        <p
                            v-if="error"
                            class="text-sm text-site-red"
                            role="alert"
                        >
                            {{ error }}
                        </p>
                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                :class="secondary"
                                @click="go('email')"
                            >
                                Change email
                            </button>
                            <button
                                type="submit"
                                :class="[primary, 'flex-1']"
                                :disabled="busy"
                            >
                                {{ busy ? 'Checking…' : 'Verify and continue' }}
                            </button>
                        </div>
                        <p class="text-center text-sm text-site-muted">
                            Did not get it?
                            <button
                                type="button"
                                class="font-semibold text-site-red underline disabled:text-site-muted disabled:no-underline"
                                :disabled="resendIn > 0 || busy"
                                @click="sendCode"
                            >
                                {{
                                    resendIn > 0
                                        ? `Resend in ${resendIn}s`
                                        : 'Resend code'
                                }}
                            </button>
                        </p>
                    </form>

                    <!-- 2. Details -->
                    <form
                        v-else-if="step === 'details'"
                        key="details"
                        class="grid gap-4 sm:grid-cols-2"
                        novalidate
                        @submit.prevent="detailsNext"
                    >
                        <div class="sm:col-span-2">
                            <label for="cb-name" :class="label"
                                >Full name *</label
                            >
                            <input
                                id="cb-name"
                                v-model="form.name"
                                type="text"
                                autocomplete="name"
                                maxlength="120"
                                :class="field"
                                :aria-invalid="!!errors.name || undefined"
                            />
                            <p
                                v-if="errors.name"
                                class="mt-1.5 text-xs text-site-red"
                                role="alert"
                            >
                                {{ errors.name }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="cb-email-ro" :class="label"
                                >Email (verified)</label
                            >
                            <div class="relative">
                                <input
                                    id="cb-email-ro"
                                    :value="email"
                                    type="email"
                                    readonly
                                    :class="[
                                        field,
                                        'bg-site-sand pr-10 text-site-muted',
                                    ]"
                                />
                                <AppIcon
                                    name="verified"
                                    :size="20"
                                    class="absolute top-1/2 right-3 -translate-y-1/2 text-success"
                                />
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="cb-phone" :class="label"
                                >Phone number *</label
                            >
                            <PhoneInput
                                id="cb-phone"
                                v-model:country="form.country"
                                v-model:phone="form.phone"
                                :countries="countries"
                                :field-class="field"
                                :invalid="!!errors.phone"
                            />
                            <p
                                v-if="errors.phone"
                                class="mt-1.5 text-xs text-site-red"
                                role="alert"
                            >
                                {{ errors.phone }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="cb-subject" :class="label"
                                >Consultation subject *</label
                            >
                            <select
                                id="cb-subject"
                                v-model="form.subject"
                                :class="[
                                    field,
                                    !form.subject && 'text-site-muted',
                                ]"
                                :aria-invalid="!!errors.subject || undefined"
                            >
                                <option value="">Choose a subject</option>
                                <option
                                    v-for="s in subjects"
                                    :key="s"
                                    :value="s"
                                >
                                    {{ s }}
                                </option>
                            </select>
                            <p
                                v-if="errors.subject"
                                class="mt-1.5 text-xs text-site-red"
                                role="alert"
                            >
                                {{ errors.subject }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="cb-message" :class="label"
                                >Additional message
                                <span class="font-normal text-site-muted"
                                    >(optional)</span
                                ></label
                            >
                            <textarea
                                id="cb-message"
                                v-model="form.message"
                                rows="3"
                                maxlength="1000"
                                :class="[field, 'h-auto resize-y py-3']"
                            />
                        </div>
                        <p
                            v-if="error"
                            class="text-sm text-site-red sm:col-span-2"
                            role="alert"
                        >
                            {{ error }}
                        </p>
                        <div class="flex flex-wrap gap-3 sm:col-span-2">
                            <button
                                type="button"
                                :class="secondary"
                                @click="go('email')"
                            >
                                Back
                            </button>
                            <button type="submit" :class="[primary, 'flex-1']">
                                Choose date and time
                            </button>
                        </div>
                    </form>

                    <!-- 3. Date & time -->
                    <div
                        v-else-if="step === 'time'"
                        key="time"
                        class="flex flex-col gap-4"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 text-sm text-site-muted"
                        >
                            <span class="inline-flex items-center gap-1.5"
                                ><AppIcon name="schedule" :size="16" />{{
                                    duration
                                }}
                                minute video call</span
                            >
                            <span class="inline-flex items-center gap-1.5"
                                ><AppIcon name="public" :size="16" />Times in
                                your timezone:
                                <b class="text-site-ink">{{ timezone }}</b> ({{
                                    tzOffset
                                }})</span
                            >
                        </div>

                        <div class="grid gap-5 md:grid-cols-[1fr_200px]">
                            <!-- Month -->
                            <div>
                                <div
                                    class="mb-2 flex items-center justify-between"
                                >
                                    <h3 class="text-base font-semibold">
                                        {{ monthLabel }}
                                    </h3>
                                    <div class="flex gap-1">
                                        <button
                                            type="button"
                                            class="grid size-9 place-items-center rounded-full hover:bg-site-sand disabled:opacity-30"
                                            aria-label="Previous month"
                                            :disabled="
                                                !canGoBack || loadingSlots
                                            "
                                            @click="changeMonth(-1)"
                                        >
                                            <AppIcon
                                                name="chevron_left"
                                                :size="22"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            class="grid size-9 place-items-center rounded-full hover:bg-site-sand disabled:opacity-30"
                                            aria-label="Next month"
                                            :disabled="
                                                !canGoForward || loadingSlots
                                            "
                                            @click="changeMonth(1)"
                                        >
                                            <AppIcon
                                                name="chevron_right"
                                                :size="22"
                                            />
                                        </button>
                                    </div>
                                </div>
                                <div
                                    class="grid grid-cols-7 text-center text-[11px] font-semibold text-site-muted"
                                >
                                    <span
                                        v-for="d in [
                                            'S',
                                            'M',
                                            'T',
                                            'W',
                                            'T',
                                            'F',
                                            'S',
                                        ]"
                                        :key="d + Math.random()"
                                        class="py-1.5"
                                        >{{ d }}</span
                                    >
                                </div>
                                <div
                                    class="grid grid-cols-7 gap-y-1"
                                    :class="loadingSlots ? 'opacity-50' : ''"
                                    role="grid"
                                    :aria-label="monthLabel"
                                    :aria-busy="loadingSlots"
                                >
                                    <button
                                        v-for="c in cells"
                                        :key="c.k"
                                        type="button"
                                        role="gridcell"
                                        :disabled="!c.available"
                                        :aria-selected="selectedDay === c.k"
                                        :aria-label="
                                            c.available
                                                ? `${c.k}, times available`
                                                : `${c.k}, no times`
                                        "
                                        class="relative mx-auto grid size-10 place-items-center rounded-full text-sm transition-colors"
                                        :class="[
                                            !c.inMonth ? 'invisible' : '',
                                            selectedDay === c.k
                                                ? 'bg-site-red font-semibold text-white'
                                                : c.available
                                                  ? 'bg-site-red/10 font-semibold text-site-red hover:bg-site-red/20'
                                                  : 'cursor-not-allowed text-site-muted/50',
                                            c.k === todayKey &&
                                            selectedDay !== c.k
                                                ? 'ring-1 ring-site-ink/30'
                                                : '',
                                        ]"
                                        @click="pickDay(c.k)"
                                    >
                                        {{ c.day }}
                                    </button>
                                </div>
                                <p
                                    v-if="!loadingSlots && !hasAnyThisMonth"
                                    class="mt-3 rounded-[10px] bg-site-sand p-3 text-sm text-site-muted"
                                >
                                    No available times this month. Try another
                                    month.
                                </p>
                            </div>

                            <!-- Times -->
                            <div>
                                <h3 class="mb-2 text-sm font-semibold">
                                    {{
                                        selectedDay
                                            ? new Intl.DateTimeFormat('en', {
                                                  weekday: 'long',
                                                  day: 'numeric',
                                                  month: 'short',
                                              }).format(
                                                  new Date(
                                                      `${selectedDay}T12:00:00`,
                                                  ),
                                              )
                                            : 'Choose a date'
                                    }}
                                </h3>
                                <div
                                    v-if="selectedDay"
                                    class="grid max-h-[300px] grid-cols-2 gap-2 overflow-y-auto pr-1 md:grid-cols-1"
                                >
                                    <button
                                        v-for="s in daySlots"
                                        :key="s"
                                        type="button"
                                        class="h-11 rounded-[10px] border text-sm font-semibold transition-colors"
                                        :class="
                                            selectedSlot === s
                                                ? 'border-site-red bg-site-red text-white'
                                                : 'border-site-red/40 text-site-red hover:bg-site-red/10'
                                        "
                                        :aria-pressed="selectedSlot === s"
                                        @click="selectedSlot = s"
                                    >
                                        {{ fmtTime(s) }}
                                    </button>
                                </div>
                                <p v-else class="text-sm text-site-muted">
                                    Days with times are highlighted.
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="error"
                            class="text-sm text-site-red"
                            role="alert"
                        >
                            {{ error }}
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <button
                                type="button"
                                :class="secondary"
                                @click="go('details')"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                :class="[primary, 'flex-1']"
                                :disabled="!selectedSlot"
                                @click="timeNext"
                            >
                                Review booking
                            </button>
                        </div>
                    </div>

                    <!-- 4. Confirm -->
                    <div
                        v-else-if="step === 'confirm' && selectedSlot"
                        key="confirm"
                        class="flex flex-col gap-4"
                    >
                        <h3 class="text-base font-semibold">
                            Check your booking
                        </h3>
                        <dl
                            class="grid grid-cols-[110px_1fr] gap-x-3 gap-y-3 rounded-[14px] bg-site-sand p-4 text-sm"
                        >
                            <dt class="text-site-muted">Customer</dt>
                            <dd class="font-semibold">{{ form.name }}</dd>
                            <dt class="text-site-muted">Email</dt>
                            <dd class="break-all">{{ email }}</dd>
                            <dt class="text-site-muted">Date</dt>
                            <dd class="font-semibold">
                                {{ fmtDate(selectedSlot) }}
                            </dd>
                            <dt class="text-site-muted">Time</dt>
                            <dd class="font-semibold">
                                {{ fmtRange(selectedSlot) }}
                            </dd>
                            <dt class="text-site-muted">Timezone</dt>
                            <dd>{{ timezone }} ({{ tzOffset }})</dd>
                            <dt class="text-site-muted">Duration</dt>
                            <dd>{{ duration }} minutes</dd>
                            <dt class="text-site-muted">Type</dt>
                            <dd>Video Consultation</dd>
                            <dt class="text-site-muted">Subject</dt>
                            <dd>{{ form.subject }}</dd>
                        </dl>
                        <p class="text-[13px] text-site-muted">
                            Your request is not final until our team confirms
                            it. We will email you.
                        </p>
                        <p
                            v-if="error"
                            class="text-sm text-site-red"
                            role="alert"
                        >
                            {{ error }}
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <button
                                type="button"
                                :class="secondary"
                                :disabled="busy"
                                @click="go('time')"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                :class="[primary, 'flex-1']"
                                :disabled="busy"
                                @click="confirmBooking"
                            >
                                {{ busy ? 'Booking…' : 'Confirm booking' }}
                            </button>
                        </div>
                    </div>

                    <!-- Done -->
                    <div
                        v-else-if="step === 'done' && booking"
                        key="done"
                        class="flex flex-col items-center gap-3 py-2 text-center"
                    >
                        <span
                            class="grid size-16 place-items-center rounded-full bg-success-soft text-success"
                            ><AppIcon name="event_available" :size="34"
                        /></span>
                        <h3 class="text-xl font-bold">
                            {{
                                booking.status === 'confirmed'
                                    ? 'Your consultation is confirmed'
                                    : 'Request received'
                            }}
                        </h3>
                        <p class="max-w-sm text-sm text-site-muted">
                            {{
                                booking.status === 'confirmed'
                                    ? 'We emailed your confirmation.'
                                    : `We emailed a confirmation of your request to ${booking.email}. Our team will approve it and send the meeting link.`
                            }}
                        </p>
                        <dl
                            class="mt-2 grid w-full max-w-sm grid-cols-[100px_1fr] gap-x-3 gap-y-2 rounded-[14px] bg-site-sand p-4 text-left text-sm"
                        >
                            <dt class="text-site-muted">Reference</dt>
                            <dd class="font-bold tracking-wide">
                                {{ booking.reference }}
                            </dd>
                            <dt class="text-site-muted">Status</dt>
                            <dd>{{ booking.status_label }}</dd>
                            <dt class="text-site-muted">Date</dt>
                            <dd>{{ fmtDate(booking.starts_at) }}</dd>
                            <dt class="text-site-muted">Time</dt>
                            <dd>{{ fmtRange(booking.starts_at) }}</dd>
                            <dt class="text-site-muted">Timezone</dt>
                            <dd>{{ booking.timezone }}</dd>
                        </dl>
                        <button
                            type="button"
                            :class="[primary, 'mt-2 min-w-[160px]']"
                            @click="open = false"
                        >
                            Done
                        </button>
                    </div>
                </Transition>
            </div>
        </DialogContent>
    </Dialog>
</template>
