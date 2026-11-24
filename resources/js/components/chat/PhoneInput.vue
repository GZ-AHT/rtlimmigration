<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';

/**
 * Phone number with a searchable country picker (flag and dialing code).
 * The server checks the number against the country's real numbering rules;
 * this only collects the country and what was typed.
 */
export type Country = {
    code: string;
    name: string;
    dial: string;
    flag: string;
};

const props = defineProps<{
    countries: Country[];
    id: string;
    invalid?: boolean;
    describedBy?: string;
    fieldClass: string;
}>();
const country = defineModel<string>('country', { required: true });
const phone = defineModel<string>('phone', { default: '' });

const open = ref(false);
const query = ref('');
const active = ref(0);
const root = ref<HTMLElement | null>(null);
const search = ref<HTMLInputElement | null>(null);
const list = ref<HTMLElement | null>(null);

const selected = computed(
    () =>
        props.countries.find((c) => c.code === country.value) ??
        props.countries[0],
);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase().replace(/^\+/, '');

    if (!q) {
        return props.countries;
    }

    return props.countries.filter(
        (c) =>
            c.name.toLowerCase().includes(q) ||
            c.code.toLowerCase() === q ||
            c.dial.slice(1).startsWith(q),
    );
});

watch(filtered, () => (active.value = 0));

async function toggle(): Promise<void> {
    open.value = !open.value;

    if (open.value) {
        query.value = '';
        active.value = Math.max(
            0,
            filtered.value.findIndex((c) => c.code === country.value),
        );
        await nextTick();
        search.value?.focus();
        scrollActive();
    }
}

function choose(c: Country): void {
    country.value = c.code;
    open.value = false;
}

function scrollActive(): void {
    void nextTick(() =>
        list.value
            ?.querySelector<HTMLElement>('[data-active="true"]')
            ?.scrollIntoView({ block: 'nearest' }),
    );
}

function onKey(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const n = filtered.value.length;
        active.value =
            (active.value + (event.key === 'ArrowDown' ? 1 : -1) + n) %
            Math.max(n, 1);
        scrollActive();
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const c = filtered.value[active.value];

        if (c) {
            choose(c);
        }
    }
}

function onOutside(event: MouseEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        open.value = false;
    }
}

watch(open, (v) =>
    v
        ? document.addEventListener('mousedown', onOutside)
        : document.removeEventListener('mousedown', onOutside),
);
onBeforeUnmount(() => document.removeEventListener('mousedown', onOutside));
</script>

<template>
    <div ref="root" class="relative flex gap-2">
        <button
            type="button"
            :class="[
                'flex h-[50px] shrink-0 items-center gap-1.5 rounded-[10px] border border-site-field-border bg-site-field px-2.5 text-sm text-site-ink outline-none focus:border-site-red focus:ring-3 focus:ring-site-red/15',
            ]"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-label="`Country: ${selected?.name}, ${selected?.dial}`"
            @click="toggle"
        >
            <span class="text-lg leading-none" aria-hidden="true">{{
                selected?.flag
            }}</span>
            <span class="tabular-nums">{{ selected?.dial }}</span>
            <AppIcon name="expand_more" :size="16" />
        </button>

        <input
            :id="id"
            v-model="phone"
            type="tel"
            inputmode="tel"
            autocomplete="tel-national"
            maxlength="30"
            :class="[fieldClass, 'min-w-0 flex-1']"
            :aria-invalid="invalid || undefined"
            :aria-describedby="describedBy"
            placeholder="01XXXXXXXXX"
        />

        <div
            v-if="open"
            class="absolute top-[54px] left-0 z-30 w-[min(320px,100%)] rounded-[12px] border border-site-line bg-white shadow-xl"
            @keydown="onKey"
        >
            <div class="border-b border-site-line p-2">
                <input
                    ref="search"
                    v-model="query"
                    type="search"
                    placeholder="Search country or code"
                    aria-label="Search country or dialing code"
                    class="h-10 w-full rounded-[8px] border border-site-field-border bg-site-field px-3 text-sm text-site-ink outline-none focus:border-site-red"
                />
            </div>
            <ul
                ref="list"
                role="listbox"
                aria-label="Countries"
                class="max-h-56 overflow-y-auto py-1"
            >
                <li
                    v-for="(c, i) in filtered"
                    :key="c.code"
                    role="option"
                    :aria-selected="c.code === country"
                    :data-active="i === active"
                    :class="[
                        'flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-site-ink',
                        i === active ? 'bg-site-field' : '',
                        c.code === country ? 'font-semibold' : '',
                    ]"
                    @mousedown.prevent="choose(c)"
                    @mousemove="active = i"
                >
                    <span class="text-lg leading-none" aria-hidden="true">{{
                        c.flag
                    }}</span>
                    <span class="min-w-0 flex-1 truncate">{{ c.name }}</span>
                    <span class="text-site-muted tabular-nums">{{
                        c.dial
                    }}</span>
                </li>
                <li
                    v-if="!filtered.length"
                    class="px-3 py-3 text-sm text-site-muted"
                >
                    No country found.
                </li>
            </ul>
        </div>
    </div>
</template>
