<script setup lang="ts" generic="Row extends Record<string, unknown>">
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import { Skeleton } from '@/components/ui/skeleton';
import type { SortDirection, TableColumn } from '@/types';

/**
 * Data table styled after admin-demo.html. Data, sorting and paging are
 * server-driven: the table only emits `sort` and renders what it is given.
 *
 * Cell slots: #cell-<columnKey>="{ row, value }", plus #actions="{ row }".
 */
const props = withDefaults(
    defineProps<{
        columns: TableColumn<Row>[];
        rows: Row[];
        rowKey?: keyof Row & string;
        sortKey?: string | null;
        sortDirection?: SortDirection;
        loading?: boolean;
        emptyTitle?: string;
        emptyDescription?: string;
        emptyIcon?: string;
        caption?: string;
    }>(),
    {
        rowKey: 'id' as never,
        sortKey: null,
        sortDirection: 'asc',
        loading: false,
        emptyTitle: 'Nothing to show yet',
        emptyDescription: undefined,
        emptyIcon: 'inbox',
        caption: undefined,
    },
);

const emit = defineEmits<{
    sort: [key: string, direction: SortDirection];
}>();

const slots = defineSlots<
    Record<
        string,
        ((props: { row: Row; value: unknown }) => unknown) | undefined
    > & {
        actions?: (props: { row: Row }) => unknown;
        empty?: () => unknown;
    }
>();

const hasActions = computed(() => Boolean(slots.actions));
const colspan = computed(
    () => props.columns.length + (hasActions.value ? 1 : 0),
);

const hideClass = {
    sm: 'hidden sm:table-cell',
    md: 'hidden md:table-cell',
    lg: 'hidden lg:table-cell',
};
const alignClass = {
    left: 'text-left',
    right: 'text-right',
    center: 'text-center',
};

function columnClass(column: TableColumn<Row>): string {
    return [
        column.hideBelow ? hideClass[column.hideBelow] : '',
        alignClass[column.align ?? 'left'],
        column.class ?? '',
    ].join(' ');
}

function toggleSort(column: TableColumn<Row>): void {
    if (!column.sortable) {
        return;
    }

    const direction: SortDirection =
        props.sortKey === column.key && props.sortDirection === 'asc'
            ? 'desc'
            : 'asc';
    emit('sort', column.key, direction);
}

function ariaSort(column: TableColumn<Row>) {
    if (!column.sortable || props.sortKey !== column.key) {
        return undefined;
    }

    return props.sortDirection === 'asc' ? 'ascending' : 'descending';
}

function display(row: Row, column: TableColumn<Row>): string {
    if (column.format) {
        return column.format(row);
    }

    const value = row[column.key];

    return value === null || value === undefined || value === ''
        ? '—'
        : String(value);
}
</script>

<template>
    <div class="relative overflow-x-auto">
        <table class="w-full border-collapse text-[13px]">
            <caption v-if="caption" class="sr-only">
                {{
                    caption
                }}
            </caption>
            <thead>
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        :aria-sort="ariaSort(column)"
                        :class="[
                            'bg-muted px-[18px] py-3.5 text-[11px] font-bold tracking-[0.2px] whitespace-nowrap text-muted-foreground',
                            columnClass(column),
                        ]"
                    >
                        <button
                            v-if="column.sortable"
                            type="button"
                            class="inline-flex items-center gap-1 hover:text-foreground"
                            @click="toggleSort(column)"
                        >
                            {{ column.label }}
                            <AppIcon
                                :name="
                                    sortKey === column.key
                                        ? sortDirection === 'asc'
                                            ? 'arrow_upward'
                                            : 'arrow_downward'
                                        : 'unfold_more'
                                "
                                :size="14"
                            />
                        </button>
                        <template v-else>{{ column.label }}</template>
                    </th>
                    <th
                        v-if="hasActions"
                        scope="col"
                        class="bg-muted px-[18px] py-3.5 text-right text-[11px] font-bold text-muted-foreground"
                    >
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <template v-if="loading">
                    <tr v-for="n in 5" :key="`skeleton-${n}`">
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            :class="[
                                'border-t px-[18px] py-[15px]',
                                columnClass(column),
                            ]"
                        >
                            <Skeleton class="h-4 w-full max-w-[160px]" />
                        </td>
                        <td
                            v-if="hasActions"
                            class="border-t px-[18px] py-[15px]"
                        />
                    </tr>
                </template>
                <tr v-else-if="rows.length === 0">
                    <td :colspan="colspan" class="border-t">
                        <slot name="empty">
                            <EmptyState
                                :title="emptyTitle"
                                :description="emptyDescription"
                                :icon="emptyIcon"
                            />
                        </slot>
                    </td>
                </tr>
                <template v-else>
                    <tr
                        v-for="(row, index) in rows"
                        :key="String(row[rowKey as keyof Row] ?? index)"
                        class="hover:bg-muted/60"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            :class="[
                                'border-t px-[18px] py-[15px] text-secondary-foreground',
                                columnClass(column),
                            ]"
                        >
                            <slot
                                :name="`cell-${column.key}`"
                                :row="row"
                                :value="row[column.key]"
                            >
                                {{ display(row, column) }}
                            </slot>
                        </td>
                        <td
                            v-if="hasActions"
                            class="border-t px-[18px] py-[15px] text-right"
                        >
                            <div class="inline-flex gap-1.5">
                                <slot name="actions" :row="row" />
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</template>
