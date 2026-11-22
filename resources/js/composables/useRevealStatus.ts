import { nextTick, type Ref } from 'vue';

/**
 * After a form is replaced by a short "thank you" box the page is left
 * scrolled to where the long form used to be, usually past the message.
 * Brings the message into view (clear of the fixed header and phone bar via
 * scroll-padding) and moves focus there for screen readers.
 */
export function useRevealStatus(target: Ref<HTMLElement | null>) {
    return async function reveal(): Promise<void> {
        await nextTick();

        const el = target.value;

        if (!el) {
            return;
        }

        const reduce = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        el.scrollIntoView({
            block: 'center',
            behavior: reduce ? 'auto' : 'smooth',
        });
        el.focus({ preventScroll: true });
    };
}
