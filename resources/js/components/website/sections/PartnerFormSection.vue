<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useRecaptcha } from '@/composables/useRecaptcha';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import { apply } from '@/routes/partners';
import type { Option } from '@/types/website';

/**
 * Partner application (REQ §16), from the owner's reference "RTL
 * Bangladesh — Partner application.png": four numbered groups of fields
 * beside a sidebar (benefits, contact box, tips). Creates a pending
 * application that reviewers approve or reject in Admin → Partners.
 */
const props = defineProps<{
    data: {
        title: string;
        description: string;
        aside_eyebrow: string;
        aside_title: string;
        benefits: { title: string; text: string }[];
        question_title: string;
        question_text: string;
        tips_title: string;
        tips: { text: string }[];
        submit_note: string;
    };
    records: {
        types: Option[];
        countries: Option[];
        interests: Option[];
        years: Option[];
        destinations: Option[];
        email: string;
        phone: string;
    } | null;
}>();

const sent = ref(false);
const form = useForm({
    organisation: '',
    type: '',
    country_id: '',
    city: '',
    website: '',
    contact_name: '',
    job_title: '',
    email: '',
    phone: '',
    partnership_interest: '',
    years_in_operation: '',
    destinations: [] as string[],
    message: '',
    documents: [] as File[],
    consent: false,
    company_url: '', // honeypot
    recaptcha_token: '',
});
const { token } = useRecaptcha();

function pick(event: Event): void {
    form.documents = Array.from(
        (event.target as HTMLInputElement).files ?? [],
    ).slice(0, 3);
}

async function submit(): Promise<void> {
    form.recaptcha_token = await token('partner');
    form.transform((d) => ({
        ...d,
        website: d.website || null,
        years_in_operation: d.years_in_operation || null,
    })).submit(apply(), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            sent.value = true;
            form.reset();
        },
    });
}

const err = (key: string): string | undefined =>
    (form.errors as Record<string, string | undefined>)[key];
const prefixErr = (prefix: string) =>
    Object.entries(form.errors).find(
        ([k]) => k === prefix || k.startsWith(`${prefix}.`),
    )?.[1];

// Destinations: a select-style dropdown with checkboxes.
const destOpen = ref(false);
const destLabel = computed(() => {
    const names = (props.records?.destinations ?? [])
        .filter((d) => form.destinations.includes(d.value))
        .map((d) => d.label);

    return names.length ? names.join(', ') : '';
});
function toggleDest(value: string): void {
    form.destinations = form.destinations.includes(value)
        ? form.destinations.filter((v) => v !== value)
        : [...form.destinations, value];
}
function outside(event: MouseEvent): void {
    // The box sits inside the groups v-for, where a template ref would be an array.
    if (!(event.target as Element | null)?.closest('[data-dest-box]')) {
        destOpen.value = false;
    }
}
onMounted(() => document.addEventListener('click', outside));
onBeforeUnmount(() => document.removeEventListener('click', outside));

const input =
    'h-12 w-full rounded-[2px] border border-site-field-border bg-white px-[14px] text-[15px] text-site-ink outline-none placeholder:text-site-muted focus:border-site-red focus:ring-3 focus:ring-site-red/15 aria-invalid:border-site-red';
const select = `${input} cursor-pointer appearance-none pr-11`;
const label =
    'mb-2 lg:mb-[11px] block text-[13px] leading-4 font-medium text-site-ink';
const groups = [
    'Organisation details',
    'Primary contact',
    'Your partnership',
    'Supporting documents',
];
</script>

<template>
    <div
        class="site-container grid gap-12 lg:grid-cols-[minmax(0,800px)_384px] lg:justify-between lg:gap-16"
    >
        <!-- Application form -->
        <div>
            <h2
                class="text-[28px] leading-[1.2] font-bold text-site-ink sm:text-[32px] sm:leading-[38px]"
            >
                {{ data.title }}
            </h2>
            <p
                v-if="data.description"
                class="mt-3 text-[17px] leading-6 text-site-muted lg:mt-[14px]"
            >
                {{ data.description }}
            </p>
            <p class="mt-3 text-[12px] leading-4 text-site-muted lg:mt-[10px]">
                Fields marked * are required.
            </p>

            <div
                v-if="sent"
                class="mt-10 rounded-[2px] border border-site-line bg-site-blush-bg p-6"
                role="status"
            >
                <p class="text-lg font-bold">
                    Thank you, we’ve received your application.
                </p>
                <p class="mt-2 text-sm text-site-muted">
                    Our partnerships team will review it and contact you within
                    a few working days.
                </p>
            </div>

            <form v-else-if="records" novalidate @submit.prevent="submit">
                <template v-for="(group, g) in groups" :key="group">
                    <div
                        class="mt-10 flex items-baseline gap-[22px] border-b border-site-line pb-[9px]"
                        :class="g === 0 ? 'lg:mt-[37px]' : 'lg:mt-[43px]'"
                    >
                        <span
                            class="w-[14px] text-[12px] font-semibold text-site-red"
                            >{{ String(g + 1).padStart(2, '0') }}</span
                        >
                        <h3
                            class="text-[20px] leading-7 font-semibold text-site-ink sm:text-[22px]"
                        >
                            {{ group }}
                        </h3>
                    </div>

                    <div
                        class="mt-6 grid gap-x-6 gap-y-7 sm:grid-cols-2 lg:mt-[21px] lg:gap-y-6"
                    >
                        <!-- 01 Organisation details -->
                        <template v-if="g === 0">
                            <div class="sm:col-span-2">
                                <label for="pa-org" :class="label"
                                    >Organisation / agency name *</label
                                >
                                <input
                                    id="pa-org"
                                    v-model="form.organisation"
                                    type="text"
                                    autocomplete="organization"
                                    placeholder="Enter your registered organisation name"
                                    :class="input"
                                    :aria-invalid="
                                        !!err('organisation') || undefined
                                    "
                                />
                                <p
                                    v-if="err('organisation')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ err('organisation') }}
                                </p>
                            </div>
                            <div class="relative">
                                <label for="pa-type" :class="label"
                                    >Organisation type *</label
                                >
                                <select
                                    id="pa-type"
                                    v-model="form.type"
                                    :class="[
                                        select,
                                        !form.type && 'text-site-muted',
                                    ]"
                                    :aria-invalid="!!err('type') || undefined"
                                >
                                    <option value="" disabled>
                                        Select organisation type
                                    </option>
                                    <option
                                        v-for="t in records.types"
                                        :key="t.value"
                                        :value="t.value"
                                    >
                                        {{ t.label }}
                                    </option>
                                </select>
                                <AppIcon
                                    name="expand_more"
                                    :size="22"
                                    class="pointer-events-none absolute top-[37px] right-[14px] text-site-ink"
                                />
                                <p
                                    v-if="err('type')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ err('type') }}
                                </p>
                            </div>
                            <div class="relative">
                                <label for="pa-country" :class="label"
                                    >Country *</label
                                >
                                <select
                                    id="pa-country"
                                    v-model="form.country_id"
                                    :class="[
                                        select,
                                        !form.country_id && 'text-site-muted',
                                    ]"
                                    :aria-invalid="
                                        !!err('country_id') || undefined
                                    "
                                >
                                    <option value="" disabled>
                                        Select country
                                    </option>
                                    <option
                                        v-for="c in records.countries"
                                        :key="c.value"
                                        :value="c.value"
                                    >
                                        {{ c.label }}
                                    </option>
                                </select>
                                <AppIcon
                                    name="expand_more"
                                    :size="22"
                                    class="pointer-events-none absolute top-[37px] right-[14px] text-site-ink"
                                />
                                <p
                                    v-if="err('country_id')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ err('country_id') }}
                                </p>
                            </div>
                            <div>
                                <label for="pa-city" :class="label"
                                    >City *</label
                                >
                                <input
                                    id="pa-city"
                                    v-model="form.city"
                                    type="text"
                                    autocomplete="address-level2"
                                    placeholder="Enter city"
                                    :class="input"
                                    :aria-invalid="!!err('city') || undefined"
                                />
                                <p
                                    v-if="err('city')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ err('city') }}
                                </p>
                            </div>
                            <div>
                                <label for="pa-web" :class="label"
                                    >Website</label
                                >
                                <input
                                    id="pa-web"
                                    v-model="form.website"
                                    type="url"
                                    inputmode="url"
                                    placeholder="https://yourcompany.com"
                                    :class="input"
                                    :aria-invalid="
                                        !!err('website') || undefined
                                    "
                                />
                                <p
                                    v-if="err('website')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ err('website') }}
                                </p>
                            </div>
                        </template>

                        <!-- 02 Primary contact -->
                        <template v-else-if="g === 1">
                            <div>
                                <label for="pa-name" :class="label"
                                    >Full name *</label
                                >
                                <input
                                    id="pa-name"
                                    v-model="form.contact_name"
                                    type="text"
                                    autocomplete="name"
                                    placeholder="Enter full name"
                                    :class="input"
                                    :aria-invalid="
                                        !!err('contact_name') || undefined
                                    "
                                />
                                <p
                                    v-if="err('contact_name')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ err('contact_name') }}
                                </p>
                            </div>
                            <div>
                                <label for="pa-job" :class="label"
                                    >Job title *</label
                                >
                                <input
                                    id="pa-job"
                                    v-model="form.job_title"
                                    type="text"
                                    autocomplete="organization-title"
                                    placeholder="e.g. Director or Partnership Manager"
                                    :class="input"
                                    :aria-invalid="
                                        !!err('job_title') || undefined
                                    "
                                />
                                <p
                                    v-if="err('job_title')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ err('job_title') }}
                                </p>
                            </div>
                            <div>
                                <label for="pa-email" :class="label"
                                    >Work email *</label
                                >
                                <input
                                    id="pa-email"
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="email"
                                    placeholder="name@company.com"
                                    :class="input"
                                    :aria-invalid="!!err('email') || undefined"
                                />
                                <p
                                    v-if="err('email')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ err('email') }}
                                </p>
                            </div>
                            <div>
                                <label for="pa-phone" :class="label"
                                    >Phone / WhatsApp number *</label
                                >
                                <input
                                    id="pa-phone"
                                    v-model="form.phone"
                                    type="tel"
                                    autocomplete="tel"
                                    placeholder="Include country code"
                                    :class="input"
                                    :aria-invalid="!!err('phone') || undefined"
                                />
                                <p
                                    v-if="err('phone')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ err('phone') }}
                                </p>
                            </div>
                        </template>

                        <!-- 03 Your partnership -->
                        <template v-else-if="g === 2">
                            <div class="relative">
                                <label for="pa-interest" :class="label"
                                    >Partnership interest *</label
                                >
                                <select
                                    id="pa-interest"
                                    v-model="form.partnership_interest"
                                    :class="[
                                        select,
                                        !form.partnership_interest &&
                                            'text-site-muted',
                                    ]"
                                    :aria-invalid="
                                        !!err('partnership_interest') ||
                                        undefined
                                    "
                                >
                                    <option value="" disabled>
                                        Select partnership type
                                    </option>
                                    <option
                                        v-for="o in records.interests"
                                        :key="o.value"
                                        :value="o.value"
                                    >
                                        {{ o.label }}
                                    </option>
                                </select>
                                <AppIcon
                                    name="expand_more"
                                    :size="22"
                                    class="pointer-events-none absolute top-[37px] right-[14px] text-site-ink"
                                />
                                <p
                                    v-if="err('partnership_interest')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ err('partnership_interest') }}
                                </p>
                            </div>
                            <div class="relative">
                                <label for="pa-years" :class="label"
                                    >Years in operation</label
                                >
                                <select
                                    id="pa-years"
                                    v-model="form.years_in_operation"
                                    :class="[
                                        select,
                                        !form.years_in_operation &&
                                            'text-site-muted',
                                    ]"
                                >
                                    <option value="">Select experience</option>
                                    <option
                                        v-for="o in records.years"
                                        :key="o.value"
                                        :value="o.value"
                                    >
                                        {{ o.label }}
                                    </option>
                                </select>
                                <AppIcon
                                    name="expand_more"
                                    :size="22"
                                    class="pointer-events-none absolute top-[37px] right-[14px] text-site-ink"
                                />
                            </div>
                            <div data-dest-box class="relative sm:col-span-2">
                                <span id="pa-dest-label" :class="label"
                                    >Preferred study destinations *</span
                                >
                                <button
                                    type="button"
                                    :class="[
                                        input,
                                        'flex items-center pr-11 text-left',
                                        !destLabel && 'text-site-muted',
                                    ]"
                                    aria-labelledby="pa-dest-label"
                                    aria-haspopup="listbox"
                                    :aria-expanded="destOpen"
                                    :aria-invalid="
                                        !!prefixErr('destinations') || undefined
                                    "
                                    @click="destOpen = !destOpen"
                                >
                                    <span class="truncate">{{
                                        destLabel || 'Select destinations'
                                    }}</span>
                                </button>
                                <AppIcon
                                    name="expand_more"
                                    :size="22"
                                    class="pointer-events-none absolute top-[37px] right-[14px] text-site-ink"
                                />
                                <ul
                                    v-show="destOpen"
                                    role="listbox"
                                    aria-multiselectable="true"
                                    aria-labelledby="pa-dest-label"
                                    class="absolute inset-x-0 top-[calc(100%+4px)] z-20 max-h-64 overflow-y-auto rounded-[2px] border border-site-field-border bg-white py-1 shadow-[0_12px_30px_rgba(0,0,0,0.12)]"
                                >
                                    <li
                                        v-for="d in records.destinations"
                                        :key="d.value"
                                    >
                                        <label
                                            class="flex cursor-pointer items-center gap-3 px-[14px] py-2.5 text-[15px] text-site-ink hover:bg-site-sand"
                                        >
                                            <input
                                                type="checkbox"
                                                class="size-4 accent-site-red"
                                                :checked="
                                                    form.destinations.includes(
                                                        d.value,
                                                    )
                                                "
                                                @change="toggleDest(d.value)"
                                            />
                                            {{ d.label }}
                                        </label>
                                    </li>
                                </ul>
                                <p
                                    v-if="prefixErr('destinations')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ prefixErr('destinations') }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="pa-message" :class="label"
                                    >Tell us about your organisation *</label
                                >
                                <textarea
                                    id="pa-message"
                                    v-model="form.message"
                                    rows="4"
                                    maxlength="3000"
                                    placeholder="Share your services, the students you support and what you’re looking for in a partnership."
                                    :class="[
                                        input,
                                        'h-[104px] resize-y py-[13px]',
                                    ]"
                                    :aria-invalid="
                                        !!err('message') || undefined
                                    "
                                />
                                <p
                                    v-if="err('message')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ err('message') }}
                                </p>
                            </div>
                        </template>

                        <!-- 04 Supporting documents -->
                        <template v-else>
                            <div class="sm:col-span-2 lg:-mt-[8px]">
                                <span :class="label"
                                    >Company profile or business registration
                                    (optional)</span
                                >
                                <label
                                    for="pa-docs"
                                    class="flex h-20 cursor-pointer items-center gap-[18px] rounded-[2px] border border-site-field-border bg-site-blush-bg px-[26px] focus-within:ring-3 focus-within:ring-site-red/15 hover:border-site-red"
                                >
                                    <AppIcon
                                        name="arrow_upward"
                                        :size="24"
                                        class="text-site-red"
                                    />
                                    <span>
                                        <span
                                            class="block text-[15px] leading-5 font-semibold text-site-red"
                                        >
                                            {{
                                                form.documents.length
                                                    ? form.documents
                                                          .map((f) => f.name)
                                                          .join(', ')
                                                    : 'Choose files'
                                            }}
                                        </span>
                                        <span
                                            class="mt-1 block text-[12px] leading-4 text-site-muted"
                                            >PDF, JPG or PNG · Up to 10 MB per
                                            file · Up to 3 files</span
                                        >
                                    </span>
                                    <input
                                        id="pa-docs"
                                        type="file"
                                        multiple
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        class="sr-only"
                                        @change="pick"
                                    />
                                </label>
                                <p
                                    v-if="prefixErr('documents')"
                                    class="mt-1.5 text-xs text-site-red"
                                >
                                    {{ prefixErr('documents') }}
                                </p>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="absolute -left-[9999px]" aria-hidden="true">
                    <input
                        v-model="form.company_url"
                        type="text"
                        tabindex="-1"
                        autocomplete="off"
                    />
                </div>

                <label
                    class="mt-7 flex max-w-[540px] items-start gap-3 text-[12px] leading-[18px] text-site-muted"
                >
                    <input
                        v-model="form.consent"
                        type="checkbox"
                        class="mt-0.5 size-[18px] shrink-0 accent-site-red"
                        :aria-invalid="!!err('consent') || undefined"
                    />
                    I confirm that these details are accurate and agree to be
                    contacted by RTL Bangladesh about this application. I have
                    read the Privacy Policy.
                </label>
                <p v-if="err('consent')" class="mt-1.5 text-xs text-site-red">
                    {{ err('consent') }}
                </p>

                <div
                    class="mt-7 flex flex-wrap items-center gap-x-5 gap-y-3 lg:mt-[29px]"
                >
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex h-[50px] items-center gap-2.5 rounded-[2px] bg-site-red px-5 text-[15px] font-semibold text-white hover:bg-site-red-dark disabled:opacity-60 sm:w-[294px]"
                    >
                        <template v-if="form.processing">Sending…</template>
                        <template v-else
                            >Submit application <SiteArrow
                        /></template>
                    </button>
                    <p
                        v-if="data.submit_note"
                        class="text-[12px] text-site-muted"
                    >
                        {{ data.submit_note }}
                    </p>
                </div>
            </form>
        </div>

        <!-- Sidebar -->
        <aside class="flex flex-col gap-6">
            <div
                class="rounded-[2px] bg-site-ink px-7 pt-9 pb-8 text-white lg:pb-[45px]"
            >
                <p
                    v-if="data.aside_eyebrow"
                    class="text-[11px] leading-[14px] font-semibold text-site-coral uppercase"
                >
                    {{ data.aside_eyebrow }}
                </p>
                <p
                    v-if="data.aside_title"
                    class="mt-[11px] text-[26px] leading-[34px] font-bold whitespace-pre-line sm:text-[28px]"
                >
                    {{ data.aside_title }}
                </p>
                <ol
                    v-if="data.benefits.length"
                    class="mt-9 flex flex-col gap-[30px]"
                >
                    <li
                        v-for="(b, i) in data.benefits"
                        :key="i"
                        class="grid grid-cols-[38px_1fr]"
                    >
                        <span
                            class="pt-1 text-[12px] font-semibold text-site-coral"
                            >{{ String(i + 1).padStart(2, '0') }}</span
                        >
                        <span>
                            <span
                                class="block text-[15px] leading-5 font-semibold"
                                >{{ b.title }}</span
                            >
                            <span
                                class="mt-[9px] block text-[13px] leading-5 text-site-soft-gray"
                                >{{ b.text }}</span
                            >
                        </span>
                    </li>
                </ol>
            </div>

            <div
                v-if="data.question_title || records?.email"
                class="rounded-[2px] bg-site-blush-bg px-7 pt-[30px] pb-9 lg:pb-[50px]"
            >
                <p
                    v-if="data.question_title"
                    class="text-[22px] leading-7 font-bold text-site-ink"
                >
                    {{ data.question_title }}
                </p>
                <p
                    v-if="data.question_text"
                    class="mt-[13px] text-[15px] leading-[21px] whitespace-pre-line text-site-muted"
                >
                    {{ data.question_text }}
                </p>
                <a
                    v-if="records?.email"
                    :href="`mailto:${records.email}`"
                    class="mt-[23px] block text-[16px] font-semibold break-all text-site-red hover:underline"
                    >{{ records.email }}</a
                >
                <a
                    v-if="records?.phone"
                    :href="`tel:${records.phone.replace(/\s/g, '')}`"
                    class="mt-3 block text-[16px] text-site-ink hover:text-site-red lg:mt-[10px]"
                    >{{ records.phone }}</a
                >
            </div>

            <div v-if="data.tips.length" class="pt-5">
                <p
                    v-if="data.tips_title"
                    class="text-[11px] leading-[14px] font-semibold text-site-red uppercase"
                >
                    {{ data.tips_title }}
                </p>
                <ul
                    class="mt-6 flex flex-col gap-2 text-[14px] leading-5 text-site-muted"
                >
                    <li v-for="(t, i) in data.tips" :key="i">{{ t.text }}</li>
                </ul>
            </div>
        </aside>
    </div>
</template>
