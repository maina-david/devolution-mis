<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransitionTravelRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canAny([ProgrammePermission::SubmitTravelRequests->value, ProgrammePermission::ApproveTravelRequests->value, ProgrammePermission::FinanceClearTravel->value]) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transition' => ['required', 'in:submit,manager_approve,manager_reject,finance_clear,finance_reject,cancel'],
            'rationale' => ['required', 'string', 'max:5000'],
            'approved_cost' => ['nullable', 'numeric', 'min:0'],
            'finance_commitment_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
