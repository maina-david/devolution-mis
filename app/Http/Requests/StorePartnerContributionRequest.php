<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerContributionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::SubmitPartnerData->value) === true
            || $this->user()?->can(ProgrammePermission::ManagePartners->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'partner_profile_id' => ['required', 'uuid', 'exists:partner_profiles,id'],
            'devolution_project_id' => ['required', 'uuid', 'exists:devolution_projects,id'],
            'financial_year' => ['required', 'string', 'max:20'],
            'contribution_type' => ['required', 'in:grant,loan,technical_assistance,in_kind,co_financing,other'],
            'committed_amount' => ['required', 'numeric', 'min:0'],
            'disbursed_amount' => ['required', 'numeric', 'min:0', 'lte:committed_amount'],
            'in_kind_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', Rule::in(ReferenceCatalogue::currencies())],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:planned,committed,disbursing,completed,cancelled'],
            'provenance' => ['required', 'array'],
            'provenance.source_system' => ['required', 'string', 'max:255'],
            'provenance.captured_at' => ['required', 'date'],
        ];
    }
}
