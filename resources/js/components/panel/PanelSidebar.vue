<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import PanelNavItem from '@/components/panel/PanelNavItem.vue';

/**
 * Sidebar from admin-demo.html (.sidebar). Items come from the server
 * already filtered by permission (App\Support\Navigation).
 */
defineProps<{ open: boolean }>();
const emit = defineEmits<{ navigate: [] }>();

const page = usePage();

const navigation = computed(() => page.props.navigation);
const area = computed(() => page.props.area);
const auth = computed(() => page.props.auth);

const accessTitle = computed(() =>
    auth.value.isSuperAdmin ? 'Full access enabled' : 'Role-based access',
);
const roleLabel = computed(() =>
    auth.value.roles
        .map((r) =>
            r
                .split('-')
                .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
                .join(' '),
        )
        .join(', '),
);
</script>

<template>
    <aside
        id="panel-sidebar"
        class="fixed inset-y-0 left-0 z-30 flex w-[248px] flex-col overflow-y-auto border-r bg-sidebar transition-transform duration-250"
        :class="
            open
                ? 'translate-x-0 shadow-float lg:shadow-none'
                : '-translate-x-full'
        "
        aria-label="Main navigation"
    >
        <div class="flex h-[86px] shrink-0 items-center gap-3 px-6 py-5">
            <div
                class="grid size-9 place-items-center rounded-[10px] bg-primary font-display text-lg font-extrabold text-primary-foreground"
                aria-hidden="true"
            >
                R
            </div>
            <div class="min-w-0">
                <strong
                    class="block truncate font-display text-[17px] leading-tight font-extrabold whitespace-nowrap text-foreground"
                >
                    {{ page.props.name }}
                </strong>
                <small
                    class="mt-0.5 block text-[10px] font-bold tracking-[1.2px] text-muted-foreground uppercase"
                >
                    {{ area?.label }}
                </small>
            </div>
        </div>

        <nav class="flex-1">
            <div v-for="group in navigation" :key="group.label">
                <p
                    class="px-6 pt-5 pb-2.5 text-[10px] font-bold tracking-[1.1px] text-muted-foreground uppercase"
                >
                    {{ group.label }}
                </p>
                <ul class="px-3.5">
                    <PanelNavItem
                        v-for="item in group.items"
                        :key="item.title"
                        :item="item"
                        @navigate="emit('navigate')"
                    />
                </ul>
            </div>
        </nav>

        <div
            class="mx-4 mt-9 mb-4 flex gap-2.5 rounded-[10px] border p-[13px] text-xs"
        >
            <AppIcon name="shield_person" :size="18" class="text-primary" />
            <div class="min-w-0">
                <b class="mb-0.5 block text-[13px]">{{ accessTitle }}</b>
                <span class="text-muted-foreground">{{
                    roleLabel || 'No role assigned'
                }}</span>
            </div>
        </div>
    </aside>
</template>
