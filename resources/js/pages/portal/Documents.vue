<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocumentChecklist from '@/components/documents/DocumentChecklist.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import { index, store } from '@/routes/portal/documents';
import type { Checklist, Option, UploadLimits } from '@/types';

defineProps<{
    checklist: Checklist;
    files: Option[];
    categories: Option[];
    limits: UploadLimits;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Documents', href: index() }] },
});
</script>

<template>
    <Head title="My documents" />

    <PageHeader
        eyebrow="Student portal"
        title="My documents"
        :description="`Upload clear scans or photos (${limits.extensions.join(', ').toUpperCase()}, up to ${limits.max_mb} MB). Your counselor reviews each one, and you'll be notified of the result.`"
    />

    <DocumentChecklist
        :checklist="checklist"
        :can-upload="true"
        :upload-url="store().url"
        :categories="categories"
        :files="files"
        :limits="limits"
    />
</template>
