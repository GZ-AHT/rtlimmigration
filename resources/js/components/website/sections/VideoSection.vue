<script setup lang="ts">
import CallWindow from '@/components/website/CallWindow.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import { openConsultationBooking } from '@/composables/useConsultationBooking';
import type { SiteImage } from '@/types/website';

/**
 * Figma "Video counseling" (node 12:128). The video window (CallWindow)
 * is an illustration with a photo or video of the adviser; the button opens the video consultation
 * booking popup (ConsultationModal), not the contact page.
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
    <div class="site-container">
        <div
            class="grid items-start gap-10 lg:grid-cols-[minmax(0,564px)_608px] lg:justify-between"
        >
            <div class="lg:pt-1">
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
                    class="mt-8 max-w-[515px] text-[16px] leading-6 text-site-muted max-sm:mt-4 max-sm:line-clamp-3 max-sm:text-[15px]"
                >
                    {{ data.description }}
                </p>
                <!-- Opens the booking popup (verify email, pick a time) instead of the contact page. -->
                <button
                    v-if="data.button"
                    type="button"
                    class="mt-11 inline-flex h-[54px] min-w-[274px] items-center justify-center gap-2.5 rounded-site-btn bg-site-red px-5 text-[14px] font-semibold whitespace-nowrap text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none max-sm:mt-6 max-sm:w-full"
                    @click="openConsultationBooking"
                >
                    {{ data.button }}
                    <SiteArrow glyph="↗" />
                </button>
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
            class="mt-10 grid gap-4 border-t border-[#eaddd9] pt-3 max-sm:mt-6 max-sm:gap-2 md:grid-cols-3 md:gap-6 lg:mt-[41px]"
        >
            <li
                v-for="(step, i) in data.steps"
                :key="i"
                class="flex min-h-[88px] gap-[22px] rounded-[16px] bg-white px-[18px] py-[17px] max-sm:min-h-0 max-sm:items-center max-sm:gap-4 max-sm:py-3"
            >
                <span
                    class="pt-[6px] text-[14px] leading-5 font-bold text-site-red max-sm:pt-0"
                    >{{ String(i + 1).padStart(2, '0') }}</span
                >
                <span>
                    <span
                        class="block text-[15px] leading-[22px] font-semibold text-site-ink"
                        >{{ step.title }}</span
                    >
                    <span
                        class="mt-2 block text-[13px] leading-[19px] text-site-muted max-sm:hidden"
                        >{{ step.text }}</span
                    >
                </span>
            </li>
        </ol>
    </div>
</template>
