import { router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

/**
 * Keeps a list screen's filters in the URL query string, so filtered views
 * can be bookmarked, shared and survive a refresh. Every change reloads the
 * page data from the server (filtering and paging are server-side).
 */
export function useFilters<T extends Record<string, string>>(
    initial: T,
    options: { only?: string[] } = {},
) {
    const filters = reactive({ ...initial }) as T;

    function apply(): void {
        const query = Object.fromEntries(
            Object.entries(filters).filter(([, v]) => v !== '' && v !== null),
        );

        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: options.only,
        });
    }

    watch(
        () => ({ ...filters }),
        () => apply(),
    );

    function reset(): void {
        Object.keys(filters).forEach((key) => {
            (filters as Record<string, string>)[key] = '';
        });
    }

    return { filters, apply, reset };
}
