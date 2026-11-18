<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import SortableList from '@/components/cms/SortableList.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import PanelTabs from '@/components/panel/PanelTabs.vue';
import SelectInput from '@/components/panel/SelectInput.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { index, reorder } from '@/routes/admin/cms/menus';
import { destroy, store, update } from '@/routes/admin/cms/menus/items';
import type { Option } from '@/types';
import { clone } from '@/lib/clone';

type Item = {
    id: number;
    label: string;
    link_type: 'page' | 'url';
    page_id: string;
    url: string;
    href: string | null;
    parent_id: number | null;
    new_tab: boolean;
    is_active: boolean;
    visibility: 'everyone' | 'guests' | 'auth' | 'roles';
    roles: string[];
    children: Item[];
};

const props = defineProps<{
    locations: Option[];
    location: string;
    menu: { id: number; name: string };
    items: Item[];
    pages: Option[];
    roles: Option[];
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Menus', href: index() }] } });

const tab = ref(props.location);
watch(tab, (v) =>
    router.get(
        index.url({ query: { location: v } }),
        {},
        { preserveScroll: true },
    ),
);

const tree = ref<Item[]>(clone(props.items));
watch(
    () => props.items,
    (v) => (tree.value = clone(v)),
);

const visibilityLabels: Record<string, string> = {
    everyone: 'Everyone',
    guests: 'Visitors not signed in',
    auth: 'Signed-in users',
    roles: 'Chosen roles',
};

function saveOrder(next: Item[]): void {
    tree.value = next;
    router.put(
        reorder(props.menu.id).url,
        {
            tree: next.map((i) => ({
                id: i.id,
                children: i.children.map((c) => ({ id: c.id })),
            })),
        },
        {
            preserveScroll: true,
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Could not save the order.'),
        },
    );
}

function reorderChildren(parent: Item, children: Item[]): void {
    saveOrder(
        tree.value.map((i) => (i.id === parent.id ? { ...i, children } : i)),
    );
}

// --- add / edit dialog
const editing = ref<Item | null>(null);
const dialogOpen = ref(false);
const form = useForm({
    label: '',
    link_type: 'page' as 'page' | 'url',
    page_id: '',
    url: '',
    parent_id: '',
    new_tab: false,
    is_active: true,
    visibility: 'everyone' as Item['visibility'],
    roles: [] as string[],
});

const parentOptions = computed<Option[]>(() =>
    tree.value
        .filter((i) => i.id !== editing.value?.id)
        .map((i) => ({ value: String(i.id), label: i.label })),
);

function openNew(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(item: Item): void {
    editing.value = item;
    form.label = item.label;
    form.link_type = item.link_type;
    form.page_id = item.page_id;
    form.url = item.url;
    form.parent_id = item.parent_id ? String(item.parent_id) : '';
    form.new_tab = item.new_tab;
    form.is_active = item.is_active;
    form.visibility = item.visibility;
    form.roles = [...item.roles];
    form.clearErrors();
    dialogOpen.value = true;
}

function save(): void {
    const payload = form.transform((d) => ({
        ...d,
        page_id: d.page_id || null,
        url: d.url || null,
        parent_id: d.parent_id || null,
    }));
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };

    if (editing.value) {
        payload.put(update(editing.value.id).url, options);
    } else {
        payload.post(store(props.menu.id).url, options);
    }
}

const deleting = ref<Item | null>(null);
function remove(): void {
    if (!deleting.value) return;
    router.delete(destroy(deleting.value.id).url, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

const urlHint =
    'A site path like /blog or /universities, or a full https:// address.';
const err = (k: string) =>
    (form.errors as Record<string, string | undefined>)[k];
</script>

<template>
    <Head title="Menus" />

    <PageHeader
        eyebrow="Website CMS"
        title="Menus"
        description="Website navigation. Drag links to reorder; a link can hold one level of dropdown links."
    >
        <template #actions>
            <Button @click="openNew"
                ><AppIcon name="add" :size="18" /> Add link</Button
            >
        </template>
    </PageHeader>

    <PanelTabs
        v-model="tab"
        label="Menu location"
        :tabs="locations.map((l) => ({ key: l.value, label: l.label }))"
    />

    <PanelCard :title="menu.name">
        <p
            v-if="tree.length === 0"
            class="py-6 text-center text-[13px] text-muted-foreground"
        >
            This menu is empty. Add a link.
        </p>
        <SortableList
            :items="tree"
            :label="`${menu.name} links`"
            @reorder="saveOrder"
        >
            <template #default="{ item }">
                <div class="pr-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="text-[13px] font-semibold"
                            :class="
                                item.is_active
                                    ? ''
                                    : 'text-muted-foreground line-through'
                            "
                            >{{ item.label }}</span
                        >
                        <span
                            class="truncate text-[11px] text-muted-foreground"
                            >{{ item.href ?? 'page missing' }}</span
                        >
                        <AppIcon
                            v-if="item.new_tab"
                            name="open_in_new"
                            :size="14"
                            class="text-muted-foreground"
                        />
                        <StatusBadge
                            v-if="item.visibility !== 'everyone'"
                            :label="
                                item.visibility === 'roles'
                                    ? item.roles.join(', ') || 'No roles'
                                    : visibilityLabels[item.visibility]
                            "
                            tone="info"
                        />
                        <span class="ml-auto flex gap-1">
                            <button
                                type="button"
                                class="tiny-action"
                                @click="openEdit(item)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="tiny-action"
                                @click="deleting = item"
                            >
                                Delete
                            </button>
                        </span>
                    </div>
                    <div
                        v-if="item.children.length"
                        class="mt-2 ml-2 border-l-2 border-border pl-3"
                    >
                        <SortableList
                            :items="item.children"
                            :label="`${item.label} dropdown`"
                            @reorder="(c) => reorderChildren(item, c)"
                        >
                            <template #default="{ item: child }">
                                <div
                                    class="flex flex-wrap items-center gap-2 pr-2"
                                >
                                    <span
                                        class="text-[13px]"
                                        :class="
                                            child.is_active
                                                ? ''
                                                : 'text-muted-foreground line-through'
                                        "
                                        >{{ child.label }}</span
                                    >
                                    <span
                                        class="truncate text-[11px] text-muted-foreground"
                                        >{{
                                            child.href ?? 'page missing'
                                        }}</span
                                    >
                                    <span class="ml-auto flex gap-1">
                                        <button
                                            type="button"
                                            class="tiny-action"
                                            @click="openEdit(child)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="tiny-action"
                                            @click="deleting = child"
                                        >
                                            Delete
                                        </button>
                                    </span>
                                </div>
                            </template>
                        </SortableList>
                    </div>
                </div>
            </template>
        </SortableList>
    </PanelCard>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="rounded-[14px] sm:max-w-[520px]">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-extrabold">{{
                    editing ? 'Edit link' : 'Add link'
                }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-3.5" @submit.prevent="save">
                <FormField
                    v-slot="{ id, invalid }"
                    label="Text"
                    required
                    :error="err('label')"
                >
                    <Input
                        :id="id"
                        v-model="form.label"
                        maxlength="80"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField v-slot="{ id }" label="Links to">
                    <SelectInput
                        :id="id"
                        v-model="form.link_type"
                        :options="[
                            { value: 'page', label: 'A page on this website' },
                            {
                                value: 'url',
                                label: 'Custom or external address',
                            },
                        ]"
                    />
                </FormField>
                <FormField
                    v-if="form.link_type === 'page'"
                    v-slot="{ id, invalid }"
                    label="Page"
                    :error="err('page_id')"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.page_id"
                        :options="pages"
                        placeholder="Choose a page"
                        :invalid="invalid"
                    />
                </FormField>
                <FormField
                    v-else
                    v-slot="{ id, invalid, describedBy }"
                    label="Address"
                    :error="err('url')"
                    :hint="urlHint"
                >
                    <Input
                        :id="id"
                        v-model="form.url"
                        maxlength="255"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <FormField
                    v-if="!editing?.children.length"
                    v-slot="{ id }"
                    label="Show inside"
                    :error="err('parent_id')"
                >
                    <SelectInput
                        :id="id"
                        v-model="form.parent_id"
                        :options="parentOptions"
                        placeholder="Top level"
                    />
                </FormField>
                <FormField v-slot="{ id }" label="Who sees it">
                    <SelectInput
                        :id="id"
                        v-model="form.visibility"
                        :options="
                            Object.entries(visibilityLabels).map(
                                ([value, label]) => ({ value, label }),
                            )
                        "
                    />
                </FormField>
                <fieldset
                    v-if="form.visibility === 'roles'"
                    class="flex flex-wrap gap-3"
                >
                    <legend class="mb-1 text-[13px] font-semibold">
                        Roles
                    </legend>
                    <label
                        v-for="r in roles"
                        :key="r.value"
                        class="flex items-center gap-1.5 text-[13px]"
                    >
                        <Checkbox
                            :model-value="form.roles.includes(r.value)"
                            @update:model-value="
                                (v) =>
                                    (form.roles =
                                        v === true
                                            ? [...form.roles, r.value]
                                            : form.roles.filter(
                                                  (x) => x !== r.value,
                                              ))
                            "
                        />
                        {{ r.label }}
                    </label>
                </fieldset>
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-[13px]"
                        ><Checkbox
                            :model-value="form.new_tab"
                            @update:model-value="
                                (v) => (form.new_tab = v === true)
                            "
                        />
                        Open in a new tab</label
                    >
                    <label class="flex items-center gap-2 text-[13px]"
                        ><Checkbox
                            :model-value="form.is_active"
                            @update:model-value="
                                (v) => (form.is_active = v === true)
                            "
                        />
                        Shown</label
                    >
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogOpen = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Save link</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="deleting !== null"
        :title="`Delete “${deleting?.label}”?`"
        :description="
            deleting?.children.length
                ? 'Its dropdown links are deleted too.'
                : undefined
        "
        confirm-label="Delete"
        destructive
        @update:open="(v) => !v && (deleting = null)"
        @confirm="remove"
    />
</template>
