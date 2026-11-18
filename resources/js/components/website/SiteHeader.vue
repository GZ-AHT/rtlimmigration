<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import SiteButton from '@/components/website/SiteButton.vue';
import SiteLink from '@/components/website/SiteLink.vue';
import SiteLogo from '@/components/website/SiteLogo.vue';
import { isCurrent, useSite } from '@/composables/useSite';
import type { MenuLink } from '@/types/website';
import SiteArrow from '@/components/website/SiteArrow.vue';

/**
 * Figma "Header" (node 12:5): dark contact strip, wordmark, main
 * navigation with a red underline on the current page, and the
 * "Apply now" button. Links come from Website CMS → Menus; the strip, the
 * login link, the button and stickiness from Site settings → Header.
 */
const page = usePage();
const { text, flag, link, menu } = useSite();

const items = computed(() => menu('main'));
const mobileItems = computed(() =>
    menu('mobile').length ? menu('mobile') : items.value,
);
const cta = computed(() => link('header', 'cta'));
const user = computed(() => page.props.auth?.user ?? null);

const open = ref(false);
const openDropdown = ref<string | null>(null);

const active = (item: MenuLink): boolean =>
    isCurrent(item.href, page.url) ||
    item.children.some((child) => isCurrent(child.href, page.url));

function closeAll(): void {
    open.value = false;
    openDropdown.value = null;
}

// Close the menus when the visitor moves to another page.
watch(() => page.url, closeAll);

function onKey(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeAll();
    }
}

watch(open, (value) => {
    document.body.style.overflow = value ? 'hidden' : '';
    if (value) {
        document.addEventListener('keydown', onKey);
    } else {
        document.removeEventListener('keydown', onKey);
    }
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <header
        class="z-40 bg-white"
        :class="flag('header', 'sticky', true) ? 'sticky top-0' : 'relative'"
        @keydown.esc="openDropdown = null"
    >
        <a
            href="#main"
            class="sr-only z-50 rounded bg-white px-4 py-2 font-semibold focus:not-sr-only focus:absolute focus:top-2 focus:left-2"
            >Skip to content</a
        >

        <!-- Contact and account strip -->
        <div
            v-if="flag('header', 'top_bar', true)"
            class="bg-site-ink text-xs leading-[15.6px]"
        >
            <div
                class="mx-auto flex h-[38px] max-w-[1440px] items-center gap-3 px-4 sm:px-6 lg:px-24"
            >
                <a
                    v-if="text('general', 'contact_email')"
                    :href="`mailto:${text('general', 'contact_email')}`"
                    class="truncate font-medium text-white hover:text-site-blush sm:w-[262px]"
                    >{{ text('general', 'contact_email') }}</a
                >
                <a
                    v-if="text('general', 'phone')"
                    :href="`tel:${text('general', 'phone').replace(/\s/g, '')}`"
                    class="hidden font-medium whitespace-nowrap text-white hover:text-site-blush sm:inline"
                    >{{ text('general', 'phone') }}</a
                >
                <span
                    v-if="text('general', 'address')"
                    class="ml-auto hidden truncate text-site-subtle lg:inline lg:w-[205px]"
                    >{{
                        text('general', 'address')
                            .split(',')
                            .slice(-2)
                            .join(',')
                            .trim()
                    }}</span
                >
                <template v-if="flag('header', 'show_login', true)">
                    <Link
                        v-if="user"
                        href="/dashboard"
                        class="ml-auto font-medium whitespace-pre text-white hover:text-site-blush lg:ml-0"
                        >My account <SiteArrow class="ml-1.5"
                    /></Link>
                    <Link
                        v-else
                        href="/login"
                        class="ml-auto font-medium whitespace-pre text-white hover:text-site-blush lg:ml-0"
                        >Login <SiteArrow class="ml-1.5"
                    /></Link>
                </template>
            </div>
        </div>

        <!-- Brand, navigation, call to action -->
        <div class="border-b border-site-line/70">
            <div
                class="mx-auto flex h-[76px] max-w-[1440px] items-center gap-6 px-4 sm:px-6 lg:h-[94px] lg:px-24"
            >
                <!-- Figma: the logo block spans 96–367px, the nav starts at 438px (47px + the 24px gap). -->
                <SiteLogo class="xl:min-w-[271px]" />

                <nav
                    class="ml-auto hidden h-full xl:ml-[47px] xl:flex"
                    aria-label="Main"
                >
                    <ul class="flex h-full items-center gap-[21px]">
                        <li
                            v-for="item in items"
                            :key="item.label"
                            class="relative flex h-full items-center"
                        >
                            <template v-if="item.children.length">
                                <button
                                    type="button"
                                    class="flex h-full items-center gap-1 px-[3px] text-[14px] leading-[16.8px]"
                                    :class="
                                        active(item)
                                            ? 'font-semibold text-site-red'
                                            : 'font-medium text-site-ink hover:text-site-red'
                                    "
                                    :aria-expanded="openDropdown === item.label"
                                    @click="
                                        openDropdown =
                                            openDropdown === item.label
                                                ? null
                                                : item.label
                                    "
                                >
                                    {{ item.label }}
                                    <AppIcon name="expand_more" :size="18" />
                                </button>
                                <ul
                                    v-show="openDropdown === item.label"
                                    class="absolute top-[calc(100%-14px)] left-0 z-50 min-w-[230px] rounded-[16px] border border-site-warm-line bg-white p-2 shadow-[0_12px_30px_rgba(0,0,0,0.12)]"
                                >
                                    <li
                                        v-for="child in item.children"
                                        :key="child.label"
                                    >
                                        <SiteLink
                                            :href="child.href"
                                            :new-tab="child.new_tab"
                                            class="block rounded-[10px] px-3 py-2.5 text-[14px] font-medium hover:bg-site-sand"
                                            :class="
                                                isCurrent(child.href, page.url)
                                                    ? 'text-site-red'
                                                    : 'text-site-ink'
                                            "
                                            @click="openDropdown = null"
                                            >{{ child.label }}</SiteLink
                                        >
                                    </li>
                                </ul>
                            </template>
                            <SiteLink
                                v-else
                                :href="item.href"
                                :new-tab="item.new_tab"
                                class="flex h-full items-center px-[3px] text-[14px] leading-[16.8px]"
                                :class="
                                    active(item)
                                        ? 'font-semibold text-site-red'
                                        : 'font-medium text-site-ink hover:text-site-red'
                                "
                                :aria-current="
                                    active(item) ? 'page' : undefined
                                "
                                >{{ item.label }}</SiteLink
                            >
                            <span
                                v-if="active(item)"
                                class="absolute bottom-[13px] left-0 h-[3px] w-9 rounded-[1.5px] bg-site-red"
                                aria-hidden="true"
                            />
                        </li>
                    </ul>
                </nav>

                <!-- Wrapped: the button sets its own display, so it can't be hidden directly. -->

                <div v-if="cta" class="hidden xl:ml-auto xl:block">
                    <SiteButton :href="cta.url" class="min-w-[174px]">{{
                        cta.label
                    }}</SiteButton>
                </div>

                <button
                    type="button"
                    class="ml-auto grid size-11 place-items-center rounded-[12px] border border-site-line text-site-ink xl:hidden"
                    :aria-expanded="open"
                    aria-controls="site-mobile-menu"
                    aria-label="Open menu"
                    @click="open = true"
                >
                    <AppIcon name="menu" :size="24" />
                </button>
            </div>
        </div>

        <!-- Mobile menu -->
        <div v-show="open" class="fixed inset-0 z-50 xl:hidden">
            <div
                class="absolute inset-0 bg-site-ink/50"
                aria-hidden="true"
                @click="open = false"
            />
            <div
                id="site-mobile-menu"
                role="dialog"
                aria-modal="true"
                aria-label="Menu"
                class="absolute inset-y-0 right-0 flex w-full max-w-[380px] flex-col overflow-y-auto bg-white"
            >
                <div
                    class="flex h-[76px] items-center justify-between border-b border-site-line px-4"
                >
                    <SiteLogo />
                    <button
                        v-focus="open"
                        type="button"
                        class="grid size-11 place-items-center rounded-[12px] border border-site-line"
                        aria-label="Close menu"
                        @click="open = false"
                    >
                        <AppIcon name="close" :size="24" />
                    </button>
                </div>
                <nav class="flex-1 px-4 py-4" aria-label="Mobile">
                    <ul class="flex flex-col">
                        <li
                            v-for="item in mobileItems"
                            :key="item.label"
                            class="border-b border-site-line last:border-0"
                        >
                            <template v-if="item.children.length">
                                <p
                                    class="pt-4 pb-2 text-[11px] font-semibold tracking-[0.06em] text-site-muted uppercase"
                                >
                                    {{ item.label }}
                                </p>
                                <ul class="pb-3">
                                    <li
                                        v-for="child in item.children"
                                        :key="child.label"
                                    >
                                        <SiteLink
                                            :href="child.href"
                                            :new-tab="child.new_tab"
                                            class="block py-2.5 text-[16px] font-medium"
                                            :class="
                                                isCurrent(child.href, page.url)
                                                    ? 'text-site-red'
                                                    : 'text-site-ink'
                                            "
                                            >{{ child.label }}</SiteLink
                                        >
                                    </li>
                                </ul>
                            </template>
                            <SiteLink
                                v-else
                                :href="item.href"
                                :new-tab="item.new_tab"
                                class="block py-4 text-[17px] font-semibold"
                                :class="
                                    active(item)
                                        ? 'text-site-red'
                                        : 'text-site-ink'
                                "
                                >{{ item.label }}</SiteLink
                            >
                        </li>
                    </ul>
                </nav>
                <div class="flex flex-col gap-3 border-t border-site-line p-4">
                    <SiteButton v-if="cta" :href="cta.url">{{
                        cta.label
                    }}</SiteButton>
                    <Link
                        v-if="flag('header', 'show_login', true)"
                        :href="user ? '/dashboard' : '/login'"
                        class="flex h-[50px] items-center justify-center rounded-site-btn border border-site-line text-[14px] font-semibold text-site-ink"
                        >{{ user ? 'My account' : 'Login' }}</Link
                    >
                </div>
            </div>
        </div>
    </header>
</template>
