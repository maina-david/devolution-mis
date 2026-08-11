<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransitionDevolutionInnovationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canAny([ProgrammePermission::ContributeKnowledge->value, ProgrammePermission::CurateKnowledge->value, ProgrammePermission::ManageKnowledge->value]) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['transition' => ['required', 'in:submit,accept_incubation,reject,start_pilot,scale'], 'rationale' => ['required', 'string', 'max:5000'], 'incubation_support' => ['nullable', 'string', 'max:5000'], 'evidence_reference' => ['nullable', 'string', 'max:2000']];
    }
}
