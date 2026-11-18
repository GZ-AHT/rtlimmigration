<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SiteArrow from '@/components/website/SiteArrow.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import type { PostCard } from '@/types/website';

/** Figma Blog page article card: full-width photo, label, title, excerpt and "Read article". */
defineProps<{ post: PostCard }>();
</script>

<template>
    <article
        class="group relative flex h-full flex-col overflow-hidden rounded-[8px] border border-site-line bg-white transition-shadow hover:shadow-[0_12px_30px_rgba(0,0,0,0.08)]"
    >
        <SiteImg
            v-if="post.image"
            :image="post.image"
            :alt="''"
            class="block h-[192px] w-full"
        />
        <div
            v-else
            class="grid h-[192px] w-full place-items-center bg-site-blush-panel text-[40px] font-bold text-site-red/40"
            aria-hidden="true"
        >
            RTL
        </div>
        <div class="flex flex-1 flex-col px-[21px] pt-[22px] pb-[13px]">
            <p
                class="text-[10px] leading-[13px] font-semibold whitespace-pre text-site-red uppercase"
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
                class="mt-[13px] text-[23px] leading-[28px] font-semibold text-site-ink"
            >
                <Link
                    :href="post.url"
                    class="group-focus-within:underline after:absolute after:inset-0 focus-visible:outline-none"
                    >{{ post.title }}</Link
                >
            </h3>
            <p
                class="mt-[13px] max-w-[270px] text-[14px] leading-[21px] text-site-muted"
            >
                {{ post.excerpt }}
            </p>
            <p
                class="mt-auto inline-flex items-center gap-2.5 pt-[23px] text-[14px] leading-5 font-semibold text-site-red"
                aria-hidden="true"
            >
                Read article
                <SiteArrow
                    class="transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                />
            </p>
        </div>
    </article>
</template>
