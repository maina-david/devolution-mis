<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDswgWorkingGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDswg->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'unique:dswg_working_groups,code'],
            'name' => ['required', 'string', 'max:255'],
            'mandate' => ['required', 'string', 'max:10000'],
            'scope' => ['required', 'in:national,regional,county,sector'],
            'lead_organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'secretariat_user_id' => ['required', 'uuid', 'exists:users,id'],
            'meeting_frequency' => ['nullable', 'string', 'max:100'],
            'county_ids' => ['required', 'array', 'min:1'],
            'county_ids.*' => ['uuid', 'distinct', 'exists:counties,id'],
            'sector_ids' => ['required', 'array', 'min:1'],
            'sector_ids.*' => ['uuid', 'distinct', 'exists:sectors,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['uuid', 'distinct', 'exists:users,id'],
        ];
    }
}
