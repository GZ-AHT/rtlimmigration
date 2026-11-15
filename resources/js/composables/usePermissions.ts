import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Permission checks for showing or hiding UI. This is presentation only:
 * the server authorizes every request (REQ §31.5).
 */
export function usePermissions() {
    const page = usePage();
    const auth = computed(() => page.props.auth);

    function can(permission: string): boolean {
        return (
            auth.value.isSuperAdmin ||
            auth.value.permissions.includes(permission)
        );
    }

    function canAny(permissions: string[]): boolean {
        return permissions.some(can);
    }

    function hasRole(role: string): boolean {
        return auth.value.roles.includes(role);
    }

    return { can, canAny, hasRole };
}
