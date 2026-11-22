<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    reports: {
        name: string;
        items: {
            key: string;
            title: string;
            description: string;
            url: string;
        }[];
    }[];
    exportsUrl: string | null;
}>();

const page = usePage();
setLayoutProps({ breadcrumbs: [{ title: 'Reports', href: page.url }] });
</script>

<template>
    <Head title="Reports" />

    <PageHeader
        eyebrow="Reports"
        title="Reports & analytics"
        description="Every report you can open. Reports over students and applications cover only the records you are allowed to see."
    >
        <template v-if="exportsUrl" #actions>
            <Button variant="outline" as-child>
                <Link :href="exportsUrl"
                    ><AppIcon name="download" :size="18" /> Finance
                    exports</Link
                >
            </Button>
        </template>
    </PageHeader>

    <p
        v-if="!reports.length"
        class="rounded-xl border bg-card p-6 text-sm text-muted-foreground"
    >
        No reports are available for your role.
    </p>

    <PanelCard
        v-for="group in reports"
        :key="group.name"
        class="mb-4"
        :title="group.name"
        flush
    >
        <ul class="divide-y">
            <li v-for="r in group.items" :key="r.key">
                <Link
                    :href="r.url"
                    class="flex items-center justify-between gap-4 px-5 py-4 transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                >
                    <span class="min-w-0">
                        <span class="block text-[13px] font-semibold">{{
                            r.title
                        }}</span>
                        <span class="block text-xs text-muted-foreground">{{
                            r.description
                        }}</span>
                    </span>
                    <AppIcon
                        name="chevron_right"
                        :size="20"
                        class="shrink-0 text-muted-foreground"
                    />
                </Link>
            </li>
        </ul>
    </PanelCard>
</template>
