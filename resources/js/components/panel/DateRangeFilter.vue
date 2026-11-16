<script setup lang="ts">
import { Input } from '@/components/ui/input';

/**
 * From / to date pair for report and list filters, with quick ranges.
 * Dates are plain YYYY-MM-DD strings; the server validates them.
 */
withDefaults(defineProps<{ presets?: boolean }>(), { presets: true });

const from = defineModel<string>('from', { default: '' });
const to = defineModel<string>('to', { default: '' });

function iso(date: Date): string {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 10);
}

const ranges: { label: string; range: () => [Date, Date] }[] = [
    {
        label: 'This month',
        range: () => {
            const now = new Date();

            return [new Date(now.getFullYear(), now.getMonth(), 1), now];
        },
    },
    {
        label: 'Last month',
        range: () => {
            const now = new Date();

            return [
                new Date(now.getFullYear(), now.getMonth() - 1, 1),
                new Date(now.getFullYear(), now.getMonth(), 0),
            ];
        },
    },
    {
        label: 'This year',
        range: () => {
            const now = new Date();

            return [new Date(now.getFullYear(), 0, 1), now];
        },
    },
];

function pick(range: () => [Date, Date]): void {
    const [start, end] = range();
    from.value = iso(start);
    to.value = iso(end);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <label class="flex items-center gap-1.5 text-xs text-muted-foreground">
            From
            <Input
                v-model="from"
                type="date"
                :max="to || undefined"
                class="h-9 w-[150px]"
            />
        </label>
        <label class="flex items-center gap-1.5 text-xs text-muted-foreground">
            To
            <Input
                v-model="to"
                type="date"
                :min="from || undefined"
                class="h-9 w-[150px]"
            />
        </label>
        <template v-if="presets">
            <button
                v-for="r in ranges"
                :key="r.label"
                type="button"
                class="tiny-action"
                @click="pick(r.range)"
            >
                {{ r.label }}
            </button>
        </template>
    </div>
</template>
