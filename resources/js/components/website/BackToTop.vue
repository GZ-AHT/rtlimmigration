<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';

/**
 * "Back to top" button: appears after scrolling down a screen or so. Bottom
 * left on larger screens (WhatsApp and chat own the right); an airplane that lifts off on hover; on phones it sits
 * above the quick-contact bar.
 */
const visible = ref(false);
let frame = 0;

function update(): void {
    frame = 0;
    visible.value = window.scrollY > 600;
}

function onScroll(): void {
    if (!frame) {
        frame = requestAnimationFrame(update);
    }
}

function toTop(): void {
    const reduce = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    // Move focus to the page start so keyboard users do not lose their place.
    document.getElementById('main')?.focus({ preventScroll: true });
}

onMounted(() => {
    update();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);

    if (frame) {
        cancelAnimationFrame(frame);
    }
});
</script>

<template>
    <button
        type="button"
        aria-label="Back to top"
        :tabindex="visible ? 0 : -1"
        :aria-hidden="!visible"
        class="group fixed bottom-[calc(5.25rem+env(safe-area-inset-bottom))] left-4 z-30 grid size-11 place-items-center rounded-full border border-site-line bg-white/90 text-site-ink shadow-[0_8px_24px_rgba(0,0,0,0.14)] backdrop-blur-[12px] transition-[opacity,transform,background-color] duration-200 hover:bg-site-sand focus-visible:ring-2 focus-visible:ring-site-red/40 focus-visible:outline-none active:scale-95 motion-reduce:transition-none sm:bottom-6 sm:left-6 sm:size-12"
        :class="
            visible
                ? 'translate-y-0 opacity-100'
                : 'pointer-events-none translate-y-3 opacity-0'
        "
        @click="toTop"
    >
        <AppIcon
            name="flight"
            :size="24"
            filled
            class="text-site-red transition-transform duration-300 group-hover:-translate-y-0.5 group-hover:scale-110 motion-reduce:transition-none"
        />
    </button>
</template>
