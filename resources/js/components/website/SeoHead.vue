<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { Seo } from '@/types/website';

/** Title, description, canonical and Open Graph tags for public pages (REQ §22). */
defineProps<{ seo: Seo }>();
</script>

<template>
    <Head :title="seo.title">
        <meta
            head-key="description"
            name="description"
            :content="seo.description"
        />
        <link head-key="canonical" rel="canonical" :href="seo.canonical" />
        <meta
            v-if="seo.noindex"
            head-key="robots"
            name="robots"
            content="noindex, nofollow"
        />
        <meta head-key="og:type" property="og:type" :content="seo.type" />
        <meta head-key="og:title" property="og:title" :content="seo.og_title" />
        <meta
            head-key="og:description"
            property="og:description"
            :content="seo.og_description"
        />
        <meta head-key="og:url" property="og:url" :content="seo.canonical" />
        <meta
            v-if="seo.image"
            head-key="og:image"
            property="og:image"
            :content="seo.image"
        />
        <meta
            head-key="twitter:card"
            name="twitter:card"
            :content="seo.image ? 'summary_large_image' : 'summary'"
        />
    </Head>
</template>
