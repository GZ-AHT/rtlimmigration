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
            v-if="seo.robots || seo.noindex"
            head-key="robots"
            name="robots"
            :content="seo.robots ?? 'noindex, nofollow'"
        />
        <meta
            v-if="seo.keywords"
            head-key="keywords"
            name="keywords"
            :content="seo.keywords"
        />
        <link
            v-if="seo.hreflang"
            head-key="hreflang"
            rel="alternate"
            :hreflang="seo.hreflang"
            :href="seo.canonical"
        />
        <link
            v-if="seo.hreflang"
            head-key="hreflang-default"
            rel="alternate"
            hreflang="x-default"
            :href="seo.canonical"
        />
        <meta
            v-if="seo.twitter_site"
            head-key="twitter:site"
            name="twitter:site"
            :content="seo.twitter_site"
        />
        <meta
            head-key="twitter:title"
            name="twitter:title"
            :content="seo.og_title"
        />
        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="seo.og_description"
        />
        <meta
            v-if="seo.image"
            head-key="twitter:image"
            name="twitter:image"
            :content="seo.image"
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
            :content="
                seo.twitter_card ??
                (seo.image ? 'summary_large_image' : 'summary')
            "
        />
    </Head>
</template>
