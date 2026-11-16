<script setup lang="ts">
import AppIcon from '@/components/panel/AppIcon.vue';
import { formatDateTime } from '@/lib/format';
import type { ApplicationHistoryEntry } from '@/types';

/**
 * REQ §13.3: every status update with previous and new status, who, when,
 * remarks, supporting documents and whether the student was notified.
 */
defineProps<{ history: ApplicationHistoryEntry[]; staff?: boolean }>();

function heading(h: ApplicationHistoryEntry): string {
    if (h.event === 'created') {
        return `Application started · ${h.to}`;
    }

    if (h.event === 'update') {
        return `Update · ${h.to}`;
    }

    return h.from && h.from !== h.to ? `${h.from} → ${h.to}` : h.to;
}
</script>

<template>
    <ol class="relative ml-1.5 border-l">
        <li
            v-for="h in history"
            :key="h.id"
            class="relative pb-4 pl-5 last:pb-0"
        >
            <span
                class="absolute top-1 -left-[5px] size-2.5 rounded-full border-2 border-card"
                :class="
                    h.event === 'decision' || h.event === 'withdrawn'
                        ? 'bg-foreground'
                        : 'bg-primary'
                "
            />
            <p class="text-[13px] font-semibold">{{ heading(h) }}</p>
            <p
                v-if="h.note && h.event !== 'created'"
                class="mt-0.5 text-[13px] whitespace-pre-line text-secondary-foreground"
            >
                {{ h.note }}
            </p>
            <ul v-if="h.documents.length" class="mt-1.5 flex flex-wrap gap-1.5">
                <li v-for="d in h.documents" :key="d.id">
                    <a
                        :href="d.url"
                        target="_blank"
                        rel="noopener"
                        class="tiny-action"
                        ><AppIcon name="attach_file" :size="14" />
                        {{ d.name }}</a
                    >
                </li>
            </ul>
            <p class="mt-1 text-[11px] text-muted-foreground">
                <template v-if="staff">{{ h.by ?? 'System' }} · </template
                >{{ formatDateTime(h.at) }}
                <template v-if="staff">
                    ·
                    {{
                        h.notified ? 'student notified' : 'student not notified'
                    }}</template
                >
            </p>
        </li>
    </ol>
</template>
