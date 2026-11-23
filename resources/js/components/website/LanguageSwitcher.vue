<script setup lang="ts">
import { onMounted, ref } from 'vue';

/**
 * EN | বাংলা switch backed by the hidden Google Translate widget (testing).
 * The choice lives in the `googtrans` cookie, so it carries across Inertia
 * visits and page reloads.
 */
const lang = ref<'en' | 'bn'>('en');

function readCookie(): 'en' | 'bn' {
    return /(?:^|;\s*)googtrans=\/en\/bn/.test(document.cookie) ? 'bn' : 'en';
}

function writeCookie(value: string | null): void {
    const expires = value ? '' : '; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    const host = location.hostname;

    for (const domain of ['', `; domain=${host}`, `; domain=.${host}`]) {
        document.cookie = `googtrans=${value ?? ''}; path=/${domain}${expires}`;
    }
}

function apply(next: 'en' | 'bn', attempts = 0): void {
    const combo = document.querySelector<HTMLSelectElement>('.goog-te-combo');

    if (!combo) {
        // The Google script loads asynchronously; retry for a few seconds.
        if (attempts < 20) {
            setTimeout(() => apply(next, attempts + 1), 300);
        }

        return;
    }

    combo.value = next;
    combo.dispatchEvent(new Event('change'));
}

function choose(next: 'en' | 'bn'): void {
    if (next === lang.value) {
        return;
    }

    lang.value = next;
    writeCookie(next === 'bn' ? '/en/bn' : null);

    if (next === 'en') {
        // Restoring the original text is only reliable with a fresh load.
        location.reload();

        return;
    }

    apply('bn');
}

onMounted(() => {
    lang.value = readCookie();
});
</script>

<template>
    <div
        class="notranslate inline-flex items-center gap-1 font-medium text-white"
        translate="no"
        role="group"
        aria-label="Language"
    >
        <button
            type="button"
            class="rounded px-1 hover:text-site-blush"
            :class="lang === 'en' ? 'font-bold text-site-blush' : 'opacity-80'"
            :aria-pressed="lang === 'en'"
            @click="choose('en')"
        >
            EN
        </button>
        <span aria-hidden="true" class="opacity-50">|</span>
        <button
            type="button"
            class="rounded px-1 hover:text-site-blush"
            :class="lang === 'bn' ? 'font-bold text-site-blush' : 'opacity-80'"
            :aria-pressed="lang === 'bn'"
            @click="choose('bn')"
        >
            বাংলা
        </button>
    </div>
</template>
