<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SectionHeading from '@/components/website/SectionHeading.vue';
import { flag } from '@/lib/flag';
import SiteArrow from '@/components/website/SiteArrow.vue';

/** Popular destinations (REQ §21.7; not in Figma, drawn in its card style). */
defineProps<{
    data: { eyebrow: string; title: string; description: string };
    records: {
        items: {
            id: number;
            name: string;
            iso2: string;
            universities: number;
        }[];
    } | null;
}>();
</script>

<template>
    <div class="site-container">
        <SectionHeading
            :eyebrow="data.eyebrow"
            :title="data.title"
            :description="data.description"
        />
        <ul
            v-if="records?.items.length"
            class="-mx-4 mt-9 no-scrollbar flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pt-1 pb-4 sm:-mx-6 sm:px-6 md:mx-0 md:grid md:grid-cols-3 md:overflow-visible md:px-0 md:pt-0 md:pb-0 lg:grid-cols-4 lg:gap-6"
        >
            <li
                v-for="c in records.items"
                :key="c.id"
                class="w-[74vw] max-w-[280px] shrink-0 snap-start sm:w-[48vw] md:w-auto md:max-w-none"
            >
                <Link
                    :href="`/universities?country=${c.id}`"
                    class="group flex h-full items-center gap-4 rounded-[20px] bg-white p-5 transition-shadow hover:shadow-[0_12px_30px_rgba(0,0,0,0.1)]"
                >
                    <span class="text-[34px] leading-none" aria-hidden="true">{{
                        flag(c.iso2)
                    }}</span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="block text-[17px] leading-6 font-semibold text-site-ink group-hover:text-site-red"
                            >{{ c.name }}</span
                        >
                        <span class="block text-[13px] text-site-muted"
                            >{{ c.universities }}
                            {{
                                c.universities === 1
                                    ? 'university'
                                    : 'universities'
                            }}</span
                        >
                    </span>
                    <span aria-hidden="true" class="text-site-red"
                        ><SiteArrow
                    /></span>
                </Link>
            </li>
        </ul>
    </div>
</template>
