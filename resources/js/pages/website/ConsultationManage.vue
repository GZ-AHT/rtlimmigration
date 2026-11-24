<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * The page behind "Manage this appointment" in the emails (a signed link, so
 * only the person who got the email can open it). They can cancel, or ask for
 * another time; our team is told and replies by email.
 */
const props = defineProps<{
    booking: {
        reference: string;
        name: string;
        subject: string;
        starts_at: string;
        ends_at: string;
        duration_minutes: number;
        timezone: string;
        status: string;
        status_label: string;
        type: string;
        meeting_url: string | null;
    };
    canChange: boolean;
    urls: { cancel: string; reschedule: string };
}>();

const request = ref('');
const sending = ref(false);
const errors = ref<Record<string, string>>({});

const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
const when = (iso: string, tz: string): string =>
    new Intl.DateTimeFormat('en', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: tz,
    }).format(new Date(iso));

function cancel(): void {
    if (window.confirm('Cancel this appointment?')) {
        router.post(props.urls.cancel, {}, { preserveScroll: true });
    }
}

function ask(): void {
    router.post(
        props.urls.reschedule,
        { request: request.value },
        {
            preserveScroll: true,
            onStart: () => (sending.value = true),
            onFinish: () => (sending.value = false),
            onSuccess: () => {
                request.value = '';
                errors.value = {};
            },
            onError: (e) => (errors.value = e),
        },
    );
}
</script>

<template>
    <Head title="Your video consultation" />

    <section class="bg-site-surface py-8 lg:py-12">
        <div class="mx-auto max-w-[640px] px-4 sm:px-6">
            <div
                class="rounded-[20px] border border-site-line bg-white px-5 py-6 sm:px-8 sm:py-8"
            >
                <p
                    class="text-[11px] font-semibold tracking-wide text-site-red uppercase"
                >
                    Video consultation
                </p>
                <h1 class="mt-1 text-2xl font-bold">Your appointment</h1>

                <dl
                    class="mt-5 grid grid-cols-[110px_1fr] gap-x-3 gap-y-3 rounded-[14px] bg-site-sand p-4 text-sm"
                >
                    <dt class="text-site-muted">Reference</dt>
                    <dd class="font-bold tracking-wide">
                        {{ booking.reference }}
                    </dd>
                    <dt class="text-site-muted">Status</dt>
                    <dd class="font-semibold">{{ booking.status_label }}</dd>
                    <dt class="text-site-muted">When</dt>
                    <dd class="font-semibold">
                        {{ when(booking.starts_at, zone) }}
                    </dd>
                    <dt class="text-site-muted">Your timezone</dt>
                    <dd>{{ zone }}</dd>
                    <dt class="text-site-muted">Duration</dt>
                    <dd>{{ booking.duration_minutes }} minutes</dd>
                    <dt class="text-site-muted">Type</dt>
                    <dd>{{ booking.type }}</dd>
                    <dt class="text-site-muted">Subject</dt>
                    <dd>{{ booking.subject }}</dd>
                </dl>

                <a
                    v-if="booking.meeting_url"
                    :href="booking.meeting_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-5 inline-flex h-[50px] items-center justify-center rounded-[12px] bg-site-red px-6 text-sm font-semibold text-white hover:bg-site-red-dark"
                    >Join the video consultation</a
                >

                <template v-if="canChange">
                    <form
                        class="mt-6 flex flex-col gap-3 border-t border-site-line pt-5"
                        @submit.prevent="ask"
                    >
                        <label for="mg-request" class="text-sm font-medium"
                            >Need another time?</label
                        >
                        <textarea
                            id="mg-request"
                            v-model="request"
                            rows="3"
                            maxlength="1000"
                            placeholder="Tell us which day and time would suit you."
                            class="w-full rounded-[10px] border border-site-field-border bg-site-field px-[13px] py-3 text-sm outline-none focus:border-site-red"
                        />
                        <p
                            v-if="errors.request"
                            class="text-xs text-site-red"
                            role="alert"
                        >
                            {{ errors.request }}
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <button
                                type="submit"
                                :disabled="sending"
                                class="h-[46px] rounded-[12px] bg-site-red px-5 text-sm font-semibold text-white hover:bg-site-red-dark disabled:opacity-60"
                            >
                                Ask for another time
                            </button>
                            <button
                                type="button"
                                class="h-[46px] rounded-[12px] border border-site-line px-5 text-sm font-semibold hover:bg-site-sand"
                                @click="cancel"
                            >
                                Cancel appointment
                            </button>
                        </div>
                    </form>
                </template>
                <p v-else class="mt-6 text-sm text-site-muted">
                    This appointment can no longer be changed here.
                </p>
            </div>
        </div>
    </section>
</template>
