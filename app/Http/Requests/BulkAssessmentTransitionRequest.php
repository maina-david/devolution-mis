<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAssessmentTransitionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::SubmitAssessment->value) === true
            || $this->user()?->can(ProgrammePermission::ReviewAssessment->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'uuid', 'distinct'],
            'transition' => ['required', Rule::in(['submit', 'review'])],
        ];
    }

    /** @return list<string> */
    public function ids(): array
    {
        $ids = $this->validated('ids');

        return is_array($ids)
            ? array_values(array_filter($ids, is_string(...)))
            : [];
    }
}
