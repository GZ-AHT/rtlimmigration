<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import StudentProfileForm from '@/components/students/StudentProfileForm.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import ProgressBar from '@/components/panel/ProgressBar.vue';
import { edit, update } from '@/routes/portal/profile';
import type { Completeness, Option, ProfileForm } from '@/types';

defineProps<{
    profile: ProfileForm;
    completeness: Completeness;
    options: {
        genders: Option[];
        maritalStatuses: Option[];
        educationLevels: Option[];
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'My profile', href: edit() }] },
});
</script>

<template>
    <Head title="My profile" />

    <PageHeader
        eyebrow="Student portal"
        title="My profile"
        description="Your counselor uses these details for your applications. Please keep them accurate and up to date."
    />

    <PanelCard class="mb-4">
        <ProgressBar :value="completeness.percent" label="Profile complete" />
        <p
            v-if="completeness.missing.length"
            class="mt-2 text-xs text-muted-foreground"
        >
            Still needed: {{ completeness.missing.join(', ') }}
        </p>
    </PanelCard>

    <StudentProfileForm
        :profile="profile"
        :options="options"
        :submit-url="update().url"
        submit-label="Save my profile"
    />
</template>
