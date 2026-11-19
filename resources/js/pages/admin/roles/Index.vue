<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppIcon from '@/components/panel/AppIcon.vue';
import ConfirmDialog from '@/components/panel/ConfirmDialog.vue';
import DataTable from '@/components/panel/DataTable.vue';
import PageHeader from '@/components/panel/PageHeader.vue';
import PanelCard from '@/components/panel/PanelCard.vue';
import StatusBadge from '@/components/panel/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import { create, destroy, edit, index } from '@/routes/admin/roles';
import { index as usersIndex } from '@/routes/admin/users';
import type { TableColumn } from '@/types';

type RoleRow = {
    id: number;
    name: string;
    label: string;
    built_in: boolean;
    full_access: boolean;
    users_count: number;
    permissions_count: number;
    can: { update: boolean; delete: boolean };
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Roles & permissions', href: index() }] },
});

defineProps<{
    roles: RoleRow[];
    totalPermissions: number;
    can: { create: boolean };
}>();

const columns: TableColumn<RoleRow>[] = [
    { key: 'label', label: 'Role' },
    { key: 'permissions_count', label: 'Permissions' },
    { key: 'users_count', label: 'Users', align: 'right' },
];

const deleting = ref<RoleRow | null>(null);
const busy = ref(false);

function confirmDelete(): void {
    if (!deleting.value) {
        return;
    }

    router.delete(destroy(String(deleting.value.id)).url, {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onError: (errors) =>
            toast.error(Object.values(errors)[0] ?? 'Could not delete role.'),
        onFinish: () => {
            busy.value = false;
            deleting.value = null;
        },
    });
}
</script>

<template>
    <Head title="Roles & permissions" />

    <PageHeader
        eyebrow="System"
        title="Roles & permissions"
        description="What each role can see and do. Super Admin always has full access."
    >
        <template v-if="can.create" #actions>
            <Button as-child>
                <Link :href="create()"
                    ><AppIcon name="add" :size="18" /> New role</Link
                >
            </Button>
        </template>
    </PageHeader>

    <PanelCard flush>
        <DataTable :columns="columns" :rows="roles" caption="Roles">
            <template #cell-label="{ row }">
                <span class="flex items-center gap-2.5">
                    <span
                        class="size-30px grid shrink-0 place-items-center rounded-lg bg-accent text-accent-foreground"
                    >
                        <AppIcon
                            :name="row.full_access ? 'shield_person' : 'badge'"
                            :size="17"
                        />
                    </span>
                    <span class="font-semibold text-foreground"
                        >{{ row.label }}
                    </span>
                    <StatusBadge
                        v-if="row.built_in"
                        label="Built-in"
                        tone="info"
                    />
                </span>
            </template>
            <template #cell-permissions_count="{ row }">
                <span v-if="row.full_access" class="font-semibold text-success"
                    >Full access</span
                >
                <span v-else
                    >{{ row.permissions_count }} of {{ totalPermissions }}</span
                >
            </template>
            <template #cell-users_count="{ row }">
                <Link
                    :href="usersIndex({ query: { role: row.name } })"
                    class="hover:text-primary hover:underline"
                >
                    {{ formatNumber(row.users_count) }}
                </Link>
            </template>
            <template #actions="{ row }">
                <Link
                    v-if="row.can.update"
                    :href="edit(String(row.id))"
                    class="tiny-action"
                >
                    <AppIcon name="edit" :size="13" />
                    <span>Edit</span>
                </Link>
                <button
                    v-if="row.can.delete"
                    type="button"
                    class="tiny-action text-danger hover:border-danger/30"
                    @click="deleting = row"
                >
                    <AppIcon name="delete" :size="13" />
                    <span>Delete</span>
                </button>
            </template>
        </DataTable>
    </PanelCard>

    <ConfirmDialog
        :open="deleting !== null"
        :title="`Delete the ${deleting?.label} role?`"
        description="This can't be undone. Only roles with no users can be deleted."
        confirm-label="Delete role"
        destructive
        :loading="busy"
        @update:open="(v) => !v && (deleting = null)"
        @confirm="confirmDelete"
    />
</template>
