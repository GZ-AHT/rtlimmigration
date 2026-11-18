<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import { Button } from '@/components/ui/button';
import type { PageSection } from '@/types';

/**
 * Homepage "Banner design" choice. Each design is its own section with its
 * own content; choosing one shows it and hides the other, so switching back
 * and forth never loses anything. Preview opens the saved version of a
 * design on the live page, visible only to editors.
 */
const props = defineProps<{
    path: string;
    editable: boolean;
    dirty: boolean;
}>();
const sections = defineModel<PageSection[]>({ required: true });
const emit = defineEmits<{ add: [type: string]; edit: [id: string] }>();

const designs = [
    {
        type: 'hero',
        title: 'Existing banner',
        text: 'Heading and buttons beside a photo or video, with the pathway finder underneath.',
        icon: 'vertical_split',
    },
    {
        type: 'hero_cover',
        title: 'New full-width banner',
        text: 'Heading and buttons over a full-width photo or video, with the finder along the bottom.',
        icon: 'panorama',
    },
] as const;

const sectionOf = (type: string) => sections.value.find((s) => s.type === type);

// The live design: the first visible banner section (the website shows one).
const active = computed(
    () =>
        sections.value.find(
            (s) => (s.type === 'hero' || s.type === 'hero_cover') && s.enabled,
        )?.type ?? null,
);

function choose(type: string): void {
    if (!sectionOf(type)) {
        emit('add', type);
    }

    let shown = false;

    for (const s of sections.value) {
        if (s.type === 'hero' || s.type === 'hero_cover') {
            s.enabled = s.type === type && !shown;
            shown = shown || s.enabled;
        }
    }
}
</script>

<template>
    <PanelCard
        title="Banner design"
        description="Choose which banner the homepage shows. Each design keeps its own content, image or video and settings."
    >
        <div
            class="grid gap-3 sm:grid-cols-2"
            role="radiogroup"
            aria-label="Banner design"
        >
            <div
                v-for="d in designs"
                :key="d.type"
                class="flex flex-col gap-3 rounded-[12px] border-2 p-3.5 transition-colors"
                :class="
                    active === d.type
                        ? 'border-primary bg-accent/40'
                        : 'border-border'
                "
            >
                <button
                    type="button"
                    role="radio"
                    :aria-checked="active === d.type"
                    :disabled="!editable"
                    class="flex items-start gap-3 text-left disabled:cursor-not-allowed"
                    @click="choose(d.type)"
                >
                    <span
                        class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border-2"
                        :class="
                            active === d.type
                                ? 'border-primary'
                                : 'border-muted-foreground/50'
                        "
                        aria-hidden="true"
                    >
                        <span
                            v-if="active === d.type"
                            class="size-2.5 rounded-full bg-primary"
                        />
                    </span>
                    <span class="min-w-0">
                        <span
                            class="flex items-center gap-1.5 text-[14px] font-semibold text-foreground"
                        >
                            <AppIcon :name="d.icon" :size="18" /> {{ d.title }}
                            <span
                                v-if="active === d.type"
                                class="rounded-full bg-primary px-2 py-0.5 text-[11px] font-semibold text-primary-foreground"
                                >Live</span
                            >
                        </span>
                        <span
                            class="mt-1 block text-[12px] text-muted-foreground"
                            >{{ d.text }}</span
                        >
                    </span>
                </button>
                <div class="flex flex-wrap gap-2 pl-8">
                    <Button
                        v-if="sectionOf(d.type)"
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="emit('edit', sectionOf(d.type)!.id)"
                    >
                        <AppIcon name="edit" :size="16" /> Edit content
                    </Button>
                    <Button
                        v-if="sectionOf(d.type)"
                        type="button"
                        size="sm"
                        variant="outline"
                        as-child
                    >
                        <a
                            :href="`${path}?banner=${d.type}`"
                            target="_blank"
                            rel="noopener"
                            ><AppIcon name="visibility" :size="16" /> Preview</a
                        >
                    </Button>
                    <Button
                        v-else-if="editable"
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="emit('add', d.type)"
                    >
                        <AppIcon name="add" :size="16" /> Add this design
                    </Button>
                </div>
            </div>
        </div>
        <p class="mt-3 text-[12px] text-muted-foreground">
            <template v-if="dirty"
                >You have unsaved changes: save first, then Preview shows them.
            </template>
            Saving makes the chosen design live. Preview shows a design as it
            was last saved, and only you can see it.
        </p>
    </PanelCard>
</template>
