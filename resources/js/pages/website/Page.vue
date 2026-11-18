<script setup lang="ts">
import { onBeforeUnmount, onMounted, provide } from 'vue';
import SectionRenderer from '@/components/website/SectionRenderer.vue';
import SeoHead from '@/components/website/SeoHead.vue';
import type { PublicSection, Seo } from '@/types/website';

/**
 * A CMS page (REQ §5, §21.7, §21.8): the homepage, built-in pages and
 * custom pages, all made of sections. Per-page custom CSS/JS (Super Admin
 * only, logged: roadmap Q16) is added while the page is open.
 */
const props = defineProps<{
    page: {
        title: string;
        slug: string;
        system_key: string | null;
        preview: 'draft' | 'scheduled' | null;
        edit_url: string | null;
        banner_preview: 'hero' | 'hero_cover' | null;
        custom_css: string;
        custom_js: string;
    };
    sections: PublicSection[];
    seo: Seo;
}>();

provide('pageTitle', props.page.title);

let script: HTMLScriptElement | null = null;

onMounted(() => {
    if (props.page.custom_js) {
        script = document.createElement('script');
        script.textContent = props.page.custom_js;
        document.body.appendChild(script);
    }
});

onBeforeUnmount(() => script?.remove());
</script>

<template>
    <SeoHead :seo="seo" />
    <component :is="'style'" v-if="page.custom_css">{{
        page.custom_css
    }}</component>

    <div
        v-if="page.banner_preview"
        class="bg-site-red text-[13px] text-white"
        role="status"
    >
        <div
            class="site-container flex flex-wrap items-center justify-between gap-2 py-2"
        >
            <span>
                Preview: the
                {{
                    page.banner_preview === 'hero_cover'
                        ? 'new full-width'
                        : 'existing'
                }}
                banner, as last saved. Visitors see the banner chosen in the
                Homepage editor.
            </span>
            <a
                v-if="page.edit_url"
                :href="page.edit_url"
                class="font-semibold underline"
                >Back to the editor</a
            >
        </div>
    </div>
    <div
        v-if="page.preview || page.edit_url"
        class="bg-site-ink text-[13px] text-white"
    >
        <div
            class="site-container flex flex-wrap items-center justify-between gap-2 py-2"
        >
            <span v-if="page.preview">
                Preview: this page is a
                {{
                    page.preview === 'scheduled'
                        ? 'scheduled page and is not live yet'
                        : 'draft'
                }}. Visitors can’t see it.
            </span>
            <span v-else>You can edit this page.</span>
            <a
                v-if="page.edit_url"
                :href="page.edit_url"
                class="font-semibold underline"
                >Edit page</a
            >
        </div>
    </div>

    <h1
        v-if="
            sections.length && !['hero', 'page_hero'].includes(sections[0].type)
        "
        class="sr-only"
    >
        {{ page.title }}
    </h1>
    <SectionRenderer :sections="sections" />

    <div v-if="!sections.length" class="site-container py-24 text-center">
        <h1 class="text-[40px] font-bold">{{ page.title }}</h1>
        <p class="mt-4 text-site-muted">This page has no content yet.</p>
    </div>
</template>
