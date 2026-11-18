<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import SiteImg from '@/components/website/SiteImg.vue';
import type { SiteImage } from '@/types/website';

/**
 * A banner's photo or background video. The image is always rendered: it
 * is the video's poster and the fallback when a video can't play. The
 * video is added after the page loads (autoplay, muted, loop, inline),
 * pauses while off-screen, and is skipped for visitors who prefer reduced
 * motion and, unless allowed, on phones (to save their data).
 */
const props = withDefaults(
    defineProps<{
        image: SiteImage | null;
        video: { url: string; mime: string } | null;
        mediaType: string;
        mobileVideo?: boolean;
        eager?: boolean;
    }>(),
    { mobileVideo: false, eager: false },
);

const el = ref<HTMLVideoElement | null>(null);
const play = ref(false);
const failed = ref(false);
const wantsVideo = computed(
    () => props.mediaType === 'video' && props.video !== null,
);
let observer: IntersectionObserver | null = null;

onMounted(() => {
    if (!wantsVideo.value) {
        return;
    }

    const reduced = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    const phone = window.matchMedia('(max-width: 767px)').matches;
    play.value = !reduced && (props.mobileVideo || !phone);
});

function observe(video: HTMLVideoElement | null): void {
    el.value = video;

    if (!video || observer || typeof IntersectionObserver === 'undefined') {
        return;
    }

    observer = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting) {
            void video.play().catch(() => undefined);
        } else {
            video.pause();
        }
    });
    observer.observe(video);
}

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div class="relative overflow-hidden">
        <SiteImg
            v-if="image"
            :image="image"
            :eager="eager"
            :alt="mediaType === 'video' ? '' : undefined"
            class="absolute inset-0 block size-full"
        />
        <div v-else class="absolute inset-0 bg-site-navy" aria-hidden="true" />
        <video
            v-if="play && video && !failed"
            :ref="(v) => observe(v as HTMLVideoElement | null)"
            class="absolute inset-0 size-full object-cover"
            :poster="image?.url"
            autoplay
            muted
            loop
            playsinline
            disablepictureinpicture
            preload="auto"
            aria-hidden="true"
            @error="failed = true"
        >
            <source
                :src="video.url"
                :type="video.mime"
                @error="failed = true"
            />
        </video>
        <slot />
    </div>
</template>
