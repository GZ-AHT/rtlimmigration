<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ArrowLink from '@/components/website/ArrowLink.vue';
import NewsletterForm from '@/components/website/NewsletterForm.vue';
import SiteButton from '@/components/website/SiteButton.vue';
import SiteLink from '@/components/website/SiteLink.vue';
import SiteLogo from '@/components/website/SiteLogo.vue';
import { useSite } from '@/composables/useSite';

/**
 * Figma "Footer" (node 12:219): red consultation band, wordmark and
 * description, Explore / Our services / Let's talk columns, copyright and
 * legal links. Text from Site settings → General and Footer; links from
 * Website CMS → Menus.
 */
const { text, flag, link, menu, media, whatsappUrl } = useSite();

const ctaButton = computed(() => link('footer', 'cta_button'));
const copyright = computed(() =>
    text('general', 'copyright').replace(
        '{year}',
        String(new Date().getFullYear()),
    ),
);
const social = computed(() =>
    (
        [
            ['facebook', 'Facebook'],
            ['instagram', 'Instagram'],
            ['linkedin', 'LinkedIn'],
            ['youtube', 'YouTube'],
            ['x', 'X'],
            ['tiktok', 'TikTok'],
        ] as const
    )
        .map(([key, label]) => ({ key, label, href: text('social', key) }))
        .filter((s) => s.href),
);
const columns = computed(() => [
    { title: 'Explore RTL', items: menu('footer') },
    { title: 'Our services', items: menu('footer_services') },
]);
</script>

<template>
    <footer class="bg-site-navy text-white">
        <!-- Consultation invitation -->
        <div v-if="text('footer', 'cta_title')" class="bg-site-red">
            <div
                class="mx-auto flex max-w-360 flex-col gap-6 px-4 py-9 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-24 lg:py-8.75"
            >
                <div>
                    <p
                        class="text-[24px] leading-[1.24] font-semibold sm:text-[30px] sm:leading-[37.2px]"
                    >
                        {{ text('footer', 'cta_title') }}
                    </p>
                    <p
                        v-if="text('footer', 'cta_text')"
                        class="mt-3 text-[15px] leading-5.25"
                    >
                        {{ text('footer', 'cta_text') }}
                    </p>
                </div>
                <SiteButton
                    v-if="ctaButton"
                    :href="ctaButton.url"
                    variant="light"
                    class="w-full shrink-0 rounded-xl sm:w-63.5"
                    >{{ ctaButton.label }}</SiteButton
                >
            </div>
        </div>

        <div class="mx-auto max-w-360 px-4 sm:px-6 lg:px-24">
            <div
                class="grid gap-10 pt-12 pb-10 md:grid-cols-2 lg:grid-cols-[412px_246px_264px_1fr] lg:gap-0 lg:pt-9.25 lg:pb-10.5"
            >
                <div class="lg:pr-14">
                    <SiteLogo dark />
                    <p
                        v-if="text('footer', 'description')"
                        class="mt-4.5 max-w-88.75 text-[13px] leading-[21.45px] whitespace-pre-line text-site-soft-gray"
                    >
                        {{ text('footer', 'description') }}
                    </p>
                    <ul
                        v-if="social.length"
                        class="mt-5 flex flex-wrap items-center gap-2.5 text-[13px]"
                        aria-label="Social media"
                    >
                        <li v-for="s in social" :key="s.label">
                            <a
                                :href="s.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                :aria-label="s.label"
                                :title="s.label"
                                class="flex size-8 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-site-red hover:text-white"
                            >
                                <svg
                                    v-if="s.key === 'facebook'"
                                    class="size-4"
                                    fill="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"
                                    />
                                </svg>
                                <svg
                                    v-else-if="s.key === 'instagram'"
                                    class="size-4"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <rect
                                        width="20"
                                        height="20"
                                        x="2"
                                        y="2"
                                        rx="5"
                                        ry="5"
                                    />
                                    <path
                                        d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"
                                    />
                                    <line
                                        x1="17.5"
                                        x2="17.51"
                                        y1="6.5"
                                        y2="6.5"
                                    />
                                </svg>
                                <svg
                                    v-else-if="s.key === 'linkedin'"
                                    class="size-4"
                                    fill="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"
                                    />
                                </svg>
                                <svg
                                    v-else-if="s.key === 'youtube'"
                                    class="size-4"
                                    fill="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"
                                    />
                                </svg>
                                <svg
                                    v-else-if="s.key === 'x'"
                                    class="size-3.5"
                                    fill="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"
                                    />
                                </svg>
                                <svg
                                    v-else-if="s.key === 'tiktok'"
                                    class="size-4"
                                    fill="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.24 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"
                                    />
                                </svg>
                                <span v-else>{{ s.label }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div v-for="col in columns" :key="col.title" class="lg:pt-2">
                    <template v-if="col.items.length">
                        <p
                            class="text-[11px] leading-[14.3px] font-semibold uppercase"
                        >
                            {{ col.title }}
                        </p>
                        <ul
                            class="mt-5.25 flex flex-col gap-[10.8px] text-[13px] leading-[18.2px]"
                        >
                            <li v-for="item in col.items" :key="item.label">
                                <SiteLink
                                    :href="item.href"
                                    :new-tab="item.new_tab"
                                    class="text-[13px] leading-[18.2px] text-site-soft-gray hover:text-white"
                                    >{{ item.label }}</SiteLink
                                >
                            </li>
                        </ul>
                    </template>
                </div>

                <div class="lg:pt-2">
                    <p
                        class="text-[11px] leading-[14.3px] font-semibold uppercase"
                    >
                        Let’s talk
                    </p>
                    <ul
                        class="mt-5.25 flex flex-col gap-3 text-[13px] leading-[18.2px] text-site-soft-gray"
                    >
                        <li
                            v-if="text('general', 'phone')"
                            class="flex items-center gap-2"
                        >
                            <AppIcon
                                name="call"
                                :size="15"
                                class="shrink-0 text-site-red"
                            />
                            <a
                                :href="`tel:${text('general', 'phone').replace(/\s/g, '')}`"
                                class="hover:text-white"
                                >{{ text('general', 'phone') }}</a
                            >
                        </li>
                        <li
                            v-if="text('general', 'contact_email')"
                            class="flex items-center gap-2"
                        >
                            <AppIcon
                                name="mail"
                                :size="15"
                                class="shrink-0 text-site-red"
                            />
                            <a
                                :href="`mailto:${text('general', 'contact_email')}`"
                                class="break-all hover:text-white"
                                >{{ text('general', 'contact_email') }}</a
                            >
                        </li>
                        <li
                            v-if="text('general', 'address')"
                            class="flex items-start gap-2"
                        >
                            <AppIcon
                                name="location_on"
                                :size="15"
                                class="mt-0.5 shrink-0 text-site-red"
                            />
                            <span>{{ text('general', 'address') }}</span>
                        </li>
                        <li
                            class="flex flex-wrap gap-x-5.5 gap-y-2 pt-1 font-normal"
                        >
                            <ArrowLink
                                v-if="whatsappUrl"
                                :href="whatsappUrl"
                                tone="white"
                                weight="normal"
                                >WhatsApp</ArrowLink
                            >
                            <ArrowLink
                                href="/contact"
                                tone="white"
                                weight="normal"
                                >Contact us</ArrowLink
                            >
                        </li>
                    </ul>
                </div>
            </div>

            <div
                v-if="flag('footer', 'show_newsletter')"
                class="border-t border-[#3c3e43] py-8"
            >
                <NewsletterForm dark source="footer" />
            </div>

            <div
                class="flex flex-col gap-4 border-t border-[#3c3e43] py-6 text-[12px] leading-[16.8px] text-site-soft-gray lg:flex-row lg:items-center lg:justify-between lg:pt-6.75 lg:pb-9.25"
            >
                <p>{{ copyright }}</p>
                <div
                    v-if="media?.payment_logos?.length"
                    class="flex flex-wrap items-center gap-3"
                >
                    <img
                        v-for="logo in media.payment_logos"
                        :key="logo.url"
                        :src="logo.url"
                        :alt="logo.alt"
                        class="h-6 w-auto rounded bg-white px-1"
                        loading="lazy"
                    />
                </div>
                <ul class="flex flex-wrap gap-x-7.5 gap-y-2">
                    <li v-for="item in menu('footer_legal')" :key="item.label">
                        <SiteLink
                            :href="item.href"
                            :new-tab="item.new_tab"
                            class="hover:text-white"
                            >{{ item.label }}</SiteLink
                        >
                    </li>
                </ul>
            </div>
        </div>
    </footer>
</template>
