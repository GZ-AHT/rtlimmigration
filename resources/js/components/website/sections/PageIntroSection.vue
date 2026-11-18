<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, inject } from 'vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import SiteLink from '@/components/website/SiteLink.vue';
import type { LinkValue } from '@/types/website';

/**
 * Simple page header. Blog: a red note on the right (Figma "RTL Bangladesh
 * — Blog"). Partner application: a panel with a red left border and a
 * parent breadcrumb (owner's reference "Partner application.png").
 */
const props = defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        parent: LinkValue;
        aside: LinkValue;
        aside_eyebrow: string;
        aside_title: string;
        aside_text: string;
    };
}>();

const pageTitle = inject<string>('pageTitle', '');
const panel = computed(
    () => !!(props.data.aside_title || props.data.aside_text),
);
</script>

<template>
    <div
        class="site-container pt-6 pb-10"
        :class="
            panel ? 'lg:pt-[31px] lg:pb-[36px]' : 'lg:pt-[27px] lg:pb-[31px]'
        "
    >
        <nav aria-label="Breadcrumb">
            <ol
                class="flex flex-wrap gap-2 text-[12px] leading-[18px] text-site-muted"
            >
                <li><Link href="/" class="hover:text-site-red">Home</Link></li>
                <template v-if="data.parent.label">
                    <li aria-hidden="true">/</li>
                    <li>
                        <SiteLink
                            v-if="data.parent.url"
                            :href="data.parent.url"
                            class="hover:text-site-red"
                            >{{ data.parent.label }}</SiteLink
                        >
                        <span v-else>{{ data.parent.label }}</span>
                    </li>
                </template>
                <li aria-hidden="true">/</li>
                <li aria-current="page">{{ pageTitle }}</li>
            </ol>
        </nav>
        <div
            class="mt-8 flex flex-col gap-8 lg:flex-row lg:justify-between"
            :class="panel ? 'lg:mt-[33px]' : 'lg:mt-[33px]'"
        >
            <div>
                <p
                    v-if="data.eyebrow"
                    class="text-[11px] leading-[14px] font-semibold text-site-red uppercase"
                >
                    {{ data.eyebrow }}
                </p>
                <h1
                    class="mt-4 text-[38px] leading-[1.05] font-bold tracking-[-0.02em] whitespace-pre-line text-site-ink sm:text-[48px] lg:mt-[14px] lg:text-[56px] lg:tracking-[-0.5px]"
                    :class="panel ? 'lg:leading-[58px]' : 'lg:leading-[57px]'"
                >
                    {{ data.title }}
                </h1>
                <p
                    v-if="data.description"
                    class="mt-6 max-w-[640px] text-[17px] leading-6 text-site-muted lg:whitespace-pre-line"
                    :class="panel ? 'lg:mt-[17px]' : 'lg:mt-[29px]'"
                >
                    {{ data.description }}
                </p>
            </div>

            <!-- Panel with a red edge (partner page) -->
            <div
                v-if="panel"
                class="border-l-[3px] border-site-red py-0.5 pl-6 lg:mt-[11px] lg:w-[338px] lg:shrink-0 lg:pl-[31px]"
            >
                <p
                    v-if="data.aside_eyebrow"
                    class="text-[11px] leading-[14px] font-semibold text-site-red uppercase lg:mt-0.5"
                >
                    {{ data.aside_eyebrow }}
                </p>
                <p
                    v-if="data.aside_title"
                    class="mt-4 text-[22px] leading-[30px] font-bold whitespace-pre-line text-site-ink sm:text-[24px] lg:mt-[13px]"
                >
                    {{ data.aside_title }}
                </p>
                <p
                    v-if="data.aside_text"
                    class="mt-4 pb-5 text-[15px] leading-[21px] text-site-muted lg:mt-[19px] lg:whitespace-pre-line"
                >
                    {{ data.aside_text }}
                </p>
            </div>

            <!-- Red note (blog page) -->
            <component
                :is="data.aside.url ? SiteLink : 'p'"
                v-else-if="data.aside.label"
                v-bind="data.aside.url ? { href: data.aside.url } : {}"
                class="block max-w-[110px] text-[15px] leading-[22.5px] font-semibold text-site-red uppercase lg:mt-[41px] lg:mr-[109px] lg:w-[110px] lg:max-w-none"
                :class="data.aside.url && 'hover:underline'"
            >
                {{ data.aside.label }}
                <SiteArrow v-if="data.aside.url" class="ml-1.5 text-[14px]" />
            </component>
        </div>
    </div>
</template>
