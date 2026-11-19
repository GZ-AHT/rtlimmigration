<script setup lang="ts">
import CallWindow from '@/components/website/CallWindow.vue';
import SiteButton from '@/components/website/SiteButton.vue';
import type { SiteImage } from '@/types/website';

/**
 * Figma "Video counseling" (node 12:128). The video window (CallWindow)
 * is an illustration with a photo or video of the adviser; the button opens the consultation form with "video" chosen
 * (roadmap Q6: a marketing section that leads to the enquiry form).
 */
defineProps<{
    data: {
        eyebrow: string;
        title: string;
        description: string;
        button: string;
        detail: string;
        call_style: 'meeting' | 'simple';
        media_type: 'image' | 'video';
        image: SiteImage | null;
        video: { url: string; mime: string } | null;
        mobile_video: boolean;
        adviser_name: string;
        self_image: SiteImage | null;
        show_timer: boolean;
        room_label: string;
        reassurance: string;
        steps: { title: string; text: string }[];
    };
}>();
</script>

<template>
    <div class="site-container w-full min-w-0">
        <div
            class="grid w-full min-w-0 grid-cols-1 items-start gap-10 lg:grid-cols-[minmax(0,564px)_608px] lg:justify-between"
        >
            <div class="w-full min-w-0 lg:pt-1">
                <p
                    v-if="data.eyebrow"
                    class="text-[11px] leading-4 font-semibold tracking-[1.2px] text-site-red uppercase"
                >
                    {{ data.eyebrow }}
                </p>
                <h2
                    class="mt-4 text-[40px] leading-[1.05] font-bold tracking-[-0.04em] whitespace-pre-line text-site-ink sm:text-[62px] sm:leading-[63px] sm:tracking-[-2.4px]"
                >
                    {{ data.title }}
                </h2>
                <p
                    v-if="data.description"
                    class="mt-8 max-w-[515px] text-[16px] leading-6 text-site-muted"
                >
                    {{ data.description }}
                </p>
                <SiteButton
                    v-if="data.button"
                    href="/contact?preference=video"
                    size="tall"
                    class="mt-11 min-w-[274px]"
                    >{{ data.button }}</SiteButton
                >
                <p
                    v-if="data.detail"
                    class="mt-[19px] text-[12px] leading-[17px] font-medium text-site-muted"
                >
                    {{ data.detail }}
                </p>
            </div>

            <!-- Video call window: photo or video, Meet / Zoom or Figma style -->
            <CallWindow :data="data" />
        </div>

        <ol
            v-if="data.steps.length"
            class="mt-10 grid gap-4 border-t border-[#eaddd9] pt-3 md:grid-cols-3 md:gap-6 lg:mt-[41px]"
        >
            <li
                v-for="(step, i) in data.steps"
                :key="i"
                class="flex min-h-[88px] gap-[22px] rounded-[16px] bg-white px-[18px] py-[17px]"
            >
                <span
                    class="pt-[6px] text-[14px] leading-5 font-bold text-site-red"
                    >{{ String(i + 1).padStart(2, '0') }}</span
                >
                <span>
                    <span
                        class="block text-[15px] leading-[22px] font-semibold text-site-ink"
                        >{{ step.title }}</span
                    >
                    <span
                        class="mt-2 block text-[13px] leading-[19px] text-site-muted"
                        >{{ step.text }}</span
                    >
                </span>
            </li>
        </ol>
    </div>
</template>
