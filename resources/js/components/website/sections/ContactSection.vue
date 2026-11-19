<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormField from '@/components/website/FormField.vue';
import { fieldClass, selectClass } from '@/components/website/form';
import { useSite } from '@/composables/useSite';
import { guest as guestChat } from '@/routes/chat';
import { store } from '@/routes/enquiry';
import type { Option } from '@/types/website';
import AppIcon from '@/components/panel/AppIcon.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';

/*
 * Consultation enquiry, from Figma "RTL Bangladesh – Contact" ›
 * "Consultation enquiry" (node 12:581). Every submission becomes a lead
 * (REQ §7 "Website Lead Form", §11 step 1).
 */
const props = defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        aside_eyebrow: string;
        aside_title: string;
        steps: { title: string; text: string }[];
        note_title: string;
        note_text: string;
        show_details: boolean;
    };
    records: {
        services: Option[];
        countries: Option[];
        channels: Option[];
        chatEnabled: boolean;
    } | null;
}>();

const { text } = useSite();
const page = usePage();
const query = new URLSearchParams(page.url.split('?')[1] ?? '');
const preference = query.get('preference') ?? '';

const sent = ref(false);
const form = useForm({
    name: '',
    email: '',
    phone: '',
    service_id: '',
    country_id: query.get('country') ?? '',
    contact_preference: props.records?.channels.some(
        (c) => c.value === preference,
    )
        ? preference
        : '',
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

const invalid = (key: keyof typeof form.errors) =>
    form.errors[key] ? true : undefined;
const describedBy = (key: keyof typeof form.errors, id: string) =>
    form.errors[key] ? `${id}-err` : undefined;
</script>

<template>
    <div
        class="mx-auto grid max-w-360 gap-6 px-4 sm:px-6 lg:grid-cols-[minmax(0,804px)_424px] lg:items-start lg:gap-11 lg:pr-24 lg:pl-18"
    >
        <div
            class="rounded-[20px] border border-site-line bg-white px-5 py-6 sm:px-6 sm:pt-6.5 sm:pb-9.5"
        >
            <p
                class="text-[11px] leading-[16.5px] font-semibold tracking-wide text-site-red uppercase"
            >
                {{ data.eyebrow }}
            </p>
            <h2
                class="mt-2.5 text-[28px] leading-tight font-bold sm:text-[36px] sm:leading-[43.7px]"
            >
                {{ data.title }}
            </h2>
            <p
                v-if="data.description"
                class="mt-3 text-[15px] leading-[22.5px] text-site-muted"
            >
                {{ data.description }}
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
                v-else-if="records"
                class="mt-9.5 grid gap-x-6 gap-y-6.5 sm:grid-cols-2"
                novalidate
                @submit.prevent="submit"
            >
                <FormField
                    id="enq-name"
                    label="Full name *"
                    :error="form.errors.name"
                >
                    <input
                        id="enq-name"
                        v-model="form.name"
                        type="text"
                        autocomplete="name"
                        placeholder="Enter your full name"
                        :class="fieldClass"
                        :aria-invalid="invalid('name')"
                        :aria-describedby="describedBy('name', 'enq-name')"
                    />
                </FormField>
                <FormField
                    id="enq-email"
                    label="Email address *"
                    :error="form.errors.email"
                >
                    <input
                        id="enq-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        placeholder="you@example.com"
                        :class="fieldClass"
                        :aria-invalid="invalid('email')"
                        :aria-describedby="describedBy('email', 'enq-email')"
                    />
                </FormField>
                <FormField
                    id="enq-phone"
                    label="Phone / WhatsApp *"
                    :error="form.errors.phone"
                >
                    <input
                        id="enq-phone"
                        v-model="form.phone"
                        type="tel"
                        autocomplete="tel"
                        placeholder="Include country code"
                        :class="fieldClass"
                        :aria-invalid="invalid('phone')"
                        :aria-describedby="describedBy('phone', 'enq-phone')"
                    />
                </FormField>
                <FormField
                    id="enq-service"
                    label="I’m interested in *"
                    :error="form.errors.service_id"
                >
                    <div class="relative">
                        <select
                            id="enq-service"
                            v-model="form.service_id"
                            :class="[
                                selectClass,
                                !form.service_id && 'text-site-muted',
                            ]"
                            :aria-invalid="invalid('service_id')"
                            :aria-describedby="
                                describedBy('service_id', 'enq-service')
                            "
                        >
                            <option value="" disabled>Choose a service</option>
                            <option
                                v-for="s in records.services"
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
                            class="pointer-events-none absolute top-3.75 right-3.75 size-4.5"
                        />
                    </div>
                </FormField>
                <FormField id="enq-country" label="Preferred destination">
                    <div class="relative">
                        <select
                            id="enq-country"
                            v-model="form.country_id"
                            :class="[
                                selectClass,
                                !form.country_id && 'text-site-muted',
                            ]"
                        >
                            <option value="">Select a destination</option>
                            <option
                                v-for="c in records.countries"
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
                            class="pointer-events-none absolute top-3.75 right-3.75 size-4.5"
                        />
                    </div>
                </FormField>
                <FormField id="enq-pref" label="How would you like to talk?">
                    <div class="relative">
                        <select
                            id="enq-pref"
                            v-model="form.contact_preference"
                            :class="[
                                selectClass,
                                !form.contact_preference && 'text-site-muted',
                            ]"
                        >
                            <option value="">Choose contact preference</option>
                            <option
                                v-for="c in records.channels"
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
                            class="pointer-events-none absolute top-3.75 right-3.75 size-4.5"
                        />
                    </div>
                </FormField>
                <FormField
                    id="enq-message"
                    label="Your message *"
                    :error="form.errors.message"
                    class="sm:col-span-2"
                >
                    <textarea
                        id="enq-message"
                        v-model="form.message"
                        rows="5"
                        maxlength="2000"
                        placeholder="Share your goals, questions or preferred study plans…"
                        :class="[fieldClass, 'h-30 resize-y py-3.25']"
                        :aria-invalid="invalid('message')"
                        :aria-describedby="
                            describedBy('message', 'enq-message')
                        "
                    />
                </FormField>

                <!-- Honeypot: hidden from people and screen readers. -->
                <div class="absolute left-[9999px]" aria-hidden="true">
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
                        class="flex items-start gap-3 text-xs leading-4.5 text-site-muted"
                    >
                        <input
                            v-model="form.consent"
                            type="checkbox"
                            class="border-#dfe1e5 mt-0 size-4.5 shrink-0 rounded-[3px] accent-site-red"
                            :aria-invalid="invalid('consent')"
                        />
                        <span
                            >I agree to be contacted about my enquiry and have
                            read the
                            <Link
                                href="/privacy-policy"
                                class="underline hover:text-site-red"
                                >Privacy Policy</Link
                            >.</span
                        >
                    </label>
                    <p
                        v-if="form.errors.consent"
                        class="mt-1.5 text-xs text-site-red"
                    >
                        {{ form.errors.consent }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-6 sm:col-span-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="h-13 rounded-xl bg-site-red px-5 text-sm font-semibold whitespace-pre text-white transition-colors hover:bg-site-red-dark disabled:opacity-60"
                    >
                        <template v-if="form.processing">Sending…</template
                        ><template v-else
                            >Send my enquiry <SiteArrow class="ml-2"
                        /></template>
                    </button>
                    <p class="text-xs text-site-muted">
                        Your details stay private.
                    </p>
                </div>
            </form>
        </div>

        <!-- What to expect. Figma: the form starts 72px from the left edge, the panel ends 96px from the right. -->
        <aside class="rounded-[20px] bg-site-ink p-8 text-white lg:mt-7.5">
            <p
                v-if="data.aside_eyebrow"
                class="text-[11px] leading-[16.5px] font-semibold tracking-wide text-site-blush uppercase"
            >
                {{ data.aside_eyebrow }}
            </p>
            <p
                v-if="data.aside_title"
                class="mt-4 text-[28px] leading-[33.6px] font-bold whitespace-pre-line"
            >
                {{ data.aside_title }}
            </p>
            <ol v-if="data.steps.length" class="mt-12 flex flex-col gap-9.5">
                <li
                    v-for="(s, i) in data.steps"
                    :key="i"
                    class="grid grid-cols-[48px_1fr]"
                >
                    <span
                        class="pt-1.25 text-sm leading-4.5 font-semibold text-site-blush"
                        >{{ String(i + 1).padStart(2, '0') }}</span
                    >
                    <span>
                        <span
                            class="block text-[17px] leading-[25.5px] font-semibold"
                            >{{ s.title }}</span
                        >
                        <span
                            class="mt-2 block text-sm leading-5.25 text-site-on-dark lg:max-w-62.5"
                            >{{ s.text }}</span
                        >
                    </span>
                </li>
            </ol>
            <template v-if="data.note_title || data.note_text">
                <hr class="mt-12.5 border-site-divider" />
                <p class="mt-7.25 text-xl leading-7.5 font-bold">
                    {{ data.note_title }}
                </p>
                <p
                    class="mt-3 text-sm leading-5.25 text-site-on-dark lg:max-w-67.5"
                >
                    {{ data.note_text }}
                </p>
            </template>
            <ul
                v-if="data.show_details"
                class="mt-6 flex flex-col gap-2.5 text-sm text-site-on-dark"
            >
                <li v-if="text('general', 'phone')">
                    <a
                        :href="`tel:${text('general', 'phone').replace(/\s/g, '')}`"
                        class="inline-flex items-center gap-2 hover:text-white"
                    >
                        <AppIcon
                            name="call"
                            :size="16"
                            class="shrink-0 text-site-coral"
                        />
                        <span>{{ text('general', 'phone') }}</span>
                    </a>
                </li>
                <li v-if="text('general', 'contact_email')">
                    <a
                        :href="`mailto:${text('general', 'contact_email')}`"
                        class="inline-flex items-center gap-2 hover:text-white"
                    >
                        <AppIcon
                            name="mail"
                            :size="16"
                            class="shrink-0 text-site-coral"
                        />
                        <span>{{ text('general', 'contact_email') }}</span>
                    </a>
                </li>
                <li
                    v-if="text('general', 'address')"
                    class="inline-flex items-start gap-2"
                >
                    <AppIcon
                        name="location_on"
                        :size="16"
                        class="mt-0.5 shrink-0 text-site-coral"
                    />
                    <span>{{ text('general', 'address') }}</span>
                </li>
                <li
                    v-if="text('general', 'business_hours')"
                    class="inline-flex items-start gap-2"
                >
                    <AppIcon
                        name="schedule"
                        :size="16"
                        class="mt-0.5 shrink-0 text-site-coral"
                    />
                    <span>{{ text('general', 'business_hours') }}</span>
                </li>
            </ul>
            <Link
                v-if="records?.chatEnabled"
                :href="guestChat()"
                class="mt-6 inline-flex h-11 items-center rounded-[10px] bg-white px-4 text-sm font-semibold text-site-ink hover:bg-site-blush"
                >Chat with us now <SiteArrow class="ml-1.5"
            /></Link>
        </aside>
    </div>
</template>
