<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerProfileRequest extends FormRequest
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
            'organization_id' => ['required', 'uuid', 'exists:organizations,id', 'unique:partner_profiles,organization_id'],
            'partner_type' => ['required', 'in:bilateral,multilateral,foundation,ngo,private_sector,council_of_governors,government_agency,other'],
            'country' => ['nullable', 'string', 'max:100', Rule::in(ReferenceCatalogue::countryNames())],
            'website' => ['nullable', 'url', 'max:255'],
            'focal_point_name' => ['required', 'string', 'max:255'],
            'focal_point_email' => ['required', 'email', 'max:255'],
            'focal_point_phone' => ['nullable', 'string', 'max:50'],
            'strategic_priorities' => ['nullable', 'string', 'max:5000'],
            'modalities' => ['nullable', 'array'],
            'county_ids' => ['required', 'array', 'min:1'],
            'county_ids.*' => ['uuid', 'distinct', 'exists:counties,id'],
            'sector_ids' => ['required', 'array', 'min:1'],
            'sector_ids.*' => ['uuid', 'distinct', 'exists:sectors,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid', 'distinct', 'exists:users,id'],
        ];
    }
}
