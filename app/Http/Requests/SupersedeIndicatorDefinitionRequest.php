<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupersedeIndicatorDefinitionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'results_level' => ['required', Rule::in(['input', 'activity', 'output', 'outcome', 'impact'])],
            'unit_of_measure' => ['required', 'string', 'max:100'],
            'value_type' => ['required', Rule::in(['number', 'percentage', 'currency', 'count', 'text'])],
            'direction' => ['required', Rule::in(['increase', 'decrease', 'maintain'])],
            'frequency' => ['required', Rule::in(['monthly', 'quarterly', 'semiannual', 'annual', 'ad_hoc'])],
            'data_source' => ['required', 'string', 'max:2000'],
            'verification_method' => ['required', 'string', 'max:2000'],
            'effective_from' => ['required', 'date'],
            'change_summary' => ['required', 'string', 'max:2000'],
        ];
    }
}
