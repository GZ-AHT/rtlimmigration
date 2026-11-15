<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import { getInitials } from '@/composables/useInitials';
import { counselor as counselorRoute } from '@/routes/portal';

defineProps<{
    counselor: {
        name: string;
        email: string;
        phone: string | null;
        designation: string | null;
        bio: string | null;
        availability: string | null;
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My counselor', href: counselorRoute() }],
    },
});
</script>

<template>
    <Head title="My counselor" />

    <PageHeader
        eyebrow="Student portal"
        title="My counselor"
        description="Your main point of contact for your application."
    />

    <PanelCard class="max-w-2xl">
        <EmptyState
            v-if="!counselor"
            icon="support_agent"
            title="No counselor assigned yet"
            description="A counselor will be assigned to you soon."
        />
        <template v-else>
            <div class="flex items-center gap-3.5">
                <span
                    class="grid size-14 shrink-0 place-items-center rounded-full bg-accent font-display text-lg font-extrabold text-accent-foreground"
                    >{{ getInitials(counselor.name) }}</span
                >
                <div class="min-w-0">
                    <p class="truncate font-display text-lg font-bold">
                        {{ counselor.name }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ counselor.designation ?? 'Counselor' }}
                        <template v-if="counselor.availability">
                            · {{ counselor.availability }}</template
                        >
                    </p>
                </div>
            </div>
            <p
                v-if="counselor.bio"
                class="mt-4 text-[13px] whitespace-pre-line text-secondary-foreground"
            >
                {{ counselor.bio }}
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a :href="`mailto:${counselor.email}`" class="tiny-action">
                    <AppIcon name="mail" :size="14" /> {{ counselor.email }}
                </a>
                <a
                    v-if="counselor.phone"
                    :href="`tel:${counselor.phone}`"
                    class="tiny-action"
                >
                    <AppIcon name="call" :size="14" /> {{ counselor.phone }}
                </a>
            </div>
        </template>
    </PanelCard>
</template>
