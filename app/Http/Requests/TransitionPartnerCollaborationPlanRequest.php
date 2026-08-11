<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransitionPartnerCollaborationPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canAny([ProgrammePermission::ManagePartners->value, ProgrammePermission::ApprovePartnerAgreements->value]) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['transition' => ['required', 'in:submit,approve,reject,complete'], 'decision_note' => ['nullable', 'string', 'max:5000']];
    }
}
