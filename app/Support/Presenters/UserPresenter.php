<?php

namespace App\Support\Presenters;

use App\Enums\Role as RoleEnum;
use App\Enums\UserStatus;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Shapes users for the admin screens. Sends only what the UI needs:
 * never password hashes, 2FA secrets or tokens.
 */
class UserPresenter
{
    /** @return array<string, mixed> */
    public static function row(User $user, User $actor): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->contact_email,
            'roles' => $user->roles->pluck('name')->map(fn (string $r) => self::roleLabel($r))->values(),
            'status' => $user->trashed() ? 'deleted' : $user->status->value,
            'email_verified' => $user->email_verified_at !== null,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'can' => [
                'update' => ! $user->trashed() && $actor->can('update', $user),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(User $user, User $actor): array
    {
        $canUpdate = $actor->can('update', $user);
        $canChangeAccess = $canUpdate && UserPolicy::canChangeAccess($actor, $user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->contact_email,
            'roles' => $user->roles->pluck('name')->values(),
            'role_labels' => $user->roles->pluck('name')->map(fn (string $r) => self::roleLabel($r))->values(),
            'status' => $user->status->value,
            'deleted' => $user->trashed(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
            'created_at' => $user->created_at?->toIso8601String(),
            'is_self' => $actor->is($user),
            'can' => [
                'update' => ! $user->trashed() && $canUpdate,
                'change_access' => ! $user->trashed() && $canChangeAccess,
                'delete' => ! $user->trashed() && $canChangeAccess && $actor->can('delete', $user),
                'restore' => $user->trashed() && $actor->can('restore', $user),
            ],
        ];
    }

    /**
     * Roles the actor may assign. Super Admin is offered to Super Admins only.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function roleOptions(User $actor): array
    {
        return array_values(Role::query()->orderBy('id')->pluck('name')
            ->reject(fn (string $name) => $name === RoleEnum::SuperAdmin->value && ! $actor->isSuperAdmin())
            ->map(fn (string $name) => ['value' => $name, 'label' => self::roleLabel($name)])
            ->all());
    }

    /** @return list<array{value: string, label: string}> */
    public static function statusOptions(): array
    {
        return array_map(fn (UserStatus $s) => ['value' => $s->value, 'label' => $s->label()], UserStatus::cases());
    }

    public static function roleLabel(string $name): string
    {
        return RoleEnum::tryFrom($name)?->label() ?? Str::headline($name);
    }
}
