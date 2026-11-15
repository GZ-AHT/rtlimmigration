<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/panel/FormField.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { index, show, store, update } from '@/routes/admin/users';
import type { Option } from '@/types';

type EditableUser = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    status: string;
    is_self: boolean;
};

const props = defineProps<{
    user: EditableUser | null;
    roles: Option[];
    statuses: Option[];
    canChangeAccess: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Users', href: index() }] },
});

const editing = computed(() => props.user !== null);

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    status: props.user?.status ?? 'active',
    roles: props.user?.roles ?? ([] as string[]),
    send_invitation: true,
});

function toggleRole(role: string, checked: boolean | 'indeterminate'): void {
    form.roles =
        checked === true
            ? [...form.roles, role]
            : form.roles.filter((r) => r !== role);
}

function submit(): void {
    // Your own roles and status are not editable (lock-out guard), so
    // leave them out rather than sending unchanged values.
    form.transform((data) => {
        const payload: Record<string, unknown> = {
            name: data.name,
            email: data.email,
        };

        if (props.canChangeAccess) {
            payload.status = data.status;
            payload.roles = data.roles;
        }

        if (!editing.value) {
            payload.send_invitation = data.send_invitation;
        }

        return payload;
    });

    form.submit(editing.value ? update(props.user!.id) : store(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="editing ? `Edit ${user?.name}` : 'Add user'" />

    <PageHeader
        eyebrow="Lead & user management"
        :title="editing ? `Edit ${user?.name}` : 'Add user'"
        :description="
            editing
                ? 'Update profile details, roles and access.'
                : 'Create a staff or client account. They will receive an email to set their own password.'
        "
    />

    <form
        class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(300px,1fr)]"
        novalidate
        @submit.prevent="submit"
    >
        <PanelCard title="Profile" description="Name and sign-in email.">
            <div class="grid gap-3.5 sm:grid-cols-2">
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Full name"
                    required
                    :error="form.errors.name"
                >
                    <Input
                        :id="id"
                        v-model="form.name"
                        autocomplete="off"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                        required
                    />
                </FormField>
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Email address"
                    required
                    :error="form.errors.email"
                    :hint="
                        editing
                            ? 'Changing the email requires the user to verify the new address.'
                            : undefined
                    "
                >
                    <Input
                        :id="id"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                        required
                    />
                </FormField>
            </div>

            <label
                v-if="!editing"
                class="mt-5 flex items-start gap-2.5 rounded-lg border bg-muted p-3 text-[13px]"
            >
                <Checkbox
                    :model-value="form.send_invitation"
                    class="mt-0.5"
                    @update:model-value="
                        (v) => (form.send_invitation = v === true)
                    "
                />
                <span>
                    <b class="block">Send an invitation email</b>
                    <span class="text-xs text-muted-foreground"
                        >A secure link (valid 72 hours) lets them choose a
                        password. No password is sent by email.</span
                    >
                </span>
            </label>
        </PanelCard>

        <PanelCard
            title="Access"
            description="Roles decide which dashboard and modules the user can open."
        >
            <p
                v-if="!canChangeAccess"
                class="rounded-lg bg-warning-soft p-3 text-xs text-warning"
            >
                You can't change the roles or status of your own account.
            </p>
            <template v-else>
                <fieldset>
                    <legend
                        class="mb-2 text-xs font-bold text-secondary-foreground"
                    >
                        Roles <span class="text-primary">*</span>
                    </legend>
                    <div class="flex flex-col gap-1.5">
                        <label
                            v-for="role in roles"
                            :key="role.value"
                            class="flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2.5 text-[13px] hover:border-ring"
                            :class="{
                                'border-primary bg-accent': form.roles.includes(
                                    role.value,
                                ),
                            }"
                        >
                            <Checkbox
                                :model-value="form.roles.includes(role.value)"
                                @update:model-value="
                                    (v) => toggleRole(role.value, v)
                                "
                            />
                            {{ role.label }}
                        </label>
                    </div>
                    <p
                        v-if="form.errors.roles"
                        class="mt-1.5 text-xs text-danger"
                        role="alert"
                    >
                        {{ form.errors.roles }}
                    </p>
                    <p
                        v-for="(message, key) in form.errors"
                        v-show="String(key).startsWith('roles.')"
                        :key="key"
                        class="mt-1.5 text-xs text-danger"
                        role="alert"
                    >
                        {{ message }}
                    </p>
                </fieldset>

                <fieldset class="mt-5">
                    <legend
                        class="mb-2 text-xs font-bold text-secondary-foreground"
                    >
                        Status
                    </legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label
                            v-for="s in statuses"
                            :key="s.value"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-[13px]"
                            :class="{
                                'border-primary bg-accent':
                                    form.status === s.value,
                            }"
                        >
                            <input
                                v-model="form.status"
                                type="radio"
                                name="status"
                                :value="s.value"
                                class="accent-primary"
                            />
                            {{ s.label }}
                        </label>
                    </div>
                    <p
                        v-if="form.errors.status"
                        class="mt-1.5 text-xs text-danger"
                        role="alert"
                    >
                        {{ form.errors.status }}
                    </p>
                    <p class="mt-1.5 text-[11px] text-muted-foreground">
                        Inactive users can't sign in and are signed out
                        immediately.
                    </p>
                </fieldset>
            </template>
        </PanelCard>

        <div class="flex justify-end gap-2 lg:col-span-2">
            <Button variant="outline" as-child>
                <Link :href="editing ? show(user!.id) : index()">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ editing ? 'Save changes' : 'Create user' }}
            </Button>
        </div>
    </form>
</template>
