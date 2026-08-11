<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentAppealRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::SubmitAssessment->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assessment_criterion_id' => ['nullable', 'uuid', 'exists:assessment_criteria,id'],
            'grounds' => ['required', 'string', 'min:30', 'max:10000'],
            'requested_remedy' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
