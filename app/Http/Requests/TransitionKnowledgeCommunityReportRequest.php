<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionKnowledgeCommunityReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canAny([ProgrammePermission::CurateKnowledge->value, ProgrammePermission::ManageKnowledge->value]) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transition' => ['required', Rule::in(['triage', 'resolve', 'dismiss'])],
            'rationale' => ['required', 'string', 'min:20', 'max:5000'],
            'resolution' => ['nullable', 'required_if:transition,resolve,dismiss', 'string', 'min:20', 'max:5000'],
            'post_action' => ['nullable', 'required_if:transition,resolve,dismiss', Rule::in(['hide', 'keep_visible'])],
        ];
    }
}
