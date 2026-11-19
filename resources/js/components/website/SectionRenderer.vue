<script setup lang="ts">
import type { Component } from 'vue';
import AboutSection from './sections/AboutSection.vue';
import BlogSection from './sections/BlogSection.vue';
import ContactHeroSection from './sections/ContactHeroSection.vue';
import ContactSection from './sections/ContactSection.vue';
import CountriesSection from './sections/CountriesSection.vue';
import CoursesSection from './sections/CoursesSection.vue';
import CtaSection from './sections/CtaSection.vue';
import FaqSection from './sections/FaqSection.vue';
import FeaturesSection from './sections/FeaturesSection.vue';
import GallerySection from './sections/GallerySection.vue';
import HeroCoverSection from './sections/HeroCoverSection.vue';
import HeroSection from './sections/HeroSection.vue';
import ImageTextSection from './sections/ImageTextSection.vue';
import LogosSection from './sections/LogosSection.vue';
import MessageSection from './sections/MessageSection.vue';
import MissionSection from './sections/MissionSection.vue';
import NewsletterSection from './sections/NewsletterSection.vue';
import PageHeroSection from './sections/PageHeroSection.vue';
import PageIntroSection from './sections/PageIntroSection.vue';
import PartnerFormSection from './sections/PartnerFormSection.vue';
import PathwaysSection from './sections/PathwaysSection.vue';
import RichTextSection from './sections/RichTextSection.vue';
import SkillSearchSection from './sections/SkillSearchSection.vue';
import StatsSection from './sections/StatsSection.vue';
import StepsSection from './sections/StepsSection.vue';
import StorySection from './sections/StorySection.vue';
import TestimonialsSection from './sections/TestimonialsSection.vue';
import UniversitiesSection from './sections/UniversitiesSection.vue';
import VideoSection from './sections/VideoSection.vue';
import VisitSection from './sections/VisitSection.vue';
import type { PublicSection } from '@/types/website';

/**
 * Renders a CMS page's sections (types from App\Support\Cms\SectionTypes)
 * with each section's background, spacing, anchor and "hide on phones".
 */
defineProps<{ sections: PublicSection[] }>();

const components: Record<string, Component> = {
    hero: HeroSection,
    hero_cover: HeroCoverSection,
    pathways: PathwaysSection,
    stats: StatsSection,
    about: AboutSection,
    testimonials: TestimonialsSection,
    video: VideoSection,
    blog: BlogSection,
    faq: FaqSection,
    universities: UniversitiesSection,
    countries: CountriesSection,
    courses: CoursesSection,
    logos: LogosSection,
    newsletter: NewsletterSection,
    contact: ContactSection,
    partner_form: PartnerFormSection,
    cta: CtaSection,
    image_text: ImageTextSection,
    rich_text: RichTextSection,
    page_hero: PageHeroSection,
    story: StorySection,
    mission: MissionSection,
    features: FeaturesSection,
    gallery: GallerySection,
    message: MessageSection,
    contact_hero: ContactHeroSection,
    page_intro: PageIntroSection,
    steps: StepsSection,
    skill_search: SkillSearchSection,
    visit: VisitSection,
};

// These draw their own padding and background (full-bleed bands).
const SELF_SPACED = [
    'hero',
    'hero_cover',
    'page_hero',
    'page_intro',
    'contact_hero',
    'cta',
];

const BACKGROUNDS: Record<string, string> = {
    white: 'bg-white',
    surface: 'bg-site-surface',
    sand: 'bg-site-sand',
    blush: 'bg-site-blush-bg',
    navy: 'bg-site-navy',
};

const SPACING: Record<string, string> = {
    default: 'py-14 lg:py-[60px]',
    compact: 'py-10 lg:py-[44px]',
    // Continues the section above on the same background (Figma Contact page).
    joined: 'pt-0 pb-14 lg:pb-[44px]',
    none: 'py-0',
};

const JOINED: Record<string, string> = {
    visit: 'pt-0 pb-12 lg:pb-[14px]',
};

// Figma homepage frames pad these sections unevenly; "Normal" spacing
// follows them on desktop (measured from the 1440px export).
const FIGMA_PADDING: Record<string, string> = {
    pathways: 'py-14 lg:pt-[58px] lg:pb-[50px]',
    testimonials: 'py-14 lg:py-[44px]',
    video: 'py-14 lg:pt-16 lg:pb-[27px]',
    blog: 'py-14 lg:pt-[59px] lg:pb-[31px]',
    faq: 'py-14 lg:pt-[62px] lg:pb-[77px]',
    // Figma Blog page.
    newsletter: 'py-14 lg:pt-[44px] lg:pb-[58px]',
    // Owner's reference "Partner application.png".
    partner_form: 'py-14 lg:pt-[55px] lg:pb-[70px]',
    steps: 'py-12 lg:pt-[39px] lg:pb-[94px]',
};

// The full blog listing (Figma Blog page) sits 32px under the page header.
const BLOG_LISTING = 'py-10 lg:pt-8 lg:pb-12';

function padding(section: PublicSection): string {
    const spacing = section.settings.spacing ?? 'default';

    if (SELF_SPACED.includes(section.type)) {
        return '';
    }

    if (spacing === 'default' && section.type === 'blog' && section.data.all) {
        return BLOG_LISTING;
    }

    if (spacing === 'joined' && JOINED[section.type]) {
        return JOINED[section.type];
    }

    return spacing === 'default' && FIGMA_PADDING[section.type]
        ? FIGMA_PADDING[section.type]
        : (SPACING[spacing] ?? SPACING.default);
}

function classes(section: PublicSection): string[] {
    const s = section.settings;

    return [
        BACKGROUNDS[s.background ?? 'white'] ?? 'bg-white',
        padding(section),
        s.hide_on_mobile ? 'hidden md:block' : '',
        'scroll-mt-28',
    ];
}
</script>

<template>
    <template v-for="section in sections" :key="section.id">
        <section
            v-if="components[section.type]"
            :id="section.settings.anchor || undefined"
            :class="classes(section)"
            :data-section="section.type"
            class="w-full overflow-x-clip"
        >
            <component
                :is="components[section.type]"
                :data="section.data"
                :records="section.records"
                :dark="section.settings.background === 'navy'"
                v-bind="
                    section.type === 'features'
                        ? { background: section.settings.background }
                        : {}
                "
            />
        </section>
    </template>
</template>
