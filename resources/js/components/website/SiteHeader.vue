<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
const { text, flag, link, menu, whatsappUrl } = useSite();

const items = computed(() => menu('main'));
const mobileItems = computed(() =>
    menu('mobile').length ? menu('mobile') : items.value,
);
const cta = computed(() => link('header', 'cta'));
const user = computed(() => page.props.auth?.user ?? null);

const open = ref(false);
const openDropdown = ref<string | null>(null);
const openMobileSubmenu = ref<Record<string, boolean>>({});

function toggleMobileSubmenu(label: string): void {
    openMobileSubmenu.value[label] = !openMobileSubmenu.value[label];
}

const active = (item: MenuLink): boolean =>
    isCurrent(item.href, page.url) ||
    item.children.some((child) => isCurrent(child.href, page.url));

function closeAll(): void {
    open.value = false;
    openDropdown.value = null;
}

function getMenuIcon(label: string): string {
    const l = label.toLowerCase();
    if (l.includes('home')) return 'home';
    if (l.includes('visa') || l.includes('immigration'))
        return 'flight_takeoff';
    if (
        l.includes('study') ||
        l.includes('universit') ||
        l.includes('course') ||
        l.includes('education')
    )
        return 'school';
    if (l.includes('skill')) return 'work';
    if (l.includes('about')) return 'info';
    if (l.includes('contact')) return 'support_agent';
    if (
        l.includes('blog') ||
        l.includes('news') ||
        l.includes('journal') ||
        l.includes('insight')
    )
        return 'article';
    if (l.includes('partner')) return 'handshake';
    if (l.includes('service')) return 'apps';
    if (l.includes('faq')) return 'help_outline';
    return 'arrow_forward';
}

// Close the menus when the visitor moves to another page.
watch(() => page.url, closeAll);

function onKey(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeAll();
    }
}

/**
 * Phones only (under 640px): a fixed glass header that folds into a floating
 * button while scrolling down and returns when scrolling up or at the top.
 * 'top' = original layout, 'shown' = nav bar only (strip tucked away),
 * 'hidden' = collapsed to the floating button. Only transforms and opacity
 * change, and a spacer keeps the page from jumping.
 */
const smart = computed(() => flag('header', 'sticky', true));
const hasStrip = computed(() => flag('header', 'top_bar', true));
const scrollMode = ref<'top' | 'shown' | 'hidden'>('top');
const collapsed = computed(() => smart.value && scrollMode.value === 'hidden');
let lastY = 0;
let frame = 0;
let phone: MediaQueryList | null = null;

function updateScroll(): void {
    frame = 0;

    if (!phone?.matches || open.value) {
        lastY = window.scrollY;

        return;
    }

    const y = Math.max(0, window.scrollY);
    const delta = y - lastY;

    if (y <= 8) {
        scrollMode.value = 'top';
    } else if (delta > 4) {
        scrollMode.value = 'hidden';
    } else if (delta < -4) {
        scrollMode.value = 'shown';
    }

    if (Math.abs(delta) > 4 || y <= 8) {
        lastY = y;
    }
}

function onScroll(): void {
    if (!frame) {
        frame = window.requestAnimationFrame(updateScroll);
    }
}

onMounted(() => {
    phone = window.matchMedia('(max-width: 639px)');
    lastY = window.scrollY;
    scrollMode.value = lastY > 8 ? 'hidden' : 'top';
    window.addEventListener('scroll', onScroll, { passive: true });
});

watch(open, (value) => {
    document.body.style.overflow = value ? 'hidden' : '';
    if (value) {
        document.addEventListener('keydown', onKey);
    } else {
        document.removeEventListener('keydown', onKey);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.cancelAnimationFrame(frame);
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <!-- Keeps the page from jumping: the phone header is fixed, so this holds its place. -->
    <div
        v-if="smart"
        class="sm:hidden"
        :class="hasStrip ? 'h-22' : 'h-14'"
        aria-hidden="true"
    />
    <header
        class="z-40 bg-white"
        :class="[
            smart ? 'sticky top-0' : 'relative',
            smart &&
                'max-sm:fixed max-sm:inset-x-0 max-sm:top-0 max-sm:bg-white/80 max-sm:shadow-[0_8px_24px_rgba(15,23,42,0.1)] max-sm:ring-1 max-sm:ring-white/60 max-sm:backdrop-blur-[20px] max-sm:backdrop-saturate-150 max-sm:transition-[transform,opacity] max-sm:duration-300 max-sm:ease-[cubic-bezier(0.16,1,0.3,1)] max-sm:ring-inset motion-reduce:max-sm:transition-none',
            smart &&
                scrollMode === 'shown' &&
                hasStrip &&
                'max-sm:-translate-y-8',
            collapsed &&
                'max-sm:pointer-events-none max-sm:-translate-y-[120%] max-sm:opacity-0',
        ]"
        :inert="collapsed ? true : undefined"
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
                class="mx-auto flex h-9.5 max-w-360 items-center gap-3 px-4 max-sm:h-8 max-sm:px-3 sm:px-6 lg:px-24"
            >
                <a
                    v-if="text('general', 'contact_email')"
                    :href="`mailto:${text('general', 'contact_email')}`"
                    class="flex items-center gap-1.5 truncate font-medium text-white hover:text-site-blush sm:w-65.5"
                >
                    <AppIcon
                        name="mail"
                        :size="14"
                        class="shrink-0 text-site-red"
                    />
                    <span class="truncate">{{
                        text('general', 'contact_email')
                    }}</span>
                </a>
                <a
                    v-if="text('general', 'phone')"
                    :href="`tel:${text('general', 'phone').replace(/\s/g, '')}`"
                    class="hidden items-center gap-1.5 font-medium whitespace-nowrap text-white hover:text-site-blush sm:inline-flex"
                >
                    <AppIcon
                        name="call"
                        :size="14"
                        class="shrink-0 text-site-red"
                    />
                    <span>{{ text('general', 'phone') }}</span>
                </a>
                <span
                    v-if="text('general', 'address')"
                    class="ml-auto hidden items-center gap-1.5 truncate text-site-subtle lg:inline-flex lg:w-55"
                >
                    <AppIcon
                        name="location_on"
                        :size="14"
                        class="shrink-0 text-site-red"
                    />
                    <span class="truncate">{{
                        text('general', 'address')
                            .split(',')
                            .slice(-2)
                            .join(',')
                            .trim()
                    }}</span>
                </span>
                <template v-if="flag('header', 'show_login', true)">
                    <Link
                        v-if="user"
                        href="/dashboard"
                        class="ml-auto inline-flex items-center gap-1.5 font-medium whitespace-pre text-white hover:text-site-blush lg:ml-0"
                    >
                        <AppIcon
                            name="account_circle"
                            :size="15"
                            class="shrink-0 text-site-red"
                        />
                        <span>My account</span>
                        <SiteArrow class="ml-1" />
                    </Link>
                    <Link
                        v-else
                        href="/login"
                        class="ml-auto inline-flex items-center gap-1.5 font-medium whitespace-pre text-white hover:text-site-blush lg:ml-0"
                    >
                        <AppIcon
                            name="login"
                            :size="15"
                            class="shrink-0 text-site-red"
                        />
                        <span>Login</span>
                        <SiteArrow class="ml-1" />
                    </Link>
                </template>
            </div>
        </div>

        <!-- Brand, navigation, call to action -->
        <div class="border-b border-site-line/70 max-sm:border-b-0">
            <div
                class="mx-auto flex h-19 max-w-360 items-center gap-6 px-4 max-sm:h-14 max-sm:gap-3 max-sm:px-3 sm:px-6 lg:h-23.5 lg:px-24"
            >
                <!-- Figma: the logo block spans 96–367px, the nav starts at 438px (47px + the 24px gap). -->
                <SiteLogo class="xl:min-w-67.75" />

                <nav
                    class="ml-auto hidden h-full xl:ml-11.75 xl:flex"
                    aria-label="Main"
                >
                    <ul class="flex h-full items-center gap-5.25">
                        <li
                            v-for="item in items"
                            :key="item.label"
                            class="relative flex h-full items-center"
                        >
                            <template v-if="item.children.length">
                                <button
                                    type="button"
                                    class="flex h-full items-center gap-1 px-0.75 text-[14px] leading-[16.8px]"
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
                                    class="absolute top-[calc(100%-14px)] left-0 z-50 min-w-57.5 rounded-2xl border border-site-warm-line bg-white p-2 shadow-[0_12px_30px_rgba(0,0,0,0.12)]"
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
                                class="flex h-full items-center px-0.75 text-[14px] leading-[16.8px]"
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
                                class="absolute bottom-3.25 left-0 h-0.75 w-9 rounded-[1.5px] bg-site-red"
                                aria-hidden="true"
                            />
                        </li>
                    </ul>
                </nav>

                <!-- Wrapped: the button sets its own display, so it can't be hidden directly. -->

                <div v-if="cta" class="hidden xl:ml-auto xl:block">
                    <SiteButton :href="cta.url" class="min-w-43.5">{{
                        cta.label
                    }}</SiteButton>
                </div>

                <!-- Modern animated hamburger toggle -->
                <button
                    type="button"
                    class="relative ml-auto flex size-11 items-center justify-center rounded-xl border border-site-line text-site-ink transition-colors hover:border-site-red hover:text-site-red focus-visible:ring-2 focus-visible:ring-site-red/30 focus-visible:outline-none max-sm:size-10 max-sm:bg-white/50 xl:hidden"
                    :aria-expanded="open"
                    aria-controls="site-mobile-menu"
                    :aria-label="open ? 'Close menu' : 'Open menu'"
                    @click="open = !open"
                >
                    <span
                        class="relative flex h-3.5 w-5 flex-col justify-between"
                        aria-hidden="true"
                    >
                        <span
                            class="h-0.5 w-full origin-center rounded-full bg-current transition-all duration-300 ease-in-out"
                            :class="open ? 'translate-y-[6px] rotate-45' : ''"
                        />
                        <span
                            class="h-0.5 w-full rounded-full bg-current transition-all duration-200 ease-in-out"
                            :class="
                                open ? 'scale-x-0 opacity-0' : 'opacity-100'
                            "
                        />
                        <span
                            class="h-0.5 w-full origin-center rounded-full bg-current transition-all duration-300 ease-in-out"
                            :class="open ? '-translate-y-[6px] -rotate-45' : ''"
                        />
                    </span>
                </button>
            </div>
        </div>

        <!-- Modern Mobile Navigation Drawer -->
        <Teleport to="body">
            <div v-show="open" class="fixed inset-0 z-50 xl:hidden">
                <!-- Backdrop with smooth fade -->
                <Transition
                    enter-active-class="transition-opacity duration-300 ease-out"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition-opacity duration-200 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="open"
                        class="fixed inset-0 bg-site-ink/60 backdrop-blur-xs"
                        aria-hidden="true"
                        @click="closeAll"
                    />
                </Transition>

                <!-- Drawer panel with smooth slide-in -->
                <Transition
                    enter-active-class="transition-[transform,opacity] duration-300 cubic-bezier(0.16, 1, 0.3, 1)"
                    enter-from-class="translate-x-full max-sm:translate-x-0 max-sm:scale-90 max-sm:opacity-0"
                    enter-to-class="translate-x-0 max-sm:scale-100 max-sm:opacity-100"
                    leave-active-class="transition-[transform,opacity] duration-250 ease-in"
                    leave-from-class="translate-x-0 max-sm:scale-100 max-sm:opacity-100"
                    leave-to-class="translate-x-full max-sm:translate-x-0 max-sm:scale-90 max-sm:opacity-0"
                >
                    <div
                        v-if="open"
                        id="site-mobile-menu"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Navigation Menu"
                        class="fixed inset-y-0 right-0 flex w-full max-w-[340px] flex-col bg-white shadow-2xl max-sm:inset-y-auto max-sm:top-3 max-sm:right-3 max-sm:left-3 max-sm:max-h-[calc(100dvh-1.5rem)] max-sm:w-auto max-sm:max-w-none max-sm:origin-top-right max-sm:overflow-hidden max-sm:rounded-3xl max-sm:bg-white/85 max-sm:ring-1 max-sm:ring-white/60 max-sm:backdrop-blur-[20px] max-sm:backdrop-saturate-150 sm:max-w-sm"
                    >
                        <!-- Top drawer header -->
                        <div
                            class="flex h-18 items-center justify-between border-b border-site-line/80 px-5 max-sm:h-14 max-sm:px-4"
                        >
                            <SiteLogo @click="closeAll" />
                            <button
                                v-focus="open"
                                type="button"
                                class="grid size-10 place-items-center rounded-xl border border-site-line text-site-ink transition-colors hover:border-site-red hover:text-site-red active:scale-95"
                                aria-label="Close menu"
                                @click="closeAll"
                            >
                                <AppIcon name="close" :size="22" />
                            </button>
                        </div>

                        <!-- Navigation items with icons and accordions -->
                        <nav
                            class="no-scrollbar flex-1 overflow-y-auto px-4 py-4 max-sm:px-3 max-sm:py-3"
                            aria-label="Mobile Navigation"
                        >
                            <ul class="flex flex-col gap-1.5">
                                <li
                                    v-for="item in mobileItems"
                                    :key="item.label"
                                    class="rounded-xl transition-colors"
                                >
                                    <!-- Accordion item if has children -->
                                    <template v-if="item.children.length">
                                        <div class="flex flex-col">
                                            <button
                                                type="button"
                                                class="flex min-h-12 w-full items-center justify-between rounded-xl px-3.5 py-3 text-left text-[15px] font-semibold transition-colors"
                                                :class="
                                                    active(item)
                                                        ? 'bg-site-red/5 text-site-red'
                                                        : 'text-site-ink hover:bg-site-surface'
                                                "
                                                :aria-expanded="
                                                    openMobileSubmenu[
                                                        item.label
                                                    ] ?? active(item)
                                                "
                                                @click="
                                                    toggleMobileSubmenu(
                                                        item.label,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="flex items-center gap-3"
                                                >
                                                    <span
                                                        class="grid size-8 shrink-0 place-items-center rounded-lg"
                                                        :class="
                                                            active(item)
                                                                ? 'bg-site-red/10 text-site-red'
                                                                : 'bg-site-surface text-site-muted'
                                                        "
                                                    >
                                                        <AppIcon
                                                            :name="
                                                                getMenuIcon(
                                                                    item.label,
                                                                )
                                                            "
                                                            :size="18"
                                                        />
                                                    </span>
                                                    <span>{{
                                                        item.label
                                                    }}</span>
                                                </span>
                                                <AppIcon
                                                    name="expand_more"
                                                    :size="20"
                                                    class="shrink-0 text-site-muted transition-transform duration-200"
                                                    :class="
                                                        (openMobileSubmenu[
                                                            item.label
                                                        ] ??
                                                            active(item)) &&
                                                        'rotate-180 text-site-red'
                                                    "
                                                />
                                            </button>

                                            <!-- Collapsible Submenu list -->
                                            <ul
                                                v-show="
                                                    openMobileSubmenu[
                                                        item.label
                                                    ] ?? active(item)
                                                "
                                                class="my-1 ml-6 flex flex-col gap-1 border-l-2 border-site-warm-line/80 pl-3"
                                            >
                                                <li
                                                    v-for="child in item.children"
                                                    :key="child.label"
                                                >
                                                    <SiteLink
                                                        :href="child.href"
                                                        :new-tab="child.new_tab"
                                                        class="flex min-h-11 items-center gap-2.5 rounded-lg px-3 py-2 text-[14px] transition-colors"
                                                        :class="
                                                            isCurrent(
                                                                child.href,
                                                                page.url,
                                                            )
                                                                ? 'bg-site-red/10 font-semibold text-site-red'
                                                                : 'font-medium text-site-ink hover:bg-site-surface'
                                                        "
                                                        @click="closeAll"
                                                    >
                                                        <span
                                                            class="size-1.5 shrink-0 rounded-full"
                                                            :class="
                                                                isCurrent(
                                                                    child.href,
                                                                    page.url,
                                                                )
                                                                    ? 'bg-site-red'
                                                                    : 'bg-site-muted/50'
                                                            "
                                                        />
                                                        <span>{{
                                                            child.label
                                                        }}</span>
                                                    </SiteLink>
                                                </li>
                                            </ul>
                                        </div>
                                    </template>

                                    <!-- Single navigation link -->
                                    <template v-else>
                                        <SiteLink
                                            :href="item.href"
                                            :new-tab="item.new_tab"
                                            class="flex min-h-12 items-center justify-between rounded-xl px-3.5 py-3 text-[15px] font-semibold transition-colors"
                                            :class="
                                                active(item)
                                                    ? 'bg-site-red/5 text-site-red'
                                                    : 'text-site-ink hover:bg-site-surface'
                                            "
                                            @click="closeAll"
                                        >
                                            <span
                                                class="flex items-center gap-3"
                                            >
                                                <span
                                                    class="grid size-8 shrink-0 place-items-center rounded-lg"
                                                    :class="
                                                        active(item)
                                                            ? 'bg-site-red/10 text-site-red'
                                                            : 'bg-site-surface text-site-muted'
                                                    "
                                                >
                                                    <AppIcon
                                                        :name="
                                                            getMenuIcon(
                                                                item.label,
                                                            )
                                                        "
                                                        :size="18"
                                                    />
                                                </span>
                                                <span>{{ item.label }}</span>
                                            </span>
                                            <SiteArrow
                                                v-if="active(item)"
                                                class="text-site-red"
                                            />
                                        </SiteLink>
                                    </template>
                                </li>
                            </ul>
                        </nav>

                        <!-- Drawer Footer with CTAs & Quick Contacts -->
                        <div
                            class="border-t border-site-line/80 bg-site-surface/50 p-4"
                        >
                            <div class="flex flex-col gap-2.5">
                                <SiteButton
                                    v-if="cta"
                                    :href="cta.url"
                                    class="w-full justify-center text-center shadow-xs"
                                    @click="closeAll"
                                >
                                    {{ cta.label }}
                                </SiteButton>

                                <Link
                                    v-if="flag('header', 'show_login', true)"
                                    :href="user ? '/dashboard' : '/login'"
                                    class="flex h-11 items-center justify-center gap-2 rounded-site-btn border border-site-line bg-white text-sm font-semibold text-site-ink shadow-xs transition-colors hover:border-site-red hover:text-site-red"
                                    @click="closeAll"
                                >
                                    <AppIcon
                                        :name="
                                            user ? 'account_circle' : 'login'
                                        "
                                        :size="18"
                                        class="text-site-red"
                                    />
                                    <span>{{
                                        user ? 'My account' : 'Sign in'
                                    }}</span>
                                </Link>
                            </div>

                            <!-- Direct contact strip -->
                            <div
                                class="mt-4 flex items-center justify-center gap-4 border-t border-site-line/60 pt-3 text-xs text-site-muted"
                            >
                                <a
                                    v-if="text('general', 'phone')"
                                    :href="`tel:${text('general', 'phone').replace(/\s/g, '')}`"
                                    class="inline-flex min-h-11 items-center gap-1.5 hover:text-site-red"
                                >
                                    <AppIcon
                                        name="call"
                                        :size="14"
                                        class="text-site-red"
                                    />
                                    <span>Call</span>
                                </a>
                                <span
                                    v-if="
                                        text('general', 'phone') &&
                                        text('general', 'contact_email')
                                    "
                                    class="text-site-line"
                                    >•</span
                                >
                                <a
                                    v-if="text('general', 'contact_email')"
                                    :href="`mailto:${text('general', 'contact_email')}`"
                                    class="inline-flex min-h-11 items-center gap-1.5 hover:text-site-red"
                                >
                                    <AppIcon
                                        name="mail"
                                        :size="14"
                                        class="text-site-red"
                                    />
                                    <span>Email</span>
                                </a>
                                <template v-if="whatsappUrl">
                                    <span class="text-site-line">•</span>
                                    <a
                                        :href="whatsappUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex min-h-11 items-center gap-1.5 font-medium text-site-whatsapp hover:underline"
                                    >
                                        <span>WhatsApp</span>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Teleport>
    </header>

    <!-- Collapsed state (phones): a small glass button that opens the same menu. -->
    <button
        v-if="smart"
        type="button"
        class="fixed top-[max(0.75rem,env(safe-area-inset-top))] right-3 z-40 grid size-12 place-items-center rounded-full bg-white/80 text-site-ink shadow-[0_8px_24px_rgba(15,23,42,0.18)] ring-1 ring-white/60 backdrop-blur-[20px] backdrop-saturate-150 transition-[transform,opacity] duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] ring-inset active:scale-90 motion-reduce:transition-none sm:hidden"
        :class="
            collapsed && !open
                ? 'scale-100 opacity-100'
                : 'pointer-events-none scale-50 opacity-0'
        "
        :tabindex="collapsed && !open ? 0 : -1"
        :aria-hidden="collapsed && !open ? undefined : 'true'"
        :aria-expanded="open"
        aria-controls="site-mobile-menu"
        aria-label="Open menu"
        @click="open = true"
    >
        <AppIcon name="menu" :size="24" />
    </button>
</template>
