<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');
        $builtIn = $role !== null && RoleEnum::tryFrom($role->name) !== null;

        return [
            // Built-in role names are fixed; custom roles can be renamed.
            'name' => $builtIn ? ['prohibited'] : ['required', 'string', 'max:64'],
            'slug' => $builtIn ? ['nullable'] : [
                'required', 'string', 'max:64',
                Rule::unique(Role::class, 'name')->ignore($role?->getKey()),
                Rule::notIn(array_column(RoleEnum::cases(), 'value')),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => [
                'string',
                'distinct',
                Rule::exists(Permission::class, 'name'),
                // No privilege escalation: you can only grant what you hold.
                function (string $attribute, mixed $value, Closure $fail): void {
                    /** @var User $actor */
                    $actor = $this->user();

                    if (! $actor->can((string) $value)) {
                        $fail(__('You cannot grant the ":permission" permission because you do not have it.', ['permission' => $value]));
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => __('A role with this name already exists.'),
            'slug.not_in' => __('This name is reserved for a built-in role.'),
            'name.prohibited' => __('Built-in roles cannot be renamed.'),
        ];
    }
}
