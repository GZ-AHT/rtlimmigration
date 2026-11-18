import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializeChatWidget } from '@/lib/tawk';

const appName = import.meta.env.VITE_APP_NAME || 'RTL Immigration';

void createInertiaApp({
    // Website pages send a complete title ("Page | Site name") from the server.
    title: (title) =>
        title
            ? title.includes(' | ')
                ? title
                : `${title} - ${appName}`
            : appName,
    layout: (name) => {
        switch (true) {
            case name.startsWith('website/'):
                return WebsiteLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [PanelLayout, SettingsLayout];
            default:
                // admin/, counselor/, accounts/ and portal/ pages
                return PanelLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#e80000',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// Moving between the light-only website and the panels re-applies the theme.
router.on('navigate', (event) => {
    const website = event.detail.page.component.startsWith('website/');

    if (document.documentElement.classList.contains('website') !== website) {
        document.documentElement.classList.toggle('website', website);
        initializeTheme();
    }
});

// This will listen for flash toast data from the server...
initializeFlashToast();

// This will load the live chat widget for visitors and students...
initializeChatWidget();
