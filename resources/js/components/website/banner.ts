import type { LinkValue, SiteImage } from '@/types/website';

/** Fields both homepage banner designs share (App\Support\Cms\SectionTypes $banner). */
export type BannerData = {
    eyebrow: string;
    title: string;
    title_accent: string;
    description: string;
    primary: LinkValue;
    secondary: LinkValue;
    media_type: 'image' | 'video';
    image: SiteImage | null;
    video: { url: string; mime: string } | null;
    overlay: 'gradient' | 'solid' | 'brand' | 'none';
    overlay_opacity: number;
    text_align: 'left' | 'center';
    height: 'standard' | 'compact' | 'tall' | 'screen';
    /** Phones and tablets; missing or 'auto' = as tall as the content. */
    height_tablet?: 'auto' | 'compact' | 'standard' | 'tall' | 'screen';
    height_mobile?: 'auto' | 'compact' | 'standard' | 'tall' | 'screen';
    mobile_video: boolean;
    image_caption: string;
    show_finder: boolean;
    finder_tabs: { label: string; url: string }[];
    finder_help: LinkValue;
};

/** CSS background for the banner overlay (the colour layer over the photo or video). */
export function overlayStyle(
    data: Pick<BannerData, 'overlay' | 'overlay_opacity'>,
): Record<string, string> | null {
    const a = Math.min(90, Math.max(0, data.overlay_opacity)) / 100;

    switch (data.overlay) {
        case 'gradient':
            // Figma: darkest behind the text on the left, lighter on the right.
            return {
                background: `linear-gradient(90deg, rgb(17 19 23 / ${a}) 0%, rgb(17 19 23 / ${a * 0.75}) 45%, rgb(17 19 23 / ${a * 0.35}) 100%)`,
            };
        case 'solid':
            return { background: `rgb(17 19 23 / ${a})` };
        case 'brand':
            return { background: 'var(--color-site-red)', opacity: String(a) };
        default:
            return null;
    }
}

/**
 * Phones and tablets: the text spans the whole width, so the desktop
 * left-to-right fade does not help. A soft top-to-bottom wash keeps white
 * text readable while letting the photo or video show through.
 */
export function mobileOverlayStyle(
    data: Pick<BannerData, 'overlay' | 'overlay_opacity'>,
): Record<string, string> | null {
    if (data.overlay === 'none') {
        return null;
    }

    if (data.overlay === 'brand') {
        return overlayStyle(data);
    }

    const a = Math.min(70, Math.max(0, data.overlay_opacity)) / 100;

    return {
        background: `linear-gradient(180deg, rgb(17 19 23 / ${a * 0.75}) 0%, rgb(17 19 23 / ${a * 0.4}) 45%, rgb(17 19 23 / ${a * 0.85}) 100%)`,
    };
}

// Minimum banner heights per screen size. Full class names, so Tailwind finds them.
const PHONE_HEIGHTS: Record<string, string> = {
    compact: 'max-sm:min-h-[340px]',
    standard: 'max-sm:min-h-[460px]',
    tall: 'max-sm:min-h-[580px]',
    screen: 'max-sm:min-h-[calc(100svh-5.5rem)]',
};
const TABLET_HEIGHTS: Record<string, string> = {
    compact: 'sm:max-lg:min-h-[420px]',
    standard: 'sm:max-lg:min-h-[540px]',
    tall: 'sm:max-lg:min-h-[680px]',
    screen: 'sm:max-lg:min-h-[calc(100svh-7.25rem)]',
};

/** Extra min-height classes for phones and tablets (desktop keeps its own setting). */
export function deviceHeightClasses(
    data: Pick<BannerData, 'height_mobile' | 'height_tablet'>,
): string {
    return [
        PHONE_HEIGHTS[data.height_mobile ?? 'auto'],
        TABLET_HEIGHTS[data.height_tablet ?? 'auto'],
    ]
        .filter(Boolean)
        .join(' ');
}
