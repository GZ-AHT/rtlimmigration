<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { flag } from '@/lib/flag';

/**
 * Searchable destination picker (skill migration): type a country name,
 * pick from the filtered list with mouse or arrow keys + Enter. Shows flags.
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

function choose(c: CountryChoice): void {
    model.value = c.slug;
    query.value = c.country;
    open.value = false;
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
    <div class="relative">
        <label :for="id" class="sr-only">{{ label }}</label>
        <span
            v-if="selected"
            class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-[20px] leading-none"
            aria-hidden="true"
            >{{ flag(selected.iso2) }}</span
        >
        <input
            :id="id"
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
            class="h-[54px] w-full rounded-[10px] border border-site-field-border bg-white pr-10 text-[15px] text-site-ink outline-none placeholder:text-site-muted focus:border-site-red focus:ring-3 focus:ring-site-red/15"
            :class="selected ? 'pl-12' : 'pl-4'"
            @input="typed"
            @focus="open = true"
            @blur="open = false"
            @keydown="key"
        />
        <svg
            class="pointer-events-none absolute top-1/2 right-4 size-[18px] -translate-y-1/2 text-site-muted"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
            aria-hidden="true"
        >
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" stroke-linecap="round" />
        </svg>
        <ul
            v-show="open"
            :id="`${id}-list`"
            role="listbox"
            :aria-label="label"
            class="absolute inset-x-0 top-[calc(100%+6px)] z-30 max-h-72 overflow-y-auto rounded-[12px] border border-site-line bg-white py-1.5 text-left shadow-[0_16px_40px_rgba(0,0,0,0.16)]"
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
                class="flex cursor-pointer items-center gap-3 px-4 py-2.5 text-[15px] text-site-ink"
                :class="i === active ? 'bg-site-sand' : ''"
                @mousedown.prevent="choose(c)"
                @mouseenter="active = i"
            >
                <span class="text-[20px] leading-none" aria-hidden="true">{{
                    flag(c.iso2)
                }}</span>
                <span class="flex-1">{{ c.country }}</span>
                <span
                    v-if="c.slug === model"
                    class="text-site-red"
                    aria-hidden="true"
                    >✓</span
                >
            </li>
        </ul>
    </div>
</template>
