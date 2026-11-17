<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ChatLauncher from '@/components/chat/ChatLauncher.vue';
import { Toaster } from '@/components/ui/sonner';
import { home } from '@/routes';

/**
 * Public website shell. Interim version: the full Figma header, navigation
 * and footer (driven by the CMS menus and settings) are built in Phase 9.
 * Text comes from database settings, not hardcoded values (REQ §31.4).
 */
const page = usePage();
const general = computed(
    () =>
        (page.props.site?.general ?? {}) as Record<string, string | undefined>,
);
</script>

<template>
    <div class="min-h-screen bg-white font-site text-site-ink antialiased">
        <div class="bg-site-ink text-xs font-medium text-white">
            <div
                class="mx-auto flex h-[38px] max-w-[1248px] items-center gap-6 px-4 sm:px-6"
            >
                <a
                    v-if="general.contact_email"
                    :href="`mailto:${general.contact_email}`"
                    class="truncate hover:text-site-blush"
                    >{{ general.contact_email }}</a
                >
                <a
                    v-if="general.phone"
                    :href="`tel:${general.phone}`"
                    class="hidden hover:text-site-blush sm:inline"
                    >{{ general.phone }}</a
                >
            </div>
        </div>
        <header class="border-b border-site-line bg-white">
            <div
                class="mx-auto flex h-[94px] max-w-[1248px] items-center px-4 sm:px-6"
            >
                <Link
                    :href="home()"
                    class="flex items-center gap-1.5"
                    :aria-label="general.site_name ?? 'Home'"
                >
                    <span
                        class="text-[43px] leading-none font-bold text-site-red"
                        >RTL</span
                    >
                    <span
                        class="w-[110px] text-[10px] leading-[15px] font-bold uppercase"
                        >{{ general.tagline }}</span
                    >
                </Link>
            </div>
        </header>
        <main>
            <slot />
        </main>
        <footer class="bg-site-navy py-8 text-xs text-site-on-dark">
            <div class="mx-auto max-w-[1248px] px-4 sm:px-6">
                © {{ new Date().getFullYear() }} {{ general.site_name }}.
                {{ general.address }}
            </div>
        </footer>
        <ChatLauncher />
        <Toaster />
    </div>
</template>
