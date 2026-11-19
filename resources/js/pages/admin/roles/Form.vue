<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { index, store, update } from '@/routes/admin/roles';

type PermissionItem = { name: string; description: string; grantable: boolean };
type Group = { module: string; permissions: PermissionItem[] };
type EditableRole = {
    id: number;
    name: string;
    built_in: boolean;
    permissions: string[];
    users_count: number;
};

const props = defineProps<{ role: EditableRole | null; groups: Group[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Roles & permissions', href: index() }] },
});

const editing = computed(() => props.role !== null);

const form = useForm({
    name: props.role?.name ?? '',
    permissions: props.role?.permissions ?? ([] as string[]),
});

function has(name: string): boolean {
    return form.permissions.includes(name);
}

function toggle(name: string, on: boolean): void {
    form.permissions = on
        ? [...new Set([...form.permissions, name])]
        : form.permissions.filter((p) => p !== name);
}

function groupState(group: Group): boolean | 'indeterminate' {
    const grantable = group.permissions.filter((p) => p.grantable);
    const count = grantable.filter((p) => has(p.name)).length;

    if (count === 0) {
        return false;
    }

    return count === grantable.length ? true : 'indeterminate';
}

function toggleGroup(group: Group, value: boolean | 'indeterminate'): void {
    group.permissions
        .filter((p) => p.grantable)
        .forEach((p) => toggle(p.name, value === true));
}

const selected = computed(() => form.permissions.length);

function submit(): void {
    form.transform((data) =>
        props.role?.built_in ? { permissions: data.permissions } : data,
    );
    form.submit(
        editing.value ? update(String(props.role!.id) as never) : store(),
        {
            preserveScroll: true,
        },
    );
}

// The server validates a slug derived from the name; show its errors here.
const slugError = computed(
    () => (form.errors as Record<string, string | undefined>).slug,
);

const permissionErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith('permissions'))
        .map(([, message]) => message),
);
</script>

<template>
    <Head :title="editing ? `Edit ${role?.name}` : 'New role'" />

    <PageHeader
        eyebrow="Roles & permissions"
        :title="editing ? `Edit ${role?.name}` : 'New role'"
        :description="
            editing && role?.users_count
                ? `Changes apply immediately to ${role.users_count} user(s) with this role.`
                : 'Choose what users with this role can see and do.'
        "
    />

    <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
        <PanelCard>
            <div class="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_auto]">
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Role name"
                    :required="!role?.built_in"
                    :error="form.errors.name || slugError"
                    :hint="
                        role?.built_in
                            ? 'Built-in roles cannot be renamed.'
                            : 'For example: Content Editor'
                    "
                >
                    <Input
                        :id="id"
                        v-model="form.name"
                        :disabled="role?.built_in"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <p class="text-[13px] text-muted-foreground sm:pb-6">
                    <b class="text-foreground">{{ selected }}</b> permissions
                    selected
                </p>
            </div>
        </PanelCard>

        <div
            v-if="permissionErrors.length"
            class="rounded-lg bg-danger-soft p-3 text-xs text-danger"
            role="alert"
        >
            <p v-for="message in permissionErrors" :key="message">
                {{ message }}
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
            <PanelCard v-for="group in groups" :key="group.module">
                <label
                    class="mb-3 flex cursor-pointer items-center gap-2.5 border-b pb-3"
                >
                    <Checkbox
                        :model-value="groupState(group)"
                        :disabled="!group.permissions.some((p) => p.grantable)"
                        @update:model-value="(v) => toggleGroup(group, v)"
                    />
                    <span class="font-display text-[15px] font-bold">{{
                        group.module
                    }}</span>
                </label>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="permission in group.permissions"
                        :key="permission.name"
                    >
                        <label
                            class="flex items-start gap-2.5 text-[13px]"
                            :class="
                                permission.grantable
                                    ? 'cursor-pointer'
                                    : 'cursor-not-allowed opacity-55'
                            "
                            :title="
                                permission.grantable
                                    ? permission.name
                                    : 'You can only grant permissions you have yourself.'
                            "
                        >
                            <Checkbox
                                class="mt-0.5"
                                :model-value="has(permission.name)"
                                :disabled="!permission.grantable"
                                @update:model-value="
                                    (v) => toggle(permission.name, v === true)
                                "
                            />
                            <span>
                                {{ permission.description }}
                                <AppIcon
                                    v-if="!permission.grantable"
                                    name="lock"
                                    :size="13"
                                    class="ml-0.5 align-[-2px]"
                                />
                            </span>
                        </label>
                    </li>
                </ul>
            </PanelCard>
        </div>

        <div
            class="sticky bottom-0 -mx-3.75 flex justify-end gap-2 border-t bg-background/95 px-3.75 py-3 backdrop-blur md:-mx-8 md:px-8"
        >
            <Button variant="outline" as-child>
                <Link :href="index()">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ editing ? 'Save role' : 'Create role' }}
            </Button>
        </div>
    </form>
</template>
