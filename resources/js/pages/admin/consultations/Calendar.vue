<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import { Button } from '@/components/ui/button';
import {
    availability,
    calendar,
    index,
    show,
} from '@/routes/admin/consultations';

/**
 * Bookings on a month, week or day calendar. Everything is drawn in the
 * schedule's timezone, so a booking sits where the working hours say it does.
 */
type Row = {
    id: number;
    reference: string;
    name: string;
    status: string;
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
};

const props = defineProps<{
    view: 'month' | 'week' | 'day';
    date: string;
    from: string;
    to: string;
    timezone: string;
    bookings: Row[];
    hours: Record<string, { enabled: boolean; start: string; end: string }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Calendar', href: calendar() }] },
});

const HOUR = 56; // px per hour in the week and day views
const DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

/** Date (YYYY-MM-DD) and minutes since midnight of an instant, in the schedule's timezone. */
function zoned(iso: string): { date: string; minutes: number } {
    const p = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
            timeZone: props.timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        })
            .formatToParts(new Date(iso))
            .map((x) => [x.type, x.value]),
    );

    return {
        date: `${p.year}-${p.month}-${p.day}`,
        minutes: Number(p.hour) * 60 + Number(p.minute),
    };
}

const addDays = (date: string, n: number): string => {
    const d = new Date(`${date}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + n);

    return d.toISOString().slice(0, 10);
};

const dayName = (date: string): string =>
    DAY_KEYS[new Date(`${date}T00:00:00Z`).getUTCDay()];

const days = computed(() => {
    const list: string[] = [];

    for (let d = props.from; d <= props.to; d = addDays(d, 1)) {
        list.push(d);
    }

    return list;
});

const today = computed(() => zoned(new Date().toISOString()).date);

const byDay = computed(() => {
    const map = new Map<string, (Row & { startMin: number })[]>();

    for (const b of props.bookings) {
        const z = zoned(b.starts_at);
        map.set(z.date, [
            ...(map.get(z.date) ?? []),
            { ...b, startMin: z.minutes },
        ]);
    }

    return map;
});

const title = computed(() => {
    const d = new Date(`${props.date}T00:00:00Z`);
    const opt = (o: Intl.DateTimeFormatOptions) =>
        new Intl.DateTimeFormat('en', { ...o, timeZone: 'UTC' });

    if (props.view === 'month') {
        return opt({ month: 'long', year: 'numeric' }).format(d);
    }

    if (props.view === 'day') {
        return opt({
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }).format(d);
    }

    return `${opt({ day: 'numeric', month: 'short' }).format(new Date(`${props.from}T00:00:00Z`))} – ${opt({ day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${props.to}T00:00:00Z`))}`;
});

function go(view: string, date: string): void {
    router.get(calendar().url, { view, date }, { preserveScroll: true });
}

function step(dir: -1 | 1): void {
    const d = new Date(`${props.date}T00:00:00Z`);

    if (props.view === 'month') {
        d.setUTCMonth(d.getUTCMonth() + dir, 1);
        go(props.view, d.toISOString().slice(0, 10));
    } else {
        go(
            props.view,
            addDays(props.date, dir * (props.view === 'week' ? 7 : 1)),
        );
    }
}

/* Time grid (week and day) */
const gridDays = computed(() =>
    props.view === 'day' ? [props.date] : days.value,
);

const range = computed(() => {
    let start = 8 * 60;
    let end = 18 * 60;

    for (const h of Object.values(props.hours)) {
        if (h.enabled) {
            start = Math.min(start, Number(h.start.slice(0, 2)) * 60);
            end = Math.max(
                end,
                Number(h.end.slice(0, 2)) * 60 +
                    (h.end.slice(3) === '00' ? 0 : 60),
            );
        }
    }

    for (const b of props.bookings) {
        const z = zoned(b.starts_at);
        start = Math.min(start, Math.floor(z.minutes / 60) * 60);
        end = Math.max(
            end,
            Math.ceil((z.minutes + b.duration_minutes) / 60) * 60,
        );
    }

    return { start: Math.max(0, start), end: Math.min(24 * 60, end) };
});

const hourLabels = computed(() => {
    const labels: number[] = [];

    for (let m = range.value.start; m < range.value.end; m += 60) {
        labels.push(m / 60);
    }

    return labels;
});

const hourText = (h: number): string =>
    `${h % 12 === 0 ? 12 : h % 12} ${h < 12 ? 'AM' : 'PM'}`;
const top = (minutes: number): number =>
    ((minutes - range.value.start) / 60) * HOUR;
const timeText = (iso: string): string =>
    new Intl.DateTimeFormat('en', {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: props.timezone,
    }).format(new Date(iso));

const tone = (status: string): string =>
    ({
        pending: 'border-warning bg-warning-soft text-warning',
        confirmed: 'border-success bg-success-soft text-success',
        completed: 'border-info bg-info-soft text-info',
        cancelled: 'border-border bg-muted text-muted-foreground line-through',
    })[status] ?? 'border-border bg-muted';

const dayLabel = (date: string): { dow: string; num: number } => ({
    dow: new Intl.DateTimeFormat('en', {
        weekday: 'short',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`)),
    num: Number(date.slice(8)),
});
</script>

<template>
    <Head title="Consultation calendar" />

    <PageHeader
        eyebrow="Video consultations"
        title="Calendar"
        :description="`Times are shown in ${timezone}.`"
    >
        <template #actions>
            <Button as-child variant="outline"
                ><Link :href="index()"
                    ><AppIcon name="list" :size="16" /> Bookings</Link
                ></Button
            >
            <Button as-child variant="outline"
                ><Link :href="availability()"
                    ><AppIcon name="schedule" :size="16" /> Availability</Link
                ></Button
            >
        </template>
    </PageHeader>

    <PanelCard>
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <Button variant="outline" size="sm" @click="go(view, today)"
                >Today</Button
            >
            <Button
                variant="ghost"
                size="icon"
                aria-label="Previous"
                @click="step(-1)"
                ><AppIcon name="chevron_left" :size="20"
            /></Button>
            <Button
                variant="ghost"
                size="icon"
                aria-label="Next"
                @click="step(1)"
                ><AppIcon name="chevron_right" :size="20"
            /></Button>
            <h2 class="text-base font-bold">{{ title }}</h2>
            <div
                class="ml-auto inline-flex rounded-lg border p-0.5"
                role="group"
                aria-label="Calendar view"
            >
                <button
                    v-for="v in ['month', 'week', 'day'] as const"
                    :key="v"
                    type="button"
                    class="rounded-md px-3 py-1.5 text-[12px] font-semibold capitalize"
                    :class="
                        view === v
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :aria-pressed="view === v"
                    @click="go(v, date)"
                >
                    {{ v }}
                </button>
            </div>
        </div>

        <!-- Month -->
        <div v-if="view === 'month'" class="overflow-x-auto">
            <div
                class="grid min-w-[720px] grid-cols-7 border-t border-l text-[12px]"
            >
                <div
                    v-for="d in days.slice(0, 7)"
                    :key="d"
                    class="border-r border-b bg-muted/40 px-2 py-1.5 font-bold text-muted-foreground"
                >
                    {{ dayLabel(d).dow }}
                </div>
                <div
                    v-for="d in days"
                    :key="d"
                    class="min-h-[104px] border-r border-b p-1.5"
                    :class="[
                        d.slice(0, 7) !== date.slice(0, 7)
                            ? 'bg-muted/30 text-muted-foreground'
                            : '',
                        hours[dayName(d)]?.enabled ? '' : 'bg-muted/20',
                    ]"
                >
                    <button
                        type="button"
                        class="mb-1 grid size-6 place-items-center rounded-full text-[12px] font-semibold hover:bg-muted"
                        :class="
                            d === today
                                ? 'bg-primary text-primary-foreground hover:bg-primary'
                                : ''
                        "
                        @click="go('day', d)"
                    >
                        {{ dayLabel(d).num }}
                    </button>
                    <Link
                        v-for="b in (byDay.get(d) ?? []).slice(0, 4)"
                        :key="b.id"
                        :href="show(b.id)"
                        class="mb-0.5 block truncate rounded border-l-2 px-1.5 py-0.5 text-[11px] font-semibold"
                        :class="tone(b.status)"
                    >
                        {{ timeText(b.starts_at) }} {{ b.name }}
                    </Link>
                    <button
                        v-if="(byDay.get(d) ?? []).length > 4"
                        type="button"
                        class="text-[11px] font-semibold text-primary"
                        @click="go('day', d)"
                    >
                        +{{ (byDay.get(d) ?? []).length - 4 }} more
                    </button>
                </div>
            </div>
        </div>

        <!-- Week and day -->
        <div v-else class="overflow-x-auto">
            <div class="min-w-[640px]">
                <div
                    class="grid"
                    :style="{
                        gridTemplateColumns: `56px repeat(${gridDays.length}, minmax(0, 1fr))`,
                    }"
                >
                    <div />
                    <button
                        v-for="d in gridDays"
                        :key="d"
                        type="button"
                        class="border-b px-2 py-2 text-center hover:bg-muted/40"
                        @click="go('day', d)"
                    >
                        <span
                            class="block text-[11px] font-bold text-muted-foreground uppercase"
                            >{{ dayLabel(d).dow }}</span
                        >
                        <span
                            class="mx-auto mt-0.5 grid size-8 place-items-center rounded-full text-base font-bold"
                            :class="
                                d === today
                                    ? 'bg-primary text-primary-foreground'
                                    : ''
                            "
                            >{{ dayLabel(d).num }}</span
                        >
                    </button>
                </div>
                <div
                    class="grid"
                    :style="{
                        gridTemplateColumns: `56px repeat(${gridDays.length}, minmax(0, 1fr))`,
                    }"
                >
                    <div class="relative">
                        <span
                            v-for="h in hourLabels"
                            :key="h"
                            class="absolute right-2 -translate-y-2 text-[10px] text-muted-foreground"
                            :style="{ top: `${top(h * 60)}px` }"
                            >{{ hourText(h) }}</span
                        >
                    </div>
                    <div
                        v-for="d in gridDays"
                        :key="d"
                        class="relative border-l"
                        :class="hours[dayName(d)]?.enabled ? '' : 'bg-muted/30'"
                        :style="{
                            height: `${((range.end - range.start) / 60) * HOUR}px`,
                        }"
                    >
                        <span
                            v-for="h in hourLabels"
                            :key="h"
                            class="absolute inset-x-0 border-t"
                            :style="{ top: `${top(h * 60)}px` }"
                        />
                        <Link
                            v-for="b in byDay.get(d) ?? []"
                            :key="b.id"
                            :href="show(b.id)"
                            class="absolute inset-x-1 overflow-hidden rounded border-l-4 px-1.5 py-1 text-[11px] leading-tight font-semibold"
                            :class="tone(b.status)"
                            :style="{
                                top: `${top(b.startMin)}px`,
                                height: `${Math.max(22, (b.duration_minutes / 60) * HOUR - 2)}px`,
                            }"
                        >
                            {{ timeText(b.starts_at) }}<br />{{ b.name }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <ul
            class="mt-4 flex flex-wrap gap-3 text-[11px] text-muted-foreground"
            aria-label="Legend"
        >
            <li
                v-for="s in ['pending', 'confirmed', 'completed', 'cancelled']"
                :key="s"
                class="inline-flex items-center gap-1.5 capitalize"
            >
                <span class="size-3 rounded border-l-4" :class="tone(s)" />{{
                    s
                }}
            </li>
        </ul>
    </PanelCard>
</template>
