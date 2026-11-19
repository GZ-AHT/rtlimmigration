<script setup lang="ts">
/** Numbered steps, e.g. "What happens next" (owner's reference "Partner application.png"). */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        items: { title: string; text: string }[];
    };
    dark?: boolean;
}>();
</script>

<template>
    <div class="site-container w-full min-w-0">
        <p
            v-if="data.eyebrow"
            class="text-[11px] leading-[14px] font-semibold text-site-red uppercase"
        >
            {{ data.eyebrow }}
        </p>
        <h2
            v-if="data.title"
            class="mt-3 text-[28px] leading-[1.2] font-bold sm:text-[32px]"
            :class="dark ? 'text-white' : 'text-site-ink'"
        >
            {{ data.title }}
        </h2>
        <p
            v-if="data.description"
            class="mt-3 max-w-[760px] text-[16px] leading-6"
            :class="dark ? 'text-site-on-dark' : 'text-site-muted'"
        >
            {{ data.description }}
        </p>
        <div class="w-full min-w-0 overflow-hidden">
            <ol
                class="-mx-4 mt-8 no-scrollbar flex snap-x snap-mandatory gap-6 overflow-x-auto px-4 pt-1 pb-4 sm:-mx-6 sm:px-6 md:mx-0 md:grid md:grid-cols-3 md:overflow-visible md:px-0 md:pt-0 md:pb-0 lg:mt-8.5 lg:gap-6"
            >
                <li
                    v-for="(step, i) in data.items"
                    :key="i"
                    class="flex w-[82vw] max-w-[320px] shrink-0 snap-start gap-4 md:w-auto md:max-w-none lg:gap-7.5"
                >
                    <span
                        class="text-[24px] leading-[30px] font-bold text-site-red"
                        >{{ String(i + 1).padStart(2, '0') }}</span
                    >
                    <span>
                        <span
                            class="block text-[17px] leading-6 font-semibold"
                            :class="dark ? 'text-white' : 'text-site-ink'"
                            >{{ step.title }}</span
                        >
                        <span
                            class="mt-[10px] block text-[15px] leading-[21px] lg:whitespace-pre-line"
                            :class="
                                dark ? 'text-site-on-dark' : 'text-site-muted'
                            "
                            >{{ step.text }}</span
                        >
                    </span>
                </li>
            </ol>
        </div>
    </div>
</template>
