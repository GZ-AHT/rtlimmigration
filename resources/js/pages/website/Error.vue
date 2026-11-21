<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ArrowLink from '@/components/website/ArrowLink.vue';
import SiteButton from '@/components/website/SiteButton.vue';

/** Website error pages: 404, 403, 429, 500, 503 (REQ §21.8 "404 page"). */
const props = defineProps<{ status: number; message?: string | null }>();

const copy = computed(
    () =>
        ({
            404: [
                'Page not found',
                'This page has moved or no longer exists.',
                'Let’s get you back on track.',
            ],
            403: [
                'Access denied',
                'You don’t have permission to view this page.',
                'If you think this is a mistake, contact us.',
            ],
            429: [
                'Too many requests',
                'You’ve made a lot of requests in a short time.',
                'Please wait a minute and try again.',
            ],
            500: [
                'Something went wrong',
                'An unexpected error happened on our side.',
                'Please try again in a moment.',
            ],
            503: [
                'Back soon',
                'We’re doing some maintenance.',
                'Please check back shortly.',
            ],
        })[props.status] ?? ['Something went wrong', 'Please try again.', ''],
);
</script>

<template>
    <Head :title="copy[0]">
        <meta head-key="robots" name="robots" content="noindex" />
    </Head>
    <section class="bg-site-blush-bg">
        <div class="site-container py-20 lg:py-28">
            <p
                class="text-[11px] font-semibold tracking-[1.2px] text-site-red-text uppercase"
            >
                Error {{ status }}
            </p>
            <h1
                class="mt-6 text-[48px] leading-[1.05] font-bold tracking-[-0.04em] text-site-ink sm:text-[72px]"
            >
                {{ copy[0] }}.
            </h1>
            <p
                class="mt-6 max-w-[560px] text-[18px] leading-[1.6] text-site-muted"
            >
                {{ message || `${copy[1]} ${copy[2]}` }}
            </p>
            <div class="mt-10 flex flex-wrap items-center gap-6">
                <SiteButton href="/" arrow="→">Go to the homepage</SiteButton>
                <ArrowLink href="/contact" tone="ink">Contact us</ArrowLink>
            </div>
        </div>
    </section>
</template>
