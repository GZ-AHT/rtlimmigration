<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { store } from '@/routes/enquiry';
import type { Option } from '@/types';

/*
 * Consultation enquiry, recreated from Figma "RTL Bangladesh – Contact" ›
 * "Consultation enquiry" (node 12:581). Submitting creates a lead
 * (REQ §7 "Website Lead Form", §11 step 1).
 *
 * The section copy below is the Figma copy. It moves into the CMS with the
 * rest of the page in Phase 9 (REQ §21.8).
 */
defineProps<{ services: Option[]; countries: Option[]; channels: Option[] }>();

const sent = ref(false);
const form = useForm({
    name: '',
    email: '',
    phone: '',
    service_id: '',
    country_id: '',
    contact_preference: '',
    message: '',
    consent: false,
    website: '', // honeypot, hidden from people
});

function submit(): void {
    form.transform((d) => ({
        ...d,
        country_id: d.country_id || null,
        contact_preference: d.contact_preference || null,
    })).submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = true;
            form.reset();
        },
    });
}

const steps = [
    {
        n: '01',
        title: 'We listen first',
        body: 'We’ll understand your background, your goals and what matters to you.',
    },
    {
        n: '02',
        title: 'We explore your options',
        body: 'Discuss courses, destinations and practical application next steps.',
    },
    {
        n: '03',
        title: 'We plan your way forward',
        body: 'Leave with a clearer idea of what to prepare and where to begin.',
    },
];

const field =
    'h-[50px] w-full rounded-[10px] border border-site-field-border bg-site-field px-[13px] text-sm text-site-ink outline-none placeholder:text-site-muted focus:border-site-red focus:ring-3 focus:ring-site-red/15 aria-invalid:border-site-red';
const label = 'mb-2 block text-sm leading-[19.5px] font-medium text-site-ink';
</script>

<template>
    <Head title="Contact us" />

    <section
        class="bg-site-surface py-3 lg:py-3"
        aria-labelledby="enquiry-heading"
    >
        <div
            class="mx-auto grid max-w-[1296px] gap-6 px-4 sm:px-6 lg:grid-cols-[minmax(0,804px)_424px] lg:items-start lg:gap-11 lg:px-[72px]"
        >
            <!-- Enquiry form surface -->
            <div
                class="rounded-[20px] border border-site-line bg-white px-5 py-6 sm:px-6 sm:pt-[26px] sm:pb-[38px]"
            >
                <p
                    class="text-[11px] leading-[16.5px] font-semibold tracking-wide text-site-red"
                >
                    A LITTLE ABOUT YOU
                </p>
                <h2
                    id="enquiry-heading"
                    class="mt-[10px] text-[28px] leading-tight font-bold sm:text-[36px] sm:leading-[43.7px]"
                >
                    Where would you like to go?
                </h2>
                <p class="mt-3 text-[15px] leading-[22.5px] text-site-muted">
                    Tell us what’s on your mind. We’ll help you find a clear way
                    forward.
                </p>

                <div
                    v-if="sent"
                    class="mt-8 rounded-[14px] border border-site-line bg-site-surface p-6"
                    role="status"
                >
                    <p class="text-lg font-bold">
                        Thank you, we’ve received your enquiry.
                    </p>
                    <p class="mt-2 text-sm text-site-muted">
                        A member of our team will contact you soon.
                    </p>
                    <button
                        type="button"
                        class="mt-4 text-sm font-semibold text-site-red hover:underline"
                        @click="sent = false"
                    >
                        Send another enquiry
                    </button>
                </div>

                <form
                    v-else
                    class="mt-[38px] grid gap-x-6 gap-y-[26px] sm:grid-cols-2"
                    novalidate
                    @submit.prevent="submit"
                >
                    <div>
                        <label for="enq-name" :class="label">Full name *</label>
                        <input
                            id="enq-name"
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            placeholder="Enter your full name"
                            :class="field"
                            :aria-invalid="!!form.errors.name || undefined"
                            aria-describedby="enq-name-err"
                        />
                        <p
                            v-if="form.errors.name"
                            id="enq-name-err"
                            class="mt-1.5 text-xs text-site-red"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <div>
                        <label for="enq-email" :class="label"
                            >Email address *</label
                        >
                        <input
                            id="enq-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="you@example.com"
                            :class="field"
                            :aria-invalid="!!form.errors.email || undefined"
                            aria-describedby="enq-email-err"
                        />
                        <p
                            v-if="form.errors.email"
                            id="enq-email-err"
                            class="mt-1.5 text-xs text-site-red"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>
                    <div>
                        <label for="enq-phone" :class="label"
                            >Phone / WhatsApp *</label
                        >
                        <input
                            id="enq-phone"
                            v-model="form.phone"
                            type="tel"
                            autocomplete="tel"
                            placeholder="Include country code"
                            :class="field"
                            :aria-invalid="!!form.errors.phone || undefined"
                            aria-describedby="enq-phone-err"
                        />
                        <p
                            v-if="form.errors.phone"
                            id="enq-phone-err"
                            class="mt-1.5 text-xs text-site-red"
                        >
                            {{ form.errors.phone }}
                        </p>
                    </div>
                    <div>
                        <label for="enq-service" :class="label"
                            >I’m interested in *</label
                        >
                        <div class="relative">
                            <select
                                id="enq-service"
                                v-model="form.service_id"
                                :class="[
                                    field,
                                    'appearance-none pr-10',
                                    !form.service_id && 'text-site-muted',
                                ]"
                                :aria-invalid="
                                    !!form.errors.service_id || undefined
                                "
                                aria-describedby="enq-service-err"
                            >
                                <option value="" disabled>
                                    Choose a service
                                </option>
                                <option
                                    v-for="s in services"
                                    :key="s.value"
                                    :value="s.value"
                                >
                                    {{ s.label }}
                                </option>
                            </select>
                            <img
                                src="/images/website/chevron-down.svg"
                                alt=""
                                width="24"
                                height="24"
                                class="pointer-events-none absolute top-[15px] right-[15px] size-[18px]"
                            />
                        </div>
                        <p
                            v-if="form.errors.service_id"
                            id="enq-service-err"
                            class="mt-1.5 text-xs text-site-red"
                        >
                            {{ form.errors.service_id }}
                        </p>
                    </div>
                    <div>
                        <label for="enq-country" :class="label"
                            >Preferred destination</label
                        >
                        <div class="relative">
                            <select
                                id="enq-country"
                                v-model="form.country_id"
                                :class="[
                                    field,
                                    'appearance-none pr-10',
                                    !form.country_id && 'text-site-muted',
                                ]"
                            >
                                <option value="">Select a destination</option>
                                <option
                                    v-for="c in countries"
                                    :key="c.value"
                                    :value="c.value"
                                >
                                    {{ c.label }}
                                </option>
                            </select>
                            <img
                                src="/images/website/chevron-down.svg"
                                alt=""
                                width="24"
                                height="24"
                                class="pointer-events-none absolute top-[15px] right-[15px] size-[18px]"
                            />
                        </div>
                    </div>
                    <div>
                        <label for="enq-pref" :class="label"
                            >How would you like to talk?</label
                        >
                        <div class="relative">
                            <select
                                id="enq-pref"
                                v-model="form.contact_preference"
                                :class="[
                                    field,
                                    'appearance-none pr-10',
                                    !form.contact_preference &&
                                        'text-site-muted',
                                ]"
                            >
                                <option value="">
                                    Choose contact preference
                                </option>
                                <option
                                    v-for="c in channels"
                                    :key="c.value"
                                    :value="c.value"
                                >
                                    {{ c.label }}
                                </option>
                            </select>
                            <img
                                src="/images/website/chevron-down.svg"
                                alt=""
                                width="24"
                                height="24"
                                class="pointer-events-none absolute top-[15px] right-[15px] size-[18px]"
                            />
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="enq-message" :class="label"
                            >Your message *</label
                        >
                        <textarea
                            id="enq-message"
                            v-model="form.message"
                            rows="5"
                            maxlength="2000"
                            placeholder="Share your goals, questions or preferred study plans…"
                            :class="[field, 'h-[120px] resize-y py-[13px]']"
                            :aria-invalid="!!form.errors.message || undefined"
                            aria-describedby="enq-message-err"
                        />
                        <p
                            v-if="form.errors.message"
                            id="enq-message-err"
                            class="mt-1.5 text-xs text-site-red"
                        >
                            {{ form.errors.message }}
                        </p>
                    </div>

                    <!-- Honeypot: hidden from people and screen readers. -->
                    <div class="absolute -left-[9999px]" aria-hidden="true">
                        <label for="enq-website">Website</label>
                        <input
                            id="enq-website"
                            v-model="form.website"
                            type="text"
                            tabindex="-1"
                            autocomplete="off"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <label
                            class="flex items-start gap-3 text-xs leading-[18px] text-site-muted"
                        >
                            <input
                                v-model="form.consent"
                                type="checkbox"
                                class="mt-0 size-[18px] shrink-0 rounded-[3px] border-[#dfe1e5] accent-site-red"
                                :aria-invalid="
                                    !!form.errors.consent || undefined
                                "
                            />
                            I agree to be contacted about my enquiry and have
                            read the Privacy Policy.
                        </label>
                        <p
                            v-if="form.errors.consent"
                            class="mt-1.5 text-xs text-site-red"
                        >
                            {{ form.errors.consent }}
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-6 sm:col-span-2"
                    >
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="h-[52px] rounded-[12px] bg-site-red px-5 text-sm font-semibold text-white transition-colors hover:bg-site-red-dark disabled:opacity-60"
                        >
                            {{
                                form.processing
                                    ? 'Sending…'
                                    : 'Send my enquiry  ↗'
                            }}
                        </button>
                        <p class="text-xs text-site-muted">
                            Your details stay private.
                        </p>
                    </div>
                </form>
            </div>

            <!-- What to expect -->
            <aside
                class="rounded-[20px] bg-site-ink p-8 text-white lg:mt-[30px]"
            >
                <p
                    class="text-[11px] leading-[16.5px] font-semibold tracking-wide text-site-blush"
                >
                    A CLEARER NEXT STEP
                </p>
                <p class="mt-4 text-[28px] leading-[33.6px] font-bold">
                    You bring the ambition.<br />We bring the guidance.
                </p>
                <ol class="mt-12 flex flex-col gap-[38px]">
                    <li
                        v-for="s in steps"
                        :key="s.n"
                        class="grid grid-cols-[48px_1fr]"
                    >
                        <span
                            class="pt-[5px] text-sm leading-[18px] font-semibold text-site-blush"
                            >{{ s.n }}</span
                        >
                        <span>
                            <span
                                class="block text-[17px] leading-[25.5px] font-semibold"
                                >{{ s.title }}</span
                            >
                            <span
                                class="mt-2 block text-sm leading-[21px] text-site-on-dark"
                                >{{ s.body }}</span
                            >
                        </span>
                    </li>
                </ol>
                <hr class="mt-[50px] border-site-divider" />
                <p class="mt-[29px] text-xl leading-[30px] font-bold">
                    Not in Dhaka? No problem.
                </p>
                <p class="mt-3 text-sm leading-[21px] text-site-on-dark">
                    Ask about a video consultation and connect with us from
                    wherever you are.
                </p>
            </aside>
        </div>
    </section>
</template>
