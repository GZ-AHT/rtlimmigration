<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Policies\RolePolicy;
use App\Support\Presenters\UserPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** REQ §6 Role Management, Permission Management, RBAC. */
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()->withCount(['users', 'permissions'])->orderBy('id')->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => UserPresenter::roleLabel($role->name),
                'built_in' => RoleEnum::tryFrom($role->name) !== null,
                'full_access' => $role->name === RoleEnum::SuperAdmin->value,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'can' => [
                    'update' => RolePolicy::isEditable($role) && $request->user()->can('update', $role),
                    'delete' => RolePolicy::isDeletable($role) && $request->user()->can('delete', $role) && $role->users_count === 0,
                ],
            ]);

        return Inertia::render('admin/roles/Index', [
            'roles' => $roles,
            'totalPermissions' => array_sum(array_map('count', (array) config('rbac.permissions'))),
            'can' => ['create' => $request->user()->can('create', Role::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('admin/roles/Form', [
            'role' => null,
            'groups' => $this->permissionGroups($request),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        Gate::authorize('create', Role::class);

        $role = DB::transaction(function () use ($request) {
            $role = new Role(['name' => $request->validated('slug'), 'guard_name' => 'web']);
            $role->save();
            $role->syncPermissions($request->validated('permissions'));
            $this->log($role, $request, 'role_created', [], $request->validated('permissions'));

            return $role;
        });

        $this->toast(['type' => 'success', 'message' => __('Role ":role" created.', ['role' => UserPresenter::roleLabel($role->name)])]);

        return to_route('admin.roles.index');
    }

    public function edit(Request $request, Role $role): Response
    {
        Gate::authorize('update', $role);
        abort_unless(RolePolicy::isEditable($role), 403);

        return Inertia::render('admin/roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => UserPresenter::roleLabel($role->name),
                'built_in' => RoleEnum::tryFrom($role->name) !== null,
                'permissions' => $role->permissions()->pluck('name')->values(),
                'users_count' => $role->users()->count(),
            ],
            'groups' => $this->permissionGroups($request),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);
        abort_unless(RolePolicy::isEditable($role), 403);

        DB::transaction(function () use ($request, $role) {
            $before = $this->permissionNames($role);

            // Keep grants the editor cannot see or change (permissions they
            // do not hold) so saving the form never silently strips them.
            $untouchable = array_values(array_filter($before, fn (string $p) => ! $request->user()->can($p)));
            $after = array_values(array_unique([...$request->validated('permissions'), ...$untouchable]));

            if ($request->filled('slug')) {
                $role->name = $request->validated('slug');
                $role->save();
            }

            $role->syncPermissions($after);
            $this->log($role, $request, 'role_updated', $before, $after);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->toast(['type' => 'success', 'message' => __('Role ":role" updated.', ['role' => UserPresenter::roleLabel($role->name)])]);

        return to_route('admin.roles.index');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);
        abort_unless(RolePolicy::isDeletable($role), 403);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => __('Remove this role from all users before deleting it.')]);
        }

        DB::transaction(function () use ($request, $role) {
            $this->log($role, $request, 'role_deleted', $this->permissionNames($role), []);
            $role->delete();
        });

        $this->toast(['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('admin.roles.index');
    }

    /** @return list<string> */
    private function permissionNames(Role $role): array
    {
        return array_values(array_map('strval', $role->permissions()->pluck('name')->all()));
    }

    /**
     * Permission matrix grouped by module (config/rbac.php). Permissions the
     * editor does not hold are shown locked.
     *
     * @return list<array{module: string, permissions: list<array{name: string, description: string, grantable: bool}>}>
     */
    private function permissionGroups(Request $request): array
    {
        $groups = [];

        foreach (config('rbac.permissions') as $module => $permissions) {
            $items = [];

            foreach ($permissions as $name => $description) {
                $items[] = [
                    'name' => (string) $name,
                    'description' => (string) $description,
                    'grantable' => $request->user()->can((string) $name),
                ];
            }

            $groups[] = ['module' => (string) $module, 'permissions' => $items];
        }

        return $groups;
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    private function log(Role $role, Request $request, string $event, array $before, array $after): void
    {
        activity('roles')
            ->performedOn($role)
            ->causedBy($request->user())
            ->event($event)
            ->withProperties([
                'role' => $role->name,
                'added' => array_values(array_diff($after, $before)),
                'removed' => array_values(array_diff($before, $after)),
            ])
            ->log($event);
    }
}
