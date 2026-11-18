import { isProxy, toRaw } from 'vue';

/**
 * Deep copy of plain JSON-like data (page props, form values). Use this
 * instead of structuredClone(): Inertia page props are Vue reactive
 * proxies, and structuredClone() throws "Proxy object could not be
 * cloned" on them, which leaves the screen blank.
 */
export function clone<T>(value: T): T {
    const raw = isProxy(value) ? toRaw(value) : value;

    return JSON.parse(JSON.stringify(raw ?? null)) as T;
}
