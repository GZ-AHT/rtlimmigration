import { onBeforeUnmount, ref } from 'vue';

/** Counts whole seconds down to zero from a deadline (so a sleeping tab catches up). */
export function useCountdown() {
    const remaining = ref(0);
    let timer: ReturnType<typeof setInterval> | null = null;
    let deadline = 0;

    function stop(): void {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function tick(): void {
        remaining.value = Math.max(
            0,
            Math.ceil((deadline - Date.now()) / 1000),
        );

        if (remaining.value === 0) {
            stop();
        }
    }

    function start(seconds: number): void {
        stop();
        deadline = Date.now() + Math.max(0, seconds) * 1000;
        tick();

        if (remaining.value > 0) {
            timer = setInterval(tick, 250);
        }
    }

    onBeforeUnmount(stop);

    return { remaining, start };
}
