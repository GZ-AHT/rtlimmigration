<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onClickOutside, useEventListener } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import { Spinner } from '@/components/ui/spinner';
import { search as searchRoute } from '@/routes';
import type { SearchGroup, SearchResult } from '@/types';

/**
 * Top-bar global search (REQ §1). The server decides what each user may
 * find; this component only displays the grouped results.
 */
const listId = `search-${useId()}`;
const root = ref<HTMLElement | null>(null);
const input = ref<HTMLInputElement | null>(null);
const term = ref('');
const groups = ref<SearchGroup[]>([]);
const open = ref(false);
const loading = ref(false);
const failed = ref(false);
const active = ref(-1);

let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | null = null;

const flat = computed<SearchResult[]>(() =>
    groups.value.flatMap((g) => g.results),
);
const showPanel = computed(() => open.value && term.value.trim().length >= 2);

watch(term, (value) => {
    clearTimeout(timer);
    active.value = -1;

    if (value.trim().length < 2) {
        groups.value = [];
        loading.value = false;

        return;
    }

    loading.value = true;
    timer = setTimeout(() => void run(value.trim()), 250);
});

async function run(q: string): Promise<void> {
    controller?.abort();
    controller = new AbortController();
    failed.value = false;

    try {
        const response = await fetch(searchRoute.url({ query: { q } }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        groups.value = (
            (await response.json()) as { groups: SearchGroup[] }
        ).groups;
    } catch (error) {
        if ((error as Error).name === 'AbortError') {
            return;
        }

        failed.value = true;
        groups.value = [];
    }

    loading.value = false;
}

function go(result: SearchResult): void {
    open.value = false;
    term.value = '';
    input.value?.blur();
    router.visit(result.url);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
        input.value?.blur();

        return;
    }

    if (!flat.value.length) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        active.value = (active.value + 1) % flat.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value =
            (active.value - 1 + flat.value.length) % flat.value.length;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        go(flat.value[Math.max(active.value, 0)]);
    }
}

function indexOf(result: SearchResult): number {
    return flat.value.indexOf(result);
}

onClickOutside(root, () => (open.value = false));

// Ctrl/Cmd + K focuses search from anywhere.
useEventListener(window, 'keydown', (event: KeyboardEvent) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        input.value?.focus();
    }
});

onBeforeUnmount(() => {
    clearTimeout(timer);
    controller?.abort();
});
</script>

<template>
    <div
        ref="root"
        class="relative mx-auto min-w-0 flex-1 md:w-[min(310px,30vw)] md:flex-none"
    >
        <label class="relative block">
            <span class="sr-only">Search</span>
            <AppIcon
                name="search"
                :size="19"
                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground"
            />
            <input
                ref="input"
                v-model="term"
                type="search"
                role="combobox"
                autocomplete="off"
                placeholder="Search anything..."
                :aria-expanded="showPanel"
                :aria-controls="listId"
                :aria-activedescendant="
                    active >= 0 ? `${listId}-${active}` : undefined
                "
                class="h-10 w-full rounded-lg border bg-muted pr-12 pl-10 text-[13px] outline-none placeholder:text-muted-foreground focus:border-ring focus:bg-card focus:ring-3 focus:ring-accent"
                @focus="open = true"
                @keydown="onKeydown"
            />
            <kbd
                v-if="!term"
                class="pointer-events-none absolute top-1/2 right-2.5 hidden -translate-y-1/2 rounded border bg-card px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground lg:block"
                >Ctrl K</kbd
            >
        </label>

        <div
            v-if="showPanel"
            class="absolute top-[calc(100%+6px)] right-0 left-0 z-40 max-h-[70vh] min-w-[280px] overflow-y-auto rounded-xl border bg-popover p-1.5 shadow-float"
        >
            <div
                v-if="loading"
                class="flex items-center gap-2 px-3 py-4 text-xs text-muted-foreground"
            >
                <Spinner /> Searching…
            </div>
            <p
                v-else-if="failed"
                class="px-3 py-4 text-xs text-danger"
                role="alert"
            >
                Search is unavailable right now. Please try again.
            </p>
            <p
                v-else-if="!groups.length"
                class="px-3 py-4 text-xs text-muted-foreground"
            >
                No results for “{{ term.trim() }}”.
            </p>
            <ul v-else :id="listId" role="listbox" aria-label="Search results">
                <template v-for="group in groups" :key="group.group">
                    <li role="presentation" class="eyebrow px-3 pt-2.5 pb-1.5">
                        {{ group.group }}
                    </li>
                    <li
                        v-for="result in group.results"
                        :id="`${listId}-${indexOf(result)}`"
                        :key="result.url"
                        role="option"
                        :aria-selected="active === indexOf(result)"
                        class="flex cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2"
                        :class="
                            active === indexOf(result)
                                ? 'bg-accent text-accent-foreground'
                                : 'hover:bg-muted'
                        "
                        @mouseenter="active = indexOf(result)"
                        @mousedown.prevent="go(result)"
                    >
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-lg bg-accent text-accent-foreground"
                        >
                            <AppIcon :name="result.icon" :size="17" />
                        </span>
                        <span class="min-w-0">
                            <span
                                class="block truncate text-[13px] font-semibold"
                                >{{ result.title }}</span
                            >
                            <span
                                v-if="result.subtitle"
                                class="block truncate text-[11px] text-muted-foreground"
                                >{{ result.subtitle }}</span
                            >
                        </span>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</template>
