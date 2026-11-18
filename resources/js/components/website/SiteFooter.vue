<script setup lang="ts">
import { computed } from 'vue';
import ArrowLink from '@/components/website/ArrowLink.vue';
import SiteButton from '@/components/website/SiteButton.vue';
import SiteLink from '@/components/website/SiteLink.vue';
import SiteLogo from '@/components/website/SiteLogo.vue';
import NewsletterForm from '@/components/website/NewsletterForm.vue';
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
        .map(([key, label]) => ({ label, href: text('social', key) }))
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
                class="mx-auto flex max-w-[1440px] flex-col gap-6 px-4 py-9 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-24 lg:py-[35px]"
            >
                <div>
                    <p
                        class="text-[24px] leading-[1.24] font-semibold sm:text-[30px] sm:leading-[37.2px]"
                    >
                        {{ text('footer', 'cta_title') }}
                    </p>
                    <p
                        v-if="text('footer', 'cta_text')"
                        class="mt-3 text-[15px] leading-[21px]"
                    >
                        {{ text('footer', 'cta_text') }}
                    </p>
                </div>
                <SiteButton
                    v-if="ctaButton"
                    :href="ctaButton.url"
                    variant="light"
                    class="w-full shrink-0 rounded-[12px] sm:w-[254px]"
                    >{{ ctaButton.label }}</SiteButton
                >
            </div>
        </div>

        <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-24">
            <div
                class="grid gap-10 pt-12 pb-10 md:grid-cols-2 lg:grid-cols-[412px_246px_264px_1fr] lg:gap-0 lg:pt-[37px] lg:pb-[42px]"
            >
                <div class="lg:pr-14">
                    <SiteLogo dark />
                    <p
                        v-if="text('footer', 'description')"
                        class="mt-[18px] max-w-[355px] text-[13px] leading-[21.45px] whitespace-pre-line text-site-soft-gray"
                    >
                        {{ text('footer', 'description') }}
                    </p>
                    <ul
                        v-if="social.length"
                        class="mt-5 flex flex-wrap gap-x-4 gap-y-2 text-[13px]"
                    >
                        <li v-for="s in social" :key="s.label">
                            <a
                                :href="s.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-white hover:text-site-blush"
                                >{{ s.label }}</a
                            >
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
                            class="mt-[21px] flex flex-col gap-[10.8px] text-[13px] leading-[18.2px]"
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
                        class="mt-[21px] flex flex-col gap-[10.8px] text-[13px] leading-[18.2px] text-site-soft-gray"
                    >
                        <li v-if="text('general', 'phone')">
                            <a
                                :href="`tel:${text('general', 'phone').replace(/\s/g, '')}`"
                                class="hover:text-white"
                                >{{ text('general', 'phone') }}</a
                            >
                        </li>
                        <li v-if="text('general', 'contact_email')">
                            <a
                                :href="`mailto:${text('general', 'contact_email')}`"
                                class="break-all hover:text-white"
                                >{{ text('general', 'contact_email') }}</a
                            >
                        </li>
                        <li v-if="text('general', 'address')">
                            {{ text('general', 'address') }}
                        </li>
                        <li
                            class="flex flex-wrap gap-x-[22px] gap-y-2 font-normal"
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
                class="flex flex-col gap-4 border-t border-[#3c3e43] py-6 text-[12px] leading-[16.8px] text-site-soft-gray lg:flex-row lg:items-center lg:justify-between lg:pt-[27px] lg:pb-[37px]"
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
                <ul class="flex flex-wrap gap-x-[30px] gap-y-2">
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
