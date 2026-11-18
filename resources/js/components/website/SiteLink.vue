<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { isInternal } from '@/composables/useSite';

/**
 * A website link from CMS data: Inertia navigation for site paths, a plain
 * anchor for #anchors, mailto:, tel: and other sites (new tab for those).
 */
const props = defineProps<{ href: string; newTab?: boolean }>();

const internal = computed(() => isInternal(props.href) && !props.newTab);
const external = computed(() => /^https?:\/\//i.test(props.href));
</script>

<template>
    <Link v-if="internal" :href="href"><slot /></Link>
    <a
        v-else
        :href="href"
        :target="newTab || external ? '_blank' : undefined"
        :rel="newTab || external ? 'noopener noreferrer' : undefined"
        ><slot
    /></a>
</template>
