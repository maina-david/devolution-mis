<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageReferenceData->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('organizations', 'code')->ignore($this->organization())],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['national', 'county', 'development_partner', 'civil_society', 'other'])],
            'county_id' => ['nullable', 'uuid', Rule::exists('counties', 'id')],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    private function organization(): Organization
    {
        /** @var Organization $organization */
        $organization = $this->route('organization');

        return $organization;
    }
}
