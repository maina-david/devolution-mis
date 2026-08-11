<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationFindingActionUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::SubmitIndicatorData->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['assessment_document_id' => ['required', 'uuid', 'exists:assessment_documents,id'], 'progress_percentage' => ['required', 'numeric', 'gt:0', 'max:100'], 'narrative' => ['required', 'string', 'max:5000']];
    }
}
