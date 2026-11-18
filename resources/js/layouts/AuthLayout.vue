<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';

const {
    title = '',
    description = '',
    eyebrow = 'RTL Immigration',
} = defineProps<{
    title?: string;
    description?: string;
    eyebrow?: string;
}>();
</script>

<template>
    <WebsiteLayout>
        <section
            class="flex min-h-[calc(100vh-280px)] flex-col justify-center bg-site-blush-bg/40 px-4 py-12 sm:px-6 sm:py-16 lg:py-20"
        >
            <div class="mx-auto w-full max-w-[480px]">
                <div
                    class="rounded-[22px] border border-site-line/80 bg-white p-6 shadow-[0_12px_40px_rgba(0,0,0,0.06)] sm:p-10"
                >
                    <div class="mb-8 text-center">
                        <p
                            class="text-[11px] font-bold tracking-wider text-site-red uppercase"
                        >
                            {{ eyebrow }}
                        </p>
                        <h1
                            v-if="title"
                            class="mt-2 text-[26px] font-bold tracking-tight text-site-ink sm:text-[30px]"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="mt-2 text-[14px] leading-relaxed text-site-muted"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <slot />
                </div>

                <p class="mt-6 text-center text-xs text-site-muted">
                    Need help or advice?
                    <Link
                        href="/contact"
                        class="font-semibold text-site-red hover:underline"
                    >
                        Talk to an adviser
                    </Link>
                </p>
            </div>
        </section>
    </WebsiteLayout>
</template>
