<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransitionPerformancePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny([ProgrammePermission::SubmitPerformancePlans->value, ProgrammePermission::ReviewPerformancePlans->value]) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'transition' => ['required', 'in:submit_goals,approve_goals,return_goals,start_review,submit_self_review,finalize_review'], 'rationale' => ['required', 'string', 'max:5000'],
            'capacity_gaps' => ['nullable', 'required_if:transition,finalize_review', 'string', 'max:10000'], 'development_actions' => ['nullable', 'required_if:transition,finalize_review', 'string', 'max:10000'],
            'goals' => ['nullable', 'required_if:transition,submit_self_review,finalize_review', 'array', 'min:1'], 'goals.*.id' => ['required_with:goals', 'uuid', 'exists:performance_goals,id'],
            'goals.*.actual_value' => ['nullable', 'numeric'], 'goals.*.rating' => ['required_with:goals', 'numeric', 'between:0,100'], 'goals.*.narrative' => ['nullable', 'string', 'max:5000'], 'goals.*.evidence_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
