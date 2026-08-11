<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgrammeEvaluationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageIndicators->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'programme_id' => ['nullable', 'uuid', 'exists:programmes,id'],
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'code' => ['required', 'string', 'max:100', 'alpha_dash:ascii', 'unique:programme_evaluations,code'],
            'title' => ['required', 'string', 'max:255'],
            'evaluation_type' => ['required', Rule::in(['baseline', 'midline', 'endline', 'process', 'impact'])],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'terms_of_reference' => ['required', 'string', 'max:20000'],
            'methodology' => ['nullable', 'string', 'max:20000'],
            'executive_summary' => ['nullable', 'string', 'max:20000'],
            'findings' => ['nullable', 'array'],
            'recommendations' => ['nullable', 'array'],
            'lead_evaluator_id' => ['nullable', 'uuid', 'exists:users,id'],
        ];
    }
}
