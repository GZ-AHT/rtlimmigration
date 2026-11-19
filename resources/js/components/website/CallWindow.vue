<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import BannerMedia from '@/components/website/BannerMedia.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import type { SiteImage } from '@/types/website';

/**
 * The Video counseling "call window" (an illustration, hidden from screen
 * readers). meeting: styled like a Google Meet / Zoom call, with a live
 * timer, speaking indicator, self-view tile and call controls.
 * simple: the Figma design (node 12:139). Either style shows a photo or a
 * muted, looping video of the adviser.
 */
const props = defineProps<{
    data: {
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
    };
}>();

// The call timer starts at 00:00 on both server and browser, then ticks.
const seconds = ref(0);
let timer: number | undefined;
onMounted(() => {
    if (props.data.call_style === 'meeting' && props.data.show_timer) {
        timer = window.setInterval(() => seconds.value++, 1000);
    }
});
onBeforeUnmount(() => window.clearInterval(timer));
const clock = () =>
    `${String(Math.floor(seconds.value / 60)).padStart(2, '0')}:${String(seconds.value % 60).padStart(2, '0')}`;

const controls = [
    { icon: 'mic', label: 'Microphone' },
    { icon: 'videocam', label: 'Camera' },
    { icon: 'closed_caption', label: 'Captions', hideSm: true },
    { icon: 'back_hand', label: 'Raise hand', hideSm: true },
    { icon: 'present_to_all', label: 'Share screen' },
    { icon: 'more_vert', label: 'More' },
];
const name = () => props.data.adviser_name || 'Your RTL adviser';
</script>

<template>
    <div
        class="w-full min-w-0 rounded-[26px] border border-site-warm-line bg-white p-3 sm:p-[23px]"
        aria-hidden="true"
    >
        <!-- Google Meet / Zoom style -->
        <div
            v-if="data.call_style !== 'simple'"
            class="rounded-[18px] bg-[#202124] px-3 pt-[16px] pb-[18px]"
        >
            <div class="flex items-center justify-between gap-3 px-2">
                <p
                    class="flex min-w-0 items-center gap-1.5 text-[11px] leading-4 font-semibold whitespace-pre text-white uppercase"
                >
                    <AppIcon name="lock" :size="13" class="text-[#9aa0a6]" />
                    <span class="truncate">{{ data.room_label }}</span>
                </p>
                <p
                    class="flex shrink-0 items-center gap-2 text-[11px] leading-4 font-semibold text-white tabular-nums"
                >
                    <span class="relative flex size-2">
                        <span
                            class="absolute inline-flex size-full animate-ping rounded-full bg-site-red opacity-70 motion-reduce:animate-none"
                        />
                        <span
                            class="relative inline-flex size-2 rounded-full bg-site-red"
                        />
                    </span>
                    <template v-if="data.show_timer"
                        >LIVE · {{ clock() }}</template
                    >
                    <template v-else>LIVE</template>
                </p>
            </div>

            <div
                class="relative mt-[14px] overflow-hidden rounded-[12px] ring-2 ring-[#8ab4f8]/90 ring-offset-0"
            >
                <BannerMedia
                    :image="data.image"
                    :video="data.video"
                    :media-type="data.media_type"
                    :mobile-video="data.mobile_video"
                    class="aspect-[536/284] w-full"
                />
                <span
                    class="absolute bottom-3 left-3 flex items-center gap-2 rounded-[6px] bg-black/60 px-2.5 py-[6px] text-[11px] leading-4 font-medium text-white backdrop-blur-sm"
                >
                    <span
                        class="flex h-3 items-end gap-[2px]"
                        aria-hidden="true"
                    >
                        <span
                            class="sound-bar w-[3px] rounded-full bg-[#8ab4f8]"
                        />
                        <span
                            class="sound-bar w-[3px] rounded-full bg-[#8ab4f8] [animation-delay:150ms]"
                        />
                        <span
                            class="sound-bar w-[3px] rounded-full bg-[#8ab4f8] [animation-delay:300ms]"
                        />
                    </span>
                    {{ name() }}
                </span>
                <span
                    class="absolute top-3 right-3 rounded-[4px] bg-black/50 px-1.5 py-0.5 text-[9px] font-semibold tracking-wide text-white"
                    >HD</span
                >
                <span
                    class="absolute right-3 bottom-3 hidden size-[98px] overflow-hidden rounded-[8px] bg-[#3c4043] shadow-lg ring-1 ring-white/10 sm:block"
                >
                    <SiteImg
                        v-if="data.self_image"
                        :image="data.self_image"
                        :alt="''"
                        class="block size-full"
                    />
                    <span v-else class="grid size-full place-items-center">
                        <span
                            class="grid size-11 place-items-center rounded-full bg-site-red text-[18px] font-semibold text-white"
                            >Y</span
                        >
                    </span>
                    <span
                        class="absolute bottom-1.5 left-1.5 flex items-center gap-1 rounded-[4px] bg-black/55 px-1.5 py-0.5 text-[10px] font-medium text-white"
                    >
                        <AppIcon name="mic_off" :size="11" /> You
                    </span>
                </span>
            </div>

            <div
                class="mt-4 flex items-center justify-between gap-2 px-1 sm:px-2"
            >
                <span
                    class="hidden w-[60px] text-[11px] font-medium text-[#9aa0a6] tabular-nums sm:block"
                    >1 : 1</span
                >
                <div class="flex items-center gap-1.5 sm:gap-2">
                    <span
                        v-for="c in controls"
                        :key="c.icon"
                        class="grid size-9 place-items-center rounded-full bg-[#3c4043] text-white sm:size-10"
                        :class="c.hideSm && 'hidden sm:grid'"
                        :title="c.label"
                    >
                        <AppIcon :name="c.icon" :size="19" />
                    </span>
                </div>
                <span
                    class="grid h-9 w-14 place-items-center rounded-full bg-[#ea4335] text-white sm:h-10 sm:w-[60px]"
                    title="Leave call"
                >
                    <AppIcon name="call_end" :size="21" />
                </span>
            </div>
        </div>

        <!-- Simple: Figma "Video consultation preview" -->
        <div v-else class="rounded-[18px] bg-site-ink px-3 pt-[18px] pb-[21px]">
            <div class="flex items-center justify-between px-2">
                <p
                    class="text-[11px] leading-4 font-semibold whitespace-pre text-white uppercase"
                >
                    {{ data.room_label }}
                </p>
                <p
                    class="text-[9px] leading-[13px] font-semibold text-[#d3d4d7]"
                >
                    SESSION PREVIEW
                </p>
            </div>
            <div class="relative mt-[17px] overflow-hidden rounded-[12px]">
                <BannerMedia
                    :image="data.image"
                    :video="data.video"
                    :media-type="data.media_type"
                    :mobile-video="data.mobile_video"
                    class="aspect-[536/284] w-full"
                />
                <span
                    class="absolute bottom-[14px] left-[14px] rounded-[4px] bg-site-ink px-3 py-[7px] text-[11px] leading-4 font-medium text-white"
                    >{{ name() }}</span
                >
                <span
                    class="absolute right-[14px] bottom-4 hidden size-[98px] flex-col items-center justify-center gap-[13px] overflow-hidden rounded-[7px] bg-[#f8eeeb] sm:flex"
                >
                    <SiteImg
                        v-if="data.self_image"
                        :image="data.self_image"
                        :alt="''"
                        class="absolute inset-0 block size-full"
                    />
                    <template v-else>
                        <span
                            class="grid size-9 place-items-center rounded-full bg-[#f1dcd7] text-[16px] font-semibold text-site-red"
                            >Y</span
                        >
                        <span
                            class="text-[11px] leading-4 font-medium text-site-ink"
                            >You</span
                        >
                    </template>
                </span>
            </div>
            <div class="mt-4 flex items-center gap-[10px] px-2">
                <span
                    class="rounded-full bg-[#303238] px-[27px] py-[9px] text-[11px] leading-4 font-medium text-white"
                    >Mic on</span
                >
                <span
                    class="rounded-full bg-[#303238] px-[23px] py-[9px] text-[11px] leading-4 font-medium text-white"
                    >Camera on</span
                >
                <span
                    class="ml-auto rounded-full bg-site-red px-[30px] py-[9px] text-[11px] leading-4 font-semibold text-white"
                    >Leave</span
                >
            </div>
        </div>

        <p
            v-if="data.reassurance"
            class="mt-[18px] text-[12px] leading-[17px] font-medium text-site-muted"
        >
            {{ data.reassurance }}
        </p>
    </div>
</template>

<style scoped>
/* Speaking indicator, like the sound bars in Meet and Zoom. */
.sound-bar {
    height: 40%;
    animation: sound 0.9s ease-in-out infinite;
}

@keyframes sound {
    0%,
    100% {
        height: 30%;
    }
    50% {
        height: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .sound-bar {
        animation: none;
        height: 60%;
    }
}
</style>
