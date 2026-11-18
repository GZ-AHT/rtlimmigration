<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SiteImg from '@/components/website/SiteImg.vue';
import type { UniversityCard } from '@/types/website';
import SiteArrow from '@/components/website/SiteArrow.vue';

/** University card in the style of the Figma article cards (REQ §15; not in Figma). */
defineProps<{ university: UniversityCard }>();
</script>

<template>
    <article
        class="group relative flex h-full flex-col rounded-[20px] bg-site-sand p-3 transition-shadow hover:shadow-[0_12px_30px_rgba(0,0,0,0.1)]"
    >
        <div
            class="relative h-[170px] overflow-hidden rounded-[12px] bg-site-blush-panel"
        >
            <SiteImg
                v-if="university.banner"
                :image="university.banner"
                :alt="''"
                class="size-full"
            />
            <span
                v-if="university.logo"
                class="absolute bottom-3 left-3 grid size-14 place-items-center overflow-hidden rounded-[12px] bg-white p-1.5 shadow-sm"
            >
                <SiteImg
                    :image="university.logo"
                    :alt="''"
                    class="max-h-full w-auto object-contain"
                />
            </span>
            <span
                v-if="!university.banner && !university.logo"
                class="grid size-full place-items-center text-[44px] font-bold text-site-red/30"
                aria-hidden="true"
                >{{ university.name.charAt(0) }}</span
            >
        </div>
        <div class="flex flex-1 flex-col px-[10px] pt-5 pb-3">
            <p
                class="text-[11px] leading-[13px] font-semibold text-site-red uppercase"
            >
                {{
                    [university.city, university.country]
                        .filter(Boolean)
                        .join(' · ')
                }}
            </p>
            <h3
                class="mt-3 text-[20px] leading-[26px] font-semibold text-site-ink"
            >
                <Link
                    :href="university.url"
                    class="group-focus-within:underline after:absolute after:inset-0 after:rounded-[20px] focus-visible:outline-none"
                    >{{ university.name }}</Link
                >
            </h3>
            <p
                v-if="university.summary"
                class="mt-2 line-clamp-2 text-[13px] leading-5 text-site-muted"
            >
                {{ university.summary }}
            </p>
            <ul
                v-if="university.matching?.length"
                class="mt-3 flex flex-col gap-1 text-[13px] leading-5 text-site-ink"
            >
                <li
                    v-for="c in university.matching"
                    :key="c.name"
                    class="flex gap-2"
                >
                    <span aria-hidden="true" class="text-site-red">•</span>
                    <span
                        >{{ c.name }}
                        <span class="text-site-muted"
                            >· {{ c.level
                            }}<template v-if="c.duration">
                                · {{ c.duration }}</template
                            ></span
                        ></span
                    >
                </li>
            </ul>
            <div class="mt-auto flex items-end justify-between gap-4 pt-4">
                <p class="text-[13px] font-medium text-site-muted">
                    {{ university.courses }}
                    {{ university.courses === 1 ? 'course' : 'courses' }}
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
