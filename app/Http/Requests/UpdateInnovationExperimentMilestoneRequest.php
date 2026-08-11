<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInnovationExperimentMilestoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canAny([ProgrammePermission::ContributeKnowledge->value, ProgrammePermission::ManageKnowledge->value]) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['in_progress', 'completed', 'failed'])], 'actual_value' => ['nullable', 'required_if:status,completed,failed', 'string', 'max:255'], 'outcome_summary' => ['nullable', 'required_if:status,completed,failed', 'string', 'min:20', 'max:5000'], 'assessment_document_id' => ['nullable', 'required_if:status,completed,failed', 'uuid', 'exists:assessment_documents,id']];
    }
}
