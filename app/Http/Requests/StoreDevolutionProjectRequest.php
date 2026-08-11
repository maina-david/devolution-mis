<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDevolutionProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageProjects->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'alpha_dash:ascii', 'unique:devolution_projects,code'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'programme_id' => ['nullable', 'uuid', 'exists:programmes,id'],
            'sector_id' => ['required', 'uuid', 'exists:sectors,id'],
            'lead_county_id' => ['required', 'uuid', 'exists:counties,id'],
            'county_ids' => ['required', 'array', 'min:1'],
            'county_ids.*' => ['uuid', 'distinct', 'exists:counties,id'],
            'funding_organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'project_manager_id' => ['nullable', 'uuid', 'exists:users,id'],
            'planned_start_date' => ['required', 'date'],
            'planned_end_date' => ['required', 'date', 'after_or_equal:planned_start_date'],
            'approved_budget' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', Rule::in(ReferenceCatalogue::currencies())],
            'investment_registry_reference' => ['nullable', 'string', 'max:255'],
            'funding_source' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'array'],
            'climate_risk_screening' => ['nullable', 'array'],
            'indicator_ids' => ['nullable', 'array'],
            'indicator_ids.*' => ['uuid', 'distinct', 'exists:indicator_definitions,id'],
        ];
    }
}
