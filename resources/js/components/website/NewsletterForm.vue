<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import { store } from '@/routes/newsletter';

/** Email sign-up (REQ §21.7 newsletter, §21.4 footer). */
const props = withDefaults(
    defineProps<{
        dark?: boolean;
        button?: string;
        source?: string;
        /** Figma Blog page style: the button sits inside the email box. */
        combined?: boolean;
    }>(),
    { dark: false, button: 'Subscribe', source: 'website', combined: false },
);

const done = ref(false);
const form = useForm({ email: '', website: '', source: props.source });

function submit(): void {
    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            done.value = true;
            form.reset('email');
        },
    });
}
</script>

<template>
    <p
        v-if="done"
        role="status"
        class="text-[15px] font-medium"
        :class="dark ? 'text-white' : 'text-site-ink'"
    >
        Thanks for subscribing. Look out for our next update.
    </p>
    <form
        v-else-if="combined"
        class="w-full"
        novalidate
        @submit.prevent="submit"
    >
        <div
            class="flex h-[58px] items-center gap-2 rounded-[4px] border border-site-line bg-white p-[5px] focus-within:border-site-red focus-within:ring-3 focus-within:ring-site-red/15"
            :class="form.errors.email && 'border-site-red'"
        >
            <label :for="`newsletter-${source}`" class="sr-only"
                >Email address</label
            >
            <input
                :id="`newsletter-${source}`"
                v-model="form.email"
                type="email"
                autocomplete="email"
                required
                placeholder="Your email address"
                class="h-full min-w-0 flex-1 bg-transparent px-[13px] text-[14px] text-site-ink outline-none placeholder:text-site-muted"
                :aria-invalid="!!form.errors.email || undefined"
                :aria-describedby="
                    form.errors.email ? `newsletter-${source}-err` : undefined
                "
            />
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <input
                    v-model="form.website"
                    type="text"
                    tabindex="-1"
                    autocomplete="off"
                />
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex h-[46px] shrink-0 items-center gap-2.5 rounded-[4px] bg-site-red px-5 text-[14px] font-semibold text-white hover:bg-site-red-dark disabled:opacity-60 sm:w-[160px]"
            >
                {{ form.processing ? 'Subscribing…' : button }}
                <SiteArrow v-if="!form.processing" />
            </button>
        </div>
        <p
            v-if="form.errors.email"
            :id="`newsletter-${source}-err`"
            class="mt-1.5 text-xs text-site-red"
        >
            {{ form.errors.email }}
        </p>
    </form>
    <form
        v-else
        class="flex w-full max-w-[560px] flex-col gap-3 sm:flex-row"
        novalidate
        @submit.prevent="submit"
    >
        <div class="flex-1">
            <label :for="`newsletter-${source}`" class="sr-only"
                >Email address</label
            >
            <input
                :id="`newsletter-${source}`"
                v-model="form.email"
                type="email"
                autocomplete="email"
                required
                placeholder="you@example.com"
                class="h-[50px] w-full rounded-[12px] border px-4 text-[15px] outline-none focus:ring-3"
                :class="
                    dark
                        ? 'border-site-divider bg-site-panel text-white placeholder:text-site-soft-gray focus:border-white focus:ring-white/20'
                        : 'border-site-field-border bg-white text-site-ink placeholder:text-site-muted focus:border-site-red focus:ring-site-red/15'
                "
                :aria-invalid="!!form.errors.email || undefined"
                :aria-describedby="
                    form.errors.email ? `newsletter-${source}-err` : undefined
                "
            />
            <p
                v-if="form.errors.email"
                :id="`newsletter-${source}-err`"
                class="mt-1.5 text-xs"
                :class="dark ? 'text-site-blush' : 'text-site-red'"
            >
                {{ form.errors.email }}
            </p>
        </div>
        <!-- Honeypot: hidden from people and screen readers. -->
        <div class="absolute -left-[9999px]" aria-hidden="true">
            <input
                v-model="form.website"
                type="text"
                tabindex="-1"
                autocomplete="off"
            />
        </div>
        <button
            type="submit"
            :disabled="form.processing"
            class="h-[50px] shrink-0 rounded-[12px] bg-site-red px-6 text-[14px] font-semibold text-white hover:bg-site-red-dark disabled:opacity-60"
        >
            {{ form.processing ? 'Subscribing…' : button }}
        </button>
    </form>
</template>
