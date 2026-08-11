<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentFindingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ReviewAssessment->value) ?? false;
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
            'code' => ['required', 'string', 'max:80'],
            'severity' => ['required', Rule::in(['observation', 'minor', 'major', 'critical'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:10000'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'response_due_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
