<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDevolutionInnovationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ContributeKnowledge->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['county_id' => ['nullable', 'uuid', 'exists:counties,id'], 'sector_id' => ['nullable', 'uuid', 'exists:sectors,id'], 'title' => ['required', 'string', 'max:255'], 'problem_statement' => ['required', 'string', 'max:10000'], 'proposed_solution' => ['required', 'string', 'max:10000'], 'expected_impact' => ['required', 'string', 'max:10000'], 'maturity_level' => ['required', 'in:idea,prototype,validated,operational'], 'incubation_support' => ['nullable', 'string', 'max:5000'], 'evidence_reference' => ['nullable', 'string', 'max:2000']];
    }
}
