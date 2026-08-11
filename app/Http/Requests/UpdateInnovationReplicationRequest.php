<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInnovationReplicationRequest extends FormRequest
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
        return [
            'transition' => ['required', 'string', 'in:activate,start_pilot,submit_verification,abandon'],
            'adaptation_plan' => ['nullable', 'string', 'min:40', 'max:5000'],
            'success_measure' => ['nullable', 'string', 'max:255'],
            'baseline_value' => ['nullable', 'numeric'],
            'target_value' => ['nullable', 'numeric'],
            'actual_value' => ['nullable', 'numeric'],
            'outcome_summary' => ['nullable', 'string', 'min:40', 'max:5000'],
            'rationale' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }
}
