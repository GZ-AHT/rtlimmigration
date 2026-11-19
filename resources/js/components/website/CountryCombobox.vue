<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { flag } from '@/lib/flag';

/**
 * Searchable destination picker (skill migration): type a country name,
 * pick from the filtered list with touch, mouse or arrow keys + Enter. Shows flags.
 * v-model is the chosen assessment slug; `query` is the typed text.
 */
export type CountryChoice = {
    slug: string;
    country: string;
    iso2: string;
    url: string;
};

const props = withDefaults(
    defineProps<{
        countries: CountryChoice[];
        placeholder?: string;
        id?: string;
        label?: string;
    }>(),
    {
        placeholder: 'Search a country…',
        id: 'country-search',
        label: 'Destination country',
    },
);
const model = defineModel<string>({ default: '' });
const query = defineModel<string>('query', { default: '' });
const emit = defineEmits<{ enter: [] }>();

const rootRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const open = ref(false);
const active = ref(0);
const selected = computed(
    () => props.countries.find((c) => c.slug === model.value) ?? null,
);
const matches = computed(() => {
    const q = query.value.trim().toLowerCase();

    // Show everything until the visitor types something different from the chosen name.
    return !q || q === selected.value?.country.toLowerCase()
        ? props.countries
        : props.countries.filter((c) => c.country.toLowerCase().includes(q));
});

watch(
    selected,
    (c) => {
        if (c) {
            query.value = c.country;
        }
    },
    { immediate: true },
);
watch(matches, () => (active.value = 0));

// Close dropdown smoothly when clicking/tapping outside the combobox
onClickOutside(rootRef, () => {
    open.value = false;
});

function choose(c: CountryChoice): void {
    model.value = c.slug;
    query.value = c.country;
    open.value = false;
}

function clear(): void {
    model.value = '';
    query.value = '';
    open.value = true;
    inputRef.value?.focus();
}

function typed(): void {
    open.value = true;
    const exact = props.countries.find(
        (c) => c.country.toLowerCase() === query.value.trim().toLowerCase(),
    );
    model.value = exact?.slug ?? '';
}

function key(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        open.value = true;
        active.value = Math.min(active.value + 1, matches.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = Math.max(active.value - 1, 0);
    } else if (event.key === 'Enter') {
        // Choosing from the open list with Enter also continues (the parent decides how);
        // with the list closed, Enter submits the surrounding form as usual.
        if (open.value && matches.value[active.value]) {
            event.preventDefault();
            choose(matches.value[active.value]);
            emit('enter');
        }
    } else if (event.key === 'Escape') {
        open.value = false;
    }
}
</script>

<template>
    <div ref="rootRef" class="relative w-full min-w-0">
        <label :for="id" class="sr-only">{{ label }}</label>

        <!-- Selected country flag icon -->
        <span
            v-if="selected"
            class="pointer-events-none absolute top-1/2 left-3.5 z-10 -translate-y-1/2 text-[20px] leading-none select-none sm:left-4"
            aria-hidden="true"
            >{{ flag(selected.iso2) }}</span
        >

        <!-- Search input field: text-base on mobile avoids iOS Safari auto-zoom -->
        <input
            :id="id"
            ref="inputRef"
            v-model="query"
            type="text"
            role="combobox"
            autocomplete="off"
            :placeholder="placeholder"
            :aria-expanded="open"
            :aria-controls="`${id}-list`"
            :aria-activedescendant="
                open && matches[active]
                    ? `${id}-opt-${matches[active].slug}`
                    : undefined
            "
            aria-autocomplete="list"
            class="h-12 w-full min-w-0 rounded-xl border border-site-field-border bg-white text-base text-site-ink transition-all outline-none placeholder:truncate placeholder:text-site-muted focus:border-site-red focus:ring-4 focus:ring-site-red/15 sm:h-[54px] sm:text-[15px]"
            :class="[
                selected ? 'pl-11 sm:pl-12' : 'pl-3.5 sm:pl-4',
                query ? 'pr-18' : 'pr-10',
            ]"
            @input="typed"
            @focus="open = true"
            @keydown="key"
        />

        <!-- Clear input button (shows whenever query is present) -->
        <button
            v-if="query"
            type="button"
            aria-label="Clear destination"
            class="absolute top-1/2 right-10 z-10 grid size-7 -translate-y-1/2 place-items-center rounded-full text-site-muted transition-colors hover:bg-site-sand hover:text-site-ink focus-visible:ring-2 focus-visible:ring-site-red focus-visible:outline-none active:scale-95"
            @click.stop="clear"
        >
            <svg
                class="size-3.5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>

        <!-- Magnifying glass icon on right -->
        <svg
            class="pointer-events-none absolute top-1/2 right-3.5 size-[18px] -translate-y-1/2 text-site-muted sm:right-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
            aria-hidden="true"
        >
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" stroke-linecap="round" />
        </svg>

        <!-- Dropdown choices listbox with smooth transition -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
        >
            <ul
                v-show="open"
                :id="`${id}-list`"
                role="listbox"
                :aria-label="label"
                class="absolute inset-x-0 top-[calc(100%+6px)] z-40 max-h-60 overflow-y-auto overscroll-contain rounded-xl border border-site-line bg-white py-1.5 text-left shadow-[0_16px_40px_rgba(0,0,0,0.18)] sm:max-h-72"
            >
                <li
                    v-if="!matches.length"
                    class="px-4 py-3 text-[14px] text-site-muted"
                >
                    No assessment for “{{ query }}” yet.
                </li>
                <li
                    v-for="(c, i) in matches"
                    :id="`${id}-opt-${c.slug}`"
                    :key="c.slug"
                    role="option"
                    :aria-selected="c.slug === model"
                    class="flex min-h-[48px] cursor-pointer items-center gap-3 px-4 py-2.5 text-[15px] text-site-ink transition-colors active:bg-site-blush-bg/70 sm:py-2.5"
                    :class="[
                        i === active ? 'bg-site-sand' : '',
                        c.slug === model ? 'font-semibold text-site-red' : '',
                    ]"
                    @pointerdown.prevent="choose(c)"
                    @click="choose(c)"
                    @mouseenter="active = i"
                >
                    <span
                        class="text-[20px] leading-none select-none"
                        aria-hidden="true"
                        >{{ flag(c.iso2) }}</span
                    >
                    <span class="flex-1 truncate">{{ c.country }}</span>
                    <span
                        v-if="c.slug === model"
                        class="flex size-5 items-center justify-center rounded-full bg-site-red/10 text-site-red"
                        aria-label="Selected"
                    >
                        <svg
                            class="size-3"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </span>
                </li>
            </ul>
        </Transition>
    </div>
</template>
