import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * The sidebar link that best matches the current URL: the longest href that
 * is the current path or a parent of it. Stops "/students" and
 * "/students/create" from both lighting up.
 */
export function useActiveNav() {
    const page = usePage();

    const activeHref = computed(() => {
        const path = new URL(page.url, 'http://x').pathname;
        const hrefs = page.props.navigation
            .flatMap((g) => g.items)
            .flatMap((i) => [i.href, ...i.children.map((c) => c.href)])
            .filter((h): h is string => !!h);

        return (
            hrefs
                .filter((h) => path === h || path.startsWith(`${h}/`))
                .sort((a, b) => b.length - a.length)[0] ?? null
        );
    });

    return { activeHref };
}
