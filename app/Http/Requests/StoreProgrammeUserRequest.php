<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\County;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProgrammeUserRequest extends FormRequest
{
    /** @return array{name: string, email: string, role: string, county_id?: string|null, assigned_county_ids?: list<string>} */
    public function accessData(): array
    {
        $countyId = $this->input('county_id');
        $assignedCountyIds = $this->input('assigned_county_ids', []);

        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
            'role' => $this->string('role')->toString(),
            'county_id' => is_string($countyId) ? $countyId : null,
            'assigned_county_ids' => is_array($assignedCountyIds)
                ? array_values(array_filter($assignedCountyIds, is_string(...)))
                : [],
        ];
    }

    public function authorize(): bool
    {
        return ($this->user()?->can(ProgrammePermission::ManageCountyUsers->value) ?? false)
            || ($this->user()?->can(ProgrammePermission::ManageUserAccess->value) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->withoutTrashed()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'county_id' => ['nullable', 'uuid', Rule::exists(County::class, 'id')->withoutTrashed()],
            'assigned_county_ids' => ['array'],
            'assigned_county_ids.*' => ['uuid', 'distinct', Rule::exists(County::class, 'id')->withoutTrashed()],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $actor = $this->user();
            $role = UserRole::tryFrom((string) $this->input('role'));

            if (! $actor || ! $role) {
                return;
            }

            if (! $actor->can(ProgrammePermission::ManageUserAccess->value)) {
                $allowedRoles = $actor->programmeRole() === UserRole::CountyAdmin
                    ? [UserRole::CountyOfficial]
                    : [UserRole::CountyOfficial, UserRole::CountyAdmin];

                if (! in_array($role, $allowedRoles)) {
                    $validator->errors()->add('role', 'You cannot grant this programme role.');
                }
            }

            if (in_array($role, [UserRole::CountyOfficial, UserRole::CountyAdmin]) && ! $this->filled('county_id')) {
                $validator->errors()->add('county_id', 'A home county is required for county roles.');
            }

            if ($actor->programmeRole() === UserRole::CountyAdmin && $this->input('county_id') !== $actor->county_id) {
                $validator->errors()->add('county_id', 'County administrators can grant access only in their own county.');
            }

            if ($role->hasAssignedCountyScope() && count($this->input('assigned_county_ids', [])) === 0) {
                $validator->errors()->add('assigned_county_ids', 'Select at least one county for this portfolio role.');
            }
        }];
    }
}
