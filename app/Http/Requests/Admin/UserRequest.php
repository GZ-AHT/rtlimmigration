<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Validates the admin user form (create and edit). Authorization is done by
 * UserPolicy in the controller; this class adds the rule that only a
 * Super Admin may grant the Super Admin role.
 */
class UserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $target */
        $target = $this->route('user');
        $editingSelf = $target !== null && $this->user()?->is($target);

        $rules = [
            ...$this->profileRules($target?->getKey()),
            'send_invitation' => ['sometimes', 'boolean'],
        ];

        // Users cannot change their own roles or status (lock-out guard).
        if (! $editingSelf) {
            $rules['status'] = ['required', Rule::enum(UserStatus::class)];
            $rules['roles'] = ['required', 'array', 'min:1'];
            $rules['roles.*'] = [
                'string',
                'distinct',
                Rule::exists(RoleModel::class, 'name'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === Role::SuperAdmin->value && ! $this->user()?->isSuperAdmin()) {
                        $fail(__('Only a Super Admin can grant the Super Admin role.'));
                    }
                },
            ];
        }

        return $rules;
    }

    /**
     * @return array{name: string, email: string, status?: string, roles?: list<string>, send_invitation?: bool}
     */
    public function payload(): array
    {
        /** @var array{name: string, email: string, status?: string, roles?: list<string>, send_invitation?: bool} */
        return $this->safe()->only(['name', 'email', 'status', 'roles', 'send_invitation']);
    }
}
