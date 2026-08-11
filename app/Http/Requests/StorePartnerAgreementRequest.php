<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerAgreementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManagePartners->value) === true;
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
            'reference' => ['required', 'string', 'max:100', 'unique:partner_agreements,reference'],
            'title' => ['required', 'string', 'max:255'],
            'agreement_type' => ['required', 'in:mou,partnership_framework,financing_agreement,cooperation_agreement,other'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'committed_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', Rule::in(ReferenceCatalogue::currencies())],
            'summary' => ['required', 'string', 'max:10000'],
            'document_reference' => ['nullable', 'string', 'max:1000'],
            'status' => ['prohibited'],
        ];
    }
}
