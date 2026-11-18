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
