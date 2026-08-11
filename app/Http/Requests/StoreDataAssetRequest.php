<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDataAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDataGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['data_owner_id' => ['required', 'uuid', 'exists:users,id'], 'steward_id' => ['required', 'uuid', 'exists:users,id', 'different:data_owner_id'], 'code' => ['required', 'string', 'max:50', 'alpha_dash:ascii', 'unique:data_assets,code'], 'name' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:5000'], 'module' => ['required', 'string', 'max:255'], 'authoritative_source' => ['required', 'string', 'max:500'], 'classification' => ['required', 'in:public,official,confidential,restricted'], 'contains_personal_data' => ['required', 'boolean'], 'contains_sensitive_personal_data' => ['required', 'boolean'], 'personal_data_categories' => ['nullable', 'string', 'max:3000', 'required_if:contains_personal_data,1'], 'data_subject_categories' => ['nullable', 'string', 'max:3000', 'required_if:contains_personal_data,1'], 'storage_locations' => ['required', 'string', 'max:3000'], 'residency_country' => ['required', 'string', 'size:2', 'alpha:ascii', Rule::in(ReferenceCatalogue::countryCodes())], 'quality_standard' => ['nullable', 'string', 'max:3000']];
    }
}
