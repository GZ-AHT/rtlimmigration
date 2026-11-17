<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ImageField from '@/components/cms/ImageField.vue';
import EmptyState from '@/components/panel/EmptyState.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import TextArea from '@/components/panel/TextArea.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { getInitials } from '@/composables/useInitials';
import { index, update } from '@/routes/admin/blog/authors';
import type { MediaItem } from '@/types';

type Author = {
    id: number;
    name: string;
    email: string;
    posts: number;
    display_name: string;
    job_title: string;
    bio: string;
    avatar: MediaItem | null;
};

const props = defineProps<{ authors: Author[]; canManage: boolean }>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Blog authors', href: index() }] },
});

const page = usePage();
const me = computed(
    () => (page.props.auth as { user: { id: number } | null }).user?.id,
);
const writers = computed(() =>
    props.authors.filter(
        (a) => a.posts > 0 || a.display_name || a.id === me.value,
    ),
);
const others = computed(() =>
    props.authors.filter((a) => !writers.value.includes(a)),
);

const editing = ref<Author | null>(null);
const form = useForm({
    display_name: '',
    job_title: '',
    bio: '',
    avatar: null as MediaItem | null,
});

function open(a: Author): void {
    editing.value = a;
    form.display_name = a.display_name;
    form.job_title = a.job_title;
    form.bio = a.bio;
    form.avatar = a.avatar;
    form.clearErrors();
}

function save(): void {
    if (!editing.value) return;
    form.transform((d) => ({
        ...d,
        avatar: undefined,
        avatar_id: d.avatar?.id ?? null,
    })).put(update(editing.value.id).url, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}
</script>

<template>
    <Head title="Blog authors" />

    <PageHeader
        eyebrow="Blog management"
        title="Authors"
        description="The name, title, photo and bio shown with each author's posts."
    />

    <PanelCard>
        <EmptyState
            v-if="writers.length === 0"
            icon="person"
            title="No authors yet"
        />
        <ul class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <li
                v-for="a in writers"
                :key="a.id"
                class="flex gap-3 rounded-lg border border-border p-3"
            >
                <img
                    v-if="a.avatar"
                    :src="a.avatar.url"
                    alt=""
                    class="size-12 shrink-0 rounded-full object-cover"
                />
                <span
                    v-else
                    class="grid size-12 shrink-0 place-items-center rounded-full bg-primary/10 font-bold text-primary"
                    >{{ getInitials(a.name) }}</span
                >
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">
                        {{ a.display_name || a.name }}
                    </p>
                    <p class="truncate text-[12px] text-muted-foreground">
                        {{ a.job_title || a.email }}
                    </p>
                    <p class="mt-1 text-[12px] text-muted-foreground">
                        {{ a.posts }} post{{ a.posts === 1 ? '' : 's' }}
                    </p>
                </div>
                <button
                    v-if="canManage || a.id === me"
                    type="button"
                    class="tiny-action self-start"
                    @click="open(a)"
                >
                    Edit
                </button>
            </li>
        </ul>
        <details v-if="canManage && others.length" class="mt-4">
            <summary class="cursor-pointer text-[13px] font-semibold">
                Other staff ({{ others.length }})
            </summary>
            <ul class="mt-2 flex flex-wrap gap-2">
                <li v-for="a in others" :key="a.id">
                    <button type="button" class="tiny-action" @click="open(a)">
                        {{ a.name }}
                    </button>
                </li>
            </ul>
        </details>
    </PanelCard>

    <Dialog
        :open="editing !== null"
        @update:open="(v) => !v && (editing = null)"
    >
        <DialogContent class="rounded-[14px] sm:max-w-[520px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">{{
                    editing?.name
                }}</DialogTitle>
                <DialogDescription class="text-xs"
                    >Shown publicly with their blog posts.</DialogDescription
                >
            </DialogHeader>
            <form class="flex flex-col gap-3.5" @submit.prevent="save">
                <ImageField
                    v-model="form.avatar"
                    label="Photo"
                    hint="Square works best."
                />
                <FormField
                    v-slot="{ id }"
                    label="Display name"
                    :error="form.errors.display_name"
                    :hint="`Blank: ${editing?.name}`"
                >
                    <Input
                        :id="id"
                        v-model="form.display_name"
                        maxlength="120"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Job title"
                    :error="form.errors.job_title"
                >
                    <Input
                        :id="id"
                        v-model="form.job_title"
                        maxlength="120"
                        placeholder="e.g. Senior visa adviser"
                    />
                </FormField>
                <FormField v-slot="{ id }" label="Bio" :error="form.errors.bio">
                    <TextArea
                        :id="id"
                        v-model="form.bio"
                        :rows="4"
                        :maxlength="1000"
                    />
                </FormField>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="editing = null"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
