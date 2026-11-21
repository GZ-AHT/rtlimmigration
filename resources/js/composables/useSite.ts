import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { LinkValue } from '@/types/cms';
import type { MenuLink } from '@/types/website';

type Group = Record<string, unknown>;

/**
 * Website settings (Website CMS → Site settings), menus and logo images
 * for the public website. Everything here comes from the database, never
 * from hard-coded copy (REQ §31.4).
 */
export function useSite() {
    const page = usePage();
    const site = computed(() => page.props.site ?? {});

    const text = (group: string, key: string): string => {
        const value = (site.value[group] as Group | undefined)?.[key];

        return typeof value === 'string' ? value : '';
    };

    const flag = (group: string, key: string, fallback = false): boolean => {
        const value = (site.value[group] as Group | undefined)?.[key];

        return typeof value === 'boolean' ? value : fallback;
    };

    const link = (group: string, key: string): LinkValue | null => {
        const value = (site.value[group] as Group | undefined)?.[key] as
            | LinkValue
            | undefined;

        return value?.url && value.label ? value : null;
    };

    const menu = (location: string): MenuLink[] =>
        (page.props.menus as Record<string, MenuLink[]> | undefined)?.[
            location
        ] ?? [];

    const media = computed(() => page.props.siteMedia ?? null);

    const whatsappUrl = computed(() => {
        const digits = text('general', 'whatsapp').replace(/\D/g, '');

        if (!digits || !flag('integrations', 'whatsapp_enabled', true)) {
            return null;
        }

        const message = text('integrations', 'whatsapp_message');

        return `https://wa.me/${digits}${message ? `?text=${encodeURIComponent(message)}` : ''}`;
    });

    return { site, text, flag, link, menu, media, whatsappUrl };
}

/** True when a link points to the page being viewed (for the nav underline). */
export function isCurrent(href: string, url: string): boolean {
    const path = url.split(/[?#]/)[0];

    if (href === '/') {
        return path === '/';
    }

    return path === href || path.startsWith(`${href}/`);
}

/** Internal links use Inertia; external, mailto, tel and #anchor links don't. */
export function isInternal(href: string): boolean {
    return href.startsWith('/') && !href.startsWith('//');
}
