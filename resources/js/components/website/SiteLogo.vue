<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSite } from '@/composables/useSite';

/**
 * Figma wordmark: "RTL" plus the two-line tagline. An uploaded logo
 * (Site settings → Logo & branding) replaces it; the light logo is used on
 * dark backgrounds such as the footer.
 */
const props = withDefaults(defineProps<{ dark?: boolean }>(), {
    dark: false,
});

const { text, media } = useSite();
const name = computed(() => text('general', 'site_name') || 'RTL');
const tagline = computed(() => text('general', 'tagline'));
const image = computed(() =>
    props.dark
        ? (media.value?.logo_light ?? null)
        : (media.value?.logo ?? null),
);
// "RTL Bangladesh" → "RTL": the Figma wordmark shows the first word only.
const mark = computed(() => name.value.split(/\s+/)[0]);
</script>

<template>
    <Link
        href="/"
        translate="no"
        class="notranslate inline-flex shrink-0 items-center gap-1.5"
        :aria-label="`${name}, home`"
    >
        <img
            v-if="image"
            :src="image.url"
            :alt="name"
            :width="image.width ?? undefined"
            :height="image.height ?? undefined"
            class="h-11 w-auto"
        />
        <template v-else-if="dark">
            <span class="flex flex-col">
                <span class="text-[41px] leading-[45px] font-bold text-white">{{
                    mark
                }}</span>
                <span
                    v-if="tagline"
                    class="mt-2 text-[10px] leading-[13px] font-semibold text-white uppercase"
                    >{{ tagline }}</span
                >
            </span>
        </template>
        <template v-else>
            <span
                class="text-[36px] leading-none font-bold text-site-red sm:text-[43px] sm:leading-[47px]"
                >{{ mark }}</span
            >
            <span
                v-if="tagline"
                class="ml-1 w-[84px] text-[9px] leading-[13px] font-bold text-site-ink uppercase sm:w-[92px] sm:text-[10px] sm:leading-[15px]"
                >{{ tagline }}</span
            >
        </template>
    </Link>
</template>
