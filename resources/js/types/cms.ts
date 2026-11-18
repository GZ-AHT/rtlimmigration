/** Phase 9 website content types shared by the CMS screens. */
import type { Tone } from '@/types/panel';

export type MediaItem = {
    id: number;
    url: string;
    alt: string | null;
    width: number | null;
    height: number | null;
    name: string;
    size: number;
    kind?: 'image' | 'video';
    mime?: string;
};

export type LinkValue = { label: string; url: string };

export type SeoValue = {
    meta_title: string;
    meta_description: string;
    focus_keyword: string;
    canonical_url: string;
    og_title: string;
    og_description: string;
    og_image: MediaItem | null;
    noindex: boolean;
};

export type PublishState = {
    key: 'draft' | 'scheduled' | 'published';
    label: string;
    tone: Tone;
};

export type FieldDef = {
    type:
        | 'text'
        | 'textarea'
        | 'richtext'
        | 'image'
        | 'video'
        | 'images'
        | 'link'
        | 'toggle'
        | 'number'
        | 'select'
        | 'repeater'
        | 'email'
        | 'url'
        | 'color'
        | 'code';
    label: string;
    max?: number;
    min?: number;
    rows?: number;
    default?: unknown;
    hint?: string;
    required?: boolean;
    options?: Record<string, string>;
    fields?: Record<string, FieldDef>;
};

export type SectionTypeDef = {
    label: string;
    icon: string;
    description: string;
    dynamic?: string;
    fields: Record<string, FieldDef>;
};

export type PageSection = {
    id: string;
    type: string;
    enabled: boolean;
    data: Record<string, unknown>;
    settings: {
        background: string;
        spacing: string;
        anchor: string;
        hide_on_mobile: boolean;
    };
};
