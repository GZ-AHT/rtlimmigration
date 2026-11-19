<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ArrowLink from '@/components/website/ArrowLink.vue';
import SectionHeading from '@/components/website/SectionHeading.vue';
import type { CourseCard, LinkValue } from '@/types/website';

/** Featured courses (REQ §21.7; not in Figma, drawn in its card style). */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        link: LinkValue;
    };
    records: { items: CourseCard[] } | null;
}>();
</script>

<template>
    <div class="site-container w-full min-w-0">
        <div
            class="flex w-full min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <SectionHeading
                :eyebrow="data.eyebrow"
                :title="data.title"
                :description="data.description"
            />
            <ArrowLink
                v-if="data.link.url"
                :href="data.link.url"
                class="shrink-0 text-[13px] lg:mb-[5px]"
                >{{ data.link.label }}</ArrowLink
            >
        </div>
        <p v-if="!records?.items.length" class="mt-8 text-site-muted">
            Featured courses will appear here soon.
        </p>
        <div v-else class="w-full min-w-0 overflow-hidden">
            <ul
                class="-mx-4 mt-9 no-scrollbar flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pt-1 pb-4 sm:-mx-6 sm:px-6 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 md:pt-0 md:pb-0 lg:grid-cols-3 lg:gap-6"
            >
                <li
                    v-for="c in records.items"
                    :key="c.id"
                    class="flex w-[82vw] max-w-[340px] shrink-0 snap-start flex-col rounded-[20px] bg-site-sand p-6 sm:w-[60vw] md:w-auto md:max-w-none"
                >
                    <p
                        class="text-[11px] font-semibold text-site-red uppercase"
                    >
                        {{ c.level
                        }}<template v-if="c.duration">
                            · {{ c.duration }}</template
                        >
                    </p>
                    <h3
                        class="mt-3 text-[19px] leading-[25px] font-semibold text-site-ink"
                    >
                        {{ c.name }}
                    </h3>
                    <p
                        v-if="c.tuition"
                        class="mt-2 text-[13px] text-site-muted"
                    >
                        Tuition: {{ c.tuition }}
                    </p>
                    <Link
                        v-if="c.university"
                        :href="c.university.url"
                        class="mt-auto pt-4 text-[14px] font-semibold text-site-ink hover:text-site-red"
                    >
                        {{ c.university.name
                        }}<span
                            v-if="c.university.country"
                            class="font-normal text-site-muted"
                        >
                            · {{ c.university.country }}</span
                        >
                    </Link>
                </li>
            </ul>
        </div>
    </div>
</template>
