<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { flag } from '@/lib/flag';

/**
 * Skill migration steps (Destination → Introduction → Assessment → Result)
 * with the chosen country, shown on every page of the journey.
 */
defineProps<{
    step: 1 | 2 | 3 | 4;
    country?: string | null;
    iso2?: string | null;
    slug?: string | null;
}>();

const steps = ['Destination', 'Introduction', 'Assessment', 'Result'];
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <nav aria-label="Breadcrumb">
            <ol
                class="flex flex-wrap gap-2 text-[12px] leading-[18px] text-site-muted"
            >
                <li><Link href="/" class="hover:text-site-red">Home</Link></li>
                <li aria-hidden="true">/</li>
                <li>
                    <Link href="/skill-migration" class="hover:text-site-red"
                        >Skill migration</Link
                    >
                </li>
                <template v-if="country">
                    <li aria-hidden="true">/</li>
                    <li>
                        <Link
                            v-if="slug && step > 2"
                            :href="`/skill-migration/${slug}`"
                            class="hover:text-site-red"
                            >{{ country }}</Link
                        >
                        <span v-else aria-current="page">{{ country }}</span>
                    </li>
                </template>
            </ol>
        </nav>
        <ol
            class="flex items-center gap-1.5 text-[11px] font-semibold uppercase"
            aria-label="Progress"
        >
            <li
                v-for="(label, i) in steps"
                :key="label"
                class="flex items-center gap-1.5"
            >
                <span
                    class="grid size-5 place-items-center rounded-full text-[10px]"
                    :class="
                        i + 1 < step
                            ? 'bg-site-ink text-white'
                            : i + 1 === step
                              ? 'bg-site-red text-white'
                              : 'bg-site-line text-site-muted'
                    "
                    :aria-current="i + 1 === step ? 'step' : undefined"
                    >{{ i + 1 }}</span
                >
                <span
                    class="hidden md:inline"
                    :class="
                        i + 1 === step ? 'text-site-ink' : 'text-site-muted'
                    "
                    >{{ label }}</span
                >
                <span
                    v-if="i < steps.length - 1"
                    class="mx-1 h-px w-4 bg-site-line"
                    aria-hidden="true"
                />
            </li>
        </ol>
    </div>
    <p
        v-if="country"
        class="mt-5 inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-[13px] font-semibold text-site-ink shadow-sm"
    >
        <span class="text-[18px] leading-none" aria-hidden="true">{{
            flag(iso2)
        }}</span>
        Destination: {{ country }}
    </p>
</template>
