<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import { useActiveNav } from '@/composables/useActiveNav';
import type { PanelNavItem } from '@/types';

/** One sidebar entry: a link, a planned placeholder, or a sub-menu. */
const props = defineProps<{ item: PanelNavItem }>();
const emit = defineEmits<{ navigate: [] }>();

const { activeHref } = useActiveNav();
const isCurrentOrParentUrl = (href: string) => activeHref.value === href;

const hasChildren = computed(() => props.item.children.length > 0);
const childActive = computed(() =>
    props.item.children.some((c) => c.href && isCurrentOrParentUrl(c.href)),
);
const expanded = ref(childActive.value);

watch(childActive, (active) => {
    if (active) {
        expanded.value = true;
    }
});

const rowClass =
    'my-1 flex h-10 w-full items-center gap-3 rounded-lg px-3 text-left text-sm text-sidebar-foreground';
const activeClass =
    'bg-sidebar-accent font-semibold text-sidebar-accent-foreground';
const hoverClass =
    'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground';
</script>

<template>
    <li>
        <!-- Sub-menu (admin-demo.html .subnav) -->
        <template v-if="hasChildren">
            <button
                type="button"
                :class="[
                    rowClass,
                    hoverClass,
                    childActive && 'text-sidebar-accent-foreground',
                    item.planned && 'opacity-50',
                ]"
                :aria-expanded="expanded"
                @click="expanded = !expanded"
            >
                <AppIcon :name="item.icon" />
                <span class="min-w-0 flex-1 truncate">{{ item.title }}</span>
                <AppIcon
                    name="expand_more"
                    :size="18"
                    class="text-muted-foreground transition-transform"
                    :class="{ 'rotate-180': expanded }"
                />
            </button>
            <ul v-show="expanded" class="mb-1.5 ml-[35px] border-l pl-3">
                <li v-for="child in item.children" :key="child.title">
                    <Link
                        v-if="child.href"
                        :href="child.href"
                        class="flex h-[30px] items-center rounded-md px-3 text-xs text-muted-foreground hover:text-sidebar-accent-foreground"
                        :class="{
                            'font-semibold text-sidebar-accent-foreground':
                                isCurrentOrParentUrl(child.href),
                        }"
                        :aria-current="
                            isCurrentOrParentUrl(child.href)
                                ? 'page'
                                : undefined
                        "
                        @click="emit('navigate')"
                    >
                        {{ child.title }}
                    </Link>
                    <span
                        v-else
                        class="flex h-[30px] cursor-not-allowed items-center gap-2 px-3 text-xs text-muted-foreground opacity-60"
                        :title="`Planned for phase ${child.phase}`"
                        aria-disabled="true"
                    >
                        {{ child.title }}
                        <span
                            class="ml-auto rounded bg-muted px-1.5 py-0.5 text-[9px] font-bold"
                            >P{{ child.phase }}</span
                        >
                    </span>
                </li>
            </ul>
        </template>

        <Link
            v-else-if="item.href"
            :href="item.href"
            :class="[
                rowClass,
                hoverClass,
                isCurrentOrParentUrl(item.href) && activeClass,
            ]"
            :aria-current="isCurrentOrParentUrl(item.href) ? 'page' : undefined"
            @click="emit('navigate')"
        >
            <AppIcon :name="item.icon" />
            <span class="truncate">{{ item.title }}</span>
        </Link>

        <span
            v-else
            :class="[rowClass, 'cursor-not-allowed opacity-50']"
            :title="`Planned for phase ${item.phase}`"
            aria-disabled="true"
        >
            <AppIcon :name="item.icon" />
            <span class="truncate">{{ item.title }}</span>
            <span
                class="ml-auto rounded bg-muted px-1.5 py-0.5 text-[9px] font-bold"
                >P{{ item.phase }}</span
            >
        </span>
    </li>
</template>
