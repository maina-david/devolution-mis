<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRolePermissionsRequest extends FormRequest
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
            if ($this->route('role') !== 'platform-admin') {
                return;
            }

            $permissions = $this->input('permissions', []);
            foreach ([ProgrammePermission::ManageUserAccess->value, ProgrammePermission::ConfigurePlatform->value] as $required) {
                if (! is_array($permissions) || ! in_array($required, $permissions, true)) {
                    $validator->errors()->add('permissions', 'The platform administrator role must retain platform configuration and user-access management.');

                    break;
                }
            }
        }];
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        $permissions = $this->input('permissions', []);

        return is_array($permissions) ? array_values(array_filter($permissions, is_string(...))) : [];
    }
}
