<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePartnerCollaborationPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManagePartners->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['partner_profile_id' => ['required', 'uuid', 'exists:partner_profiles,id'], 'reference' => ['required', 'string', 'max:100', 'unique:partner_collaboration_plans,reference'], 'title' => ['required', 'string', 'max:255'], 'objective' => ['required', 'string', 'min:20', 'max:5000'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after:starts_on']];
    }
}
