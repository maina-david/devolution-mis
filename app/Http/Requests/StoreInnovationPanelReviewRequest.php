<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInnovationPanelReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::CurateKnowledge->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['strategic_fit_score' => ['required', 'numeric', 'between:0,100'], 'feasibility_score' => ['required', 'numeric', 'between:0,100'], 'inclusion_score' => ['required', 'numeric', 'between:0,100'], 'evidence_score' => ['required', 'numeric', 'between:0,100'], 'recommendation' => ['required', Rule::in(['advance', 'revise', 'reject'])], 'rationale' => ['required', 'string', 'min:30', 'max:5000']];
    }
}
