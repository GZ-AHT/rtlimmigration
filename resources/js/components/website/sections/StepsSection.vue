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
    <div class="site-container">
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
        <ol class="mt-8 grid gap-8 md:grid-cols-3 md:gap-6 lg:mt-[34px]">
            <li
                v-for="(step, i) in data.items"
                :key="i"
                class="flex gap-4 lg:gap-[30px]"
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
                        :class="dark ? 'text-site-on-dark' : 'text-site-muted'"
                        >{{ step.text }}</span
                    >
                </span>
            </li>
        </ol>
    </div>
</template>
