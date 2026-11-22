<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SiteImg from '@/components/website/SiteImg.vue';
import type { PostCard } from '@/types/website';
import SiteArrow from '@/components/website/SiteArrow.vue';

/** Figma "Latest insights" article card (node 12:171). */
defineProps<{ post: PostCard }>();
</script>

<template>
    <article
        class="group relative flex h-full flex-col rounded-[20px] bg-site-sand p-3 transition-shadow hover:shadow-[0_12px_30px_rgba(0,0,0,0.1)]"
    >
        <SiteImg
            v-if="post.image"
            :image="post.image"
            :alt="''"
            class="h-[190px] w-full rounded-[12px] max-sm:h-[150px]"
        />
        <div
            v-else
            class="grid h-[190px] w-full place-items-center rounded-[12px] bg-site-blush-panel text-[40px] font-bold text-site-red/40"
            aria-hidden="true"
        >
            RTL
        </div>
        <div
            class="flex flex-1 flex-col px-[10px] pt-5 pb-3 max-sm:pt-4 max-sm:pb-2"
        >
            <p
                class="text-[11px] leading-[13px] font-semibold whitespace-pre text-site-red uppercase"
            >
                <template v-if="post.label"
                    >{{ post.label }}{{ '  ·  ' }}</template
                ><time
                    v-if="post.date"
                    :datetime="post.iso_date ?? undefined"
                    >{{ post.date }}</time
                >
            </p>
            <h3
                class="mt-[15px] text-[22px] leading-[28px] font-semibold text-site-ink max-sm:mt-2 max-sm:line-clamp-2 max-sm:text-[19px] max-sm:leading-[25px]"
            >
                <Link
                    :href="post.url"
                    class="group-focus-within:underline after:absolute after:inset-0 after:rounded-[20px] focus-visible:outline-none"
                    >{{ post.title }}</Link
                >
            </h3>
            <div class="mt-auto flex items-end justify-between gap-4 pt-4">
                <p
                    class="max-w-[262px] text-[13px] leading-5 text-site-muted max-sm:line-clamp-2"
                >
                    {{ post.excerpt }}
                </p>
                <span
                    aria-hidden="true"
                    class="text-[23px] leading-[27.6px] font-medium text-site-red transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                    ><SiteArrow
                /></span>
            </div>
        </div>
    </article>
</template>
