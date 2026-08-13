<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserDirectPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageUserAccess->value) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(ProgrammePermission::class)],
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $target = $this->targetUser();
            if ($target !== null && $this->user()?->is($target)) {
                $validator->errors()->add('permissions', 'You cannot change your own direct permissions.');
            }
        }];
    }

    public function targetUser(): ?User
    {
        $identifier = $this->route('programmeUser');

        return is_string($identifier) ? User::query()->find($identifier) : null;
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        $permissions = $this->input('permissions', []);

        return is_array($permissions) ? array_values(array_filter($permissions, is_string(...))) : [];
    }
}
