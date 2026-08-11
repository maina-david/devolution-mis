<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyIndicatorObservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::VerifyIndicatorData->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'verification_status' => ['required', Rule::in(['verified', 'rejected', 'clarification_requested'])],
            'quality_status' => ['required', Rule::in(['accepted', 'warning', 'rejected'])],
            'quality_issues' => ['nullable', 'array', 'max:50'],
            'quality_issues.*' => ['string', 'max:1000'],
            'rationale' => ['required', 'string', 'max:5000'],
        ];
    }
}
