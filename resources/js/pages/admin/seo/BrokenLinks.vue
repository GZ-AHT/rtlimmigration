<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { index, scan } from '@/routes/admin/seo/broken-links';

type Row = {
    id: number;
    source: string;
    source_url: string | null;
    target: string;
    internal: boolean;
    status: number | null;
    error: string | null;
};

defineProps<{ links: Row[]; last_checked: string | null }>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Broken links', href: index() }] },
});
</script>

<template>
    <Head title="Broken links" />

    <PageHeader
        eyebrow="SEO management"
        title="Broken links"
        description="Links on pages, blog posts and menus that no longer load. The website is scanned every Monday."
    >
        <template #actions>
            <Button
                variant="outline"
                @click="router.post(scan().url, {}, { preserveScroll: true })"
                ><AppIcon name="refresh" :size="18" /> Scan now</Button
            >
        </template>
    </PageHeader>

    <PanelCard
        :title="`Broken links (${links.length})`"
        :description="
            last_checked ? `Last checked ${last_checked}.` : 'Not scanned yet.'
        "
    >
        <p
            v-if="links.length === 0"
            class="py-6 text-center text-[13px] text-muted-foreground"
        >
            {{
                last_checked
                    ? 'No broken links found.'
                    : 'Run a scan to check your links.'
            }}
        </p>
        <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                <thead class="text-[11px] text-muted-foreground uppercase">
                    <tr>
                        <th class="py-2 pr-3 font-semibold">Found on</th>
                        <th class="py-2 pr-3 font-semibold">Link</th>
                        <th class="py-2 font-semibold">Problem</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="l in links"
                        :key="l.id"
                        class="border-t align-top"
                    >
                        <td class="py-2.5 pr-3">
                            {{ l.source }}
                            <a
                                v-if="l.source_url"
                                :href="l.source_url"
                                target="_blank"
                                rel="noopener"
                                class="block font-mono text-[11px] text-muted-foreground"
                                >{{ l.source_url }}</a
                            >
                        </td>
                        <td class="py-2.5 pr-3 font-mono text-[12px] break-all">
                            {{ l.target }}
                            <StatusBadge
                                class="ml-1"
                                :label="l.internal ? 'Internal' : 'External'"
                                tone="neutral"
                            />
                        </td>
                        <td class="py-2.5">
                            <StatusBadge
                                :label="
                                    l.status
                                        ? `HTTP ${l.status}`
                                        : 'No response'
                                "
                                tone="danger"
                            />
                            <p
                                v-if="l.error"
                                class="mt-1 text-[11px] text-muted-foreground"
                            >
                                {{ l.error }}
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PanelCard>
</template>
