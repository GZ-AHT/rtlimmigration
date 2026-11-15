<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppearanceToggle from '@/components/panel/AppearanceToggle.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import GlobalSearch from '@/components/panel/GlobalSearch.vue';
import NotificationBell from '@/components/panel/NotificationBell.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem } from '@/types';

/** Top bar from admin-demo.html (.topbar). */
const props = defineProps<{
    breadcrumbs: BreadcrumbItem[];
    sidebarOpen: boolean;
}>();
const emit = defineEmits<{ toggleSidebar: [] }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const area = computed(() => page.props.area);
const current = computed(
    () => props.breadcrumbs[props.breadcrumbs.length - 1]?.title ?? 'Overview',
);
const roleLabel = computed(() => {
    const role = page.props.auth.roles[0];

    return role
        ? role
              .split('-')
              .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
              .join(' ')
        : '';
});
</script>

<template>
    <header
        class="sticky top-0 z-10 flex h-[66px] items-center gap-3 border-b bg-card px-4 md:h-20 md:gap-6 md:px-[30px]"
    >
        <button
            type="button"
            class="grid size-9 place-items-center rounded-lg text-muted-foreground hover:bg-accent hover:text-accent-foreground"
            :aria-expanded="sidebarOpen"
            aria-controls="panel-sidebar"
            aria-label="Toggle navigation"
            @click="emit('toggleSidebar')"
        >
            <AppIcon name="menu" :size="21" />
        </button>

        <p
            class="hidden text-[13px] whitespace-nowrap text-muted-foreground lg:block"
        >
            {{ area?.label }}&nbsp; / &nbsp;<b
                class="font-semibold text-foreground"
                >{{ current }}</b
            >
        </p>

        <GlobalSearch />

        <div class="ml-auto flex items-center gap-1.5 md:gap-3">
            <AppearanceToggle />
            <NotificationBell />

            <DropdownMenu>
                <DropdownMenuTrigger
                    class="flex items-center gap-2.5 rounded-lg py-1 pr-1 text-left text-[13px] md:border-l md:pl-[18px]"
                    aria-label="Account menu"
                >
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-full bg-accent font-bold text-accent-foreground"
                    >
                        {{ getInitials(user.name) }}
                    </span>
                    <span class="hidden min-w-0 md:block">
                        <b class="block max-w-[160px] truncate">{{
                            user.name
                        }}</b>
                        <small
                            class="mt-0.5 block text-[11px] text-muted-foreground"
                            >{{ roleLabel }}</small
                        >
                    </span>
                    <AppIcon
                        name="expand_more"
                        :size="18"
                        class="hidden text-muted-foreground md:inline-flex"
                    />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-60">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
