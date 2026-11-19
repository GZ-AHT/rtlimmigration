<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { inject } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import SiteLink from '@/components/website/SiteLink.vue';
import type { LinkValue, SiteImage } from '@/types/website';

/**
 * Figma "RTL Bangladesh — Contact" header: breadcrumb, two-tone heading,
 * photo with a caption bar, and the call / email / WhatsApp cards (whose
 * numbers come from Site settings → General).
 */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        title_accent: string;
        description: string;
        tags: string;
        image: SiteImage | null;
        image_caption: LinkValue;
    };
    records: {
        cards: {
            label: string;
            title: string;
            text: string;
            link_label: string;
            href: string | null;
            external: boolean;
        }[];
    } | null;
}>();

const pageTitle = inject<string>('pageTitle', '');

function cardIcon(label: string): string {
    const l = label.toLowerCase();
    if (l.includes('call') || l.includes('phone')) return 'call';
    if (l.includes('email') || l.includes('mail')) return 'mail';
    if (l.includes('whatsapp') || l.includes('chat')) return 'forum';
    return 'contact_support';
}
</script>

<template>
    <div class="site-container pt-6 pb-10 lg:pt-6.5 lg:pb-8">
        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_476px] lg:gap-10">
            <div>
                <nav aria-label="Breadcrumb">
                    <ol
                        class="flex gap-2 text-[12px] leading-4.5 text-site-muted"
                    >
                        <li>
                            <Link href="/" class="hover:text-site-red"
                                >Home</Link
                            >
                        </li>
                        <li aria-hidden="true">/</li>
                        <li aria-current="page">{{ pageTitle }}</li>
                    </ol>
                </nav>
                <p
                    v-if="data.eyebrow"
                    class="mt-9.5 text-[13px] leading-4 font-semibold tracking-[1.6px] text-site-red uppercase"
                >
                    {{ data.eyebrow }}
                </p>
                <h1
                    class="mt-5 text-[48px] leading-[1.07] font-bold tracking-[-0.03em] whitespace-pre-line text-site-ink sm:text-[60px] lg:mt-3 lg:text-[72px] lg:leading-18.5 lg:tracking-[-1.5px]"
                >
                    {{ data.title
                    }}<template v-if="data.title_accent"
                        >{{ '\n'
                        }}<span class="text-site-red">{{
                            data.title_accent
                        }}</span></template
                    >
                </h1>
                <p
                    v-if="data.description"
                    class="mt-6 max-w-130 text-[17px] leading-6.25 whitespace-pre-line text-site-muted lg:mt-5"
                >
                    {{ data.description }}
                </p>
                <p
                    v-if="data.tags"
                    class="mt-8 text-[10px] leading-3.5 font-semibold text-site-red uppercase lg:mt-11"
                >
                    {{ data.tags }}
                </p>
            </div>

            <div v-if="data.image" class="relative lg:mt-0.75">
                <SiteImg
                    :image="data.image"
                    eager
                    class="block aspect-476/356 w-full rounded-[20px]"
                />
                <component
                    :is="data.image_caption.url ? SiteLink : 'p'"
                    v-if="data.image_caption.label"
                    v-bind="
                        data.image_caption.url
                            ? { href: data.image_caption.url }
                            : {}
                    "
                    class="absolute right-10 bottom-6 left-6 flex h-12 items-center gap-2.5 rounded-xl bg-white px-4.5 text-[15px] font-semibold text-site-ink"
                    :class="data.image_caption.url && 'hover:text-site-red'"
                >
                    {{ data.image_caption.label }}
                    <SiteArrow
                        v-if="data.image_caption.url"
                        class="text-[14px]"
                    />
                </component>
            </div>
        </div>

        <ul
            v-if="records?.cards.length"
            class="mt-10 grid gap-6 md:grid-cols-3 lg:mt-13"
        >
            <li
                v-for="(card, i) in records.cards"
                :key="i"
                class="flex flex-col rounded-2xl border border-site-line bg-white px-5.5 pt-5 pb-7 lg:min-h-41"
            >
                <div class="flex items-center justify-between">
                    <p
                        class="text-[11px] leading-3.5 font-semibold tracking-[1px] text-site-red uppercase"
                    >
                        {{ String(i + 1).padStart(2, '0') }} / {{ card.label }}
                    </p>
                    <span
                        class="flex size-7 items-center justify-center rounded-full bg-site-blush-bg text-site-red"
                        aria-hidden="true"
                    >
                        <AppIcon :name="cardIcon(card.label)" :size="15" />
                    </span>
                </div>
                <p
                    class="mt-4.25 text-[19px] leading-6 font-semibold wrap-break-word text-site-ink"
                >
                    {{ card.title }}
                </p>
                <p
                    v-if="card.text"
                    class="mt-1.75 text-[13px] leading-5 text-site-muted"
                >
                    {{ card.text }}
                </p>
                <SiteLink
                    v-if="card.href && card.link_label"
                    :href="card.href"
                    :new-tab="card.external"
                    class="group mt-auto inline-flex items-center gap-2.5 pt-6 text-[13px] leading-5 font-semibold text-site-red hover:underline lg:pt-0"
                >
                    {{ card.link_label }}
                    <SiteArrow
                        class="transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                    />
                </SiteLink>
            </li>
        </ul>
    </div>
</template>
