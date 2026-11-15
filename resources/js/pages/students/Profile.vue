<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import StudentProfileForm from '@/components/students/StudentProfileForm.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import { Button } from '@/components/ui/button';
import type { Option, ProfileForm } from '@/types';

const props = defineProps<{
    student: { id: number; name: string; student_number: string };
    profile: ProfileForm;
    options: {
        genders: Option[];
        maritalStatuses: Option[];
        educationLevels: Option[];
    };
    urls: { submit: string; cancel: string; index: string };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Students', href: props.urls.index }],
});
</script>

<template>
    <Head :title="`Edit profile · ${student.name}`" />

    <PageHeader
        :eyebrow="student.student_number"
        :title="`${student.name}: profile`"
        description="Personal, contact, passport and education details. Every change is recorded."
    />

    <StudentProfileForm
        :profile="profile"
        :options="options"
        :submit-url="urls.submit"
    >
        <template #actions>
            <Button variant="outline" as-child
                ><Link :href="urls.cancel">Cancel</Link></Button
            >
        </template>
    </StudentProfileForm>
</template>
