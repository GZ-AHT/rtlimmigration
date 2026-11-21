/** Public website (Phase 9b): what the server sends to website pages. */
import type { Option } from '@/types';
import type { LinkValue } from '@/types/cms';

export type SiteImage = {
    url: string;
    alt: string;
    width: number | null;
    height: number | null;
};

export type MenuLink = {
    label: string;
    href: string;
    new_tab: boolean;
    children: MenuLink[];
};

export type SiteMenus = Partial<
    Record<
        | 'main'
        | 'top'
        | 'mobile'
        | 'footer'
        | 'footer_services'
        | 'footer_legal',
        MenuLink[]
    >
>;

export type SectionSettings = {
    background?: 'white' | 'surface' | 'sand' | 'blush' | 'navy';
    spacing?: 'default' | 'compact' | 'joined' | 'none';
    anchor?: string;
    hide_on_mobile?: boolean;
};

/** Section data is defined per type in App\Support\Cms\SectionTypes. */
export type SectionData = Record<string, unknown>;

export type PublicSection = {
    id: string;
    type: string;
    data: SectionData;
    settings: SectionSettings;
    records: Record<string, unknown> | null;
};

export type Seo = {
    title: string;
    description: string;
    canonical: string;
    og_title: string;
    og_description: string;
    image: string | null;
    noindex: boolean;
    type: string;
    robots?: string;
    keywords?: string | null;
    twitter_card?: string;
    twitter_site?: string | null;
    hreflang?: string | null;
    enabled?: boolean;
};

export type Pagination = {
    current: number;
    last: number;
    total: number;
    prev: string | null;
    next: string | null;
    pages: { number: number; url: string }[];
};

export type PostCard = {
    id: number;
    title: string;
    url: string;
    excerpt: string;
    image: SiteImage | null;
    category: { name: string; slug: string } | null;
    /** First tag (e.g. a destination), or else the category. */
    label: string | null;
    date: string | null;
    iso_date: string | null;
    reading_minutes: number;
};

export type TestimonialCard = {
    id: number;
    name: string;
    role: string | null;
    country: string | null;
    quote: string;
    rating: number;
    photo: SiteImage | null;
};

export type UniversityCard = {
    id: number;
    name: string;
    url: string;
    city: string | null;
    country: string | null;
    summary: string;
    logo: SiteImage | null;
    banner: SiteImage | null;
    courses: number;
    matching?: { name: string; level: string; duration: string | null }[];
};

export type CourseCard = {
    id: number;
    name: string;
    level: string;
    subject: string | null;
    duration: string | null;
    tuition: string | null;
    university: { name: string; url: string; country: string | null } | null;
};

export type FaqGroup = {
    name: string;
    slug: string;
    items: { id: number; question: string; answer: string }[];
};

export type { LinkValue, Option };
