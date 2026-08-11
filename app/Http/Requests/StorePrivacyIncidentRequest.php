<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePrivacyIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDataGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'data_asset_id' => ['nullable', 'uuid', 'exists:data_assets,id'],
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'incident_lead_id' => ['required', 'uuid', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'controller_role' => ['required', 'in:controller,processor'],
            'breach_type' => ['required', 'in:confidentiality,integrity,availability,combined'],
            'description' => ['required', 'string', 'min:20', 'max:10000'],
            'personal_data_categories' => ['required', 'string', 'max:2000'],
            'estimated_data_subjects' => ['nullable', 'integer', 'min:0'],
            'contains_sensitive_data' => ['required', 'boolean'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:discovered_at'],
            'discovered_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }
}
