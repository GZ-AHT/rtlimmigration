<script setup lang="ts">
import AppIcon from '@/components/panel/AppIcon.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/types';

/** Light / dark / system switch (REQ §21.6). */
const { appearance, resolvedAppearance, updateAppearance } = useAppearance();

const options: { value: Appearance; label: string; icon: string }[] = [
    { value: 'light', label: 'Light', icon: 'light_mode' },
    { value: 'dark', label: 'Dark', icon: 'dark_mode' },
    { value: 'system', label: 'System', icon: 'desktop_windows' },
];
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="grid size-9 place-items-center rounded-lg text-muted-foreground hover:bg-accent hover:text-accent-foreground"
            aria-label="Change theme"
        >
            <AppIcon
                :name="
                    resolvedAppearance === 'dark' ? 'dark_mode' : 'light_mode'
                "
            />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-40">
            <DropdownMenuRadioGroup
                :model-value="appearance"
                @update:model-value="(v) => updateAppearance(v as Appearance)"
            >
                <DropdownMenuRadioItem
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                    class="gap-2"
                >
                    <AppIcon :name="option.icon" :size="16" />
                    {{ option.label }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
