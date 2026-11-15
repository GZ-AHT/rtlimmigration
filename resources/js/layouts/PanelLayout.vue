<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { ref, watch } from 'vue';
import PanelSidebar from '@/components/panel/PanelSidebar.vue';
import PanelTopbar from '@/components/panel/PanelTopbar.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

/**
 * Shared shell for the admin panel, counselor, accounts and student portal
 * dashboards, recreated from admin-demo.html. Each area gets its own menu
 * from the server; the chrome is the same.
 */
withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const isDesktop = useMediaQuery('(min-width: 1024px)');

// Desktop (>= 1024px): pinned sidebar; remember open/collapsed in the sidebar_state cookie.
// Tablet and phone: an off-canvas drawer that starts closed.
const desktopOpen = ref(page.props.sidebarOpen);
const mobileOpen = ref(false);

watch(isDesktop, () => (mobileOpen.value = false));

function toggleSidebar(): void {
    if (isDesktop.value) {
        desktopOpen.value = !desktopOpen.value;
        document.cookie = `sidebar_state=${desktopOpen.value};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`;
    } else {
        mobileOpen.value = !mobileOpen.value;
    }
}
</script>

<template>
    <div class="min-h-screen bg-background font-sans">
        <a
            href="#panel-content"
            class="sr-only z-50 rounded-md bg-primary px-3 py-2 text-primary-foreground focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
            >Skip to content</a
        >

        <PanelSidebar
            :open="isDesktop ? desktopOpen : mobileOpen"
            @navigate="mobileOpen = false"
        />

        <div
            v-if="!isDesktop && mobileOpen"
            class="fixed inset-0 z-20 bg-black/30"
            aria-hidden="true"
            @click="mobileOpen = false"
        />

        <div
            class="min-w-0 transition-[margin] duration-250"
            :class="isDesktop && desktopOpen ? 'lg:ml-[248px]' : 'ml-0'"
        >
            <PanelTopbar
                :breadcrumbs="breadcrumbs"
                :sidebar-open="isDesktop ? desktopOpen : mobileOpen"
                @toggle-sidebar="toggleSidebar"
            />
            <main
                id="panel-content"
                class="mx-auto max-w-[1600px] px-[15px] pt-[22px] pb-10 md:px-8 md:pt-[30px] md:pb-[50px]"
            >
                <slot />
            </main>
        </div>
        <Toaster />
    </div>
</template>
