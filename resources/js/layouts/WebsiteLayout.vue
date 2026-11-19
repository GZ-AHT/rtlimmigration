<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import SiteFooter from '@/components/website/SiteFooter.vue';
import SiteHeader from '@/components/website/SiteHeader.vue';
import SiteLaunchers from '@/components/website/SiteLaunchers.vue';
import { Toaster } from '@/components/ui/sonner';
import { useSite } from '@/composables/useSite';

/**
 * Public website shell (Figma RTLIMMIG): header, page, footer and the
 * WhatsApp / live chat buttons. Brand colours, fonts and button corners
 * come from Site settings → Logo & branding and Theme (REQ §21.5, §21.6).
 */
const { text, media } = useSite();

// Fonts bundled with the app; any other choice loads from Bunny Fonts.
const BUNDLED = ['Inter', 'DM Sans', 'Manrope'];
const RADIUS: Record<string, string> = {
    rounded: '14px',
    pill: '999px',
    square: '4px',
};

const headingFont = computed(() => text('branding', 'heading_font') || 'Inter');
const bodyFont = computed(() => text('branding', 'body_font') || 'Inter');

const fontHref = computed(() => {
    const extra = [...new Set([headingFont.value, bodyFont.value])].filter(
        (font) => !BUNDLED.includes(font),
    );

    return extra.length
        ? `https://fonts.bunny.net/css?family=${extra
              .map(
                  (f) =>
                      `${f.toLowerCase().replace(/\s+/g, '-')}:400,500,600,700`,
              )
              .join('|')}&display=swap`
        : null;
});

/** Darkens a #rrggbb colour for hover states. */
function darken(hex: string, amount = 0.16): string {
    const n = parseInt(hex.slice(1), 16);
    const c = [n >> 16, (n >> 8) & 255, n & 255].map((v) =>
        Math.round(v * (1 - amount)),
    );

    return `#${c.map((v) => v.toString(16).padStart(2, '0')).join('')}`;
}

const theme = computed(() => {
    const vars: Record<string, string> = {
        '--site-body-font': `'${bodyFont.value}'`,
        '--site-heading-font': `'${headingFont.value}'`,
        '--site-btn-radius': RADIUS[text('theme', 'button_style')] ?? '14px',
    };
    const primary = text('branding', 'primary_color');
    const secondary = text('branding', 'secondary_color');

    if (/^#[0-9a-f]{6}$/i.test(primary)) {
        vars['--site-primary'] = primary;
        vars['--site-primary-dark'] = darken(primary);
    }

    if (/^#[0-9a-f]{6}$/i.test(secondary)) {
        vars['--site-secondary'] = secondary;
    }

    return vars;
});
</script>

<template>
    <Head>
        <link
            v-if="media?.favicon"
            head-key="favicon"
            rel="icon"
            :href="media.favicon.url"
        />
        <link
            v-if="media?.apple_icon"
            head-key="apple-icon"
            rel="apple-touch-icon"
            :href="media.apple_icon.url"
        />
        <link
            v-if="fontHref"
            head-key="site-fonts"
            rel="stylesheet"
            :href="fontHref"
        />
    </Head>
    <div
        class="flex min-h-screen flex-col overflow-x-hidden bg-white font-site text-site-ink antialiased [&_h1]:font-site-heading [&_h2]:font-site-heading [&_h3]:font-site-heading"
        :style="theme"
    >
        <SiteHeader />
        <main
            id="main"
            class="w-full min-w-0 flex-1 overflow-x-hidden"
            tabindex="-1"
        >
            <slot />
        </main>
        <SiteFooter />
        <SiteLaunchers />
        <Toaster />
    </div>
</template>
