<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIndicatorDefinitionRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:100', 'alpha_dash:ascii', 'unique:indicator_definitions,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'sector_id' => ['nullable', 'uuid', 'exists:sectors,id'],
            'programme_id' => ['nullable', 'uuid', 'exists:programmes,id'],
            'results_level' => ['required', Rule::in(['input', 'activity', 'output', 'outcome', 'impact'])],
            'unit_of_measure' => ['required', 'string', 'max:100'],
            'value_type' => ['required', Rule::in(['number', 'percentage', 'currency', 'count', 'text'])],
            'direction' => ['required', Rule::in(['increase', 'decrease', 'maintain'])],
            'frequency' => ['required', Rule::in(['monthly', 'quarterly', 'semiannual', 'annual', 'ad_hoc'])],
            'disaggregation_dimensions' => ['nullable', 'array', 'max:20'],
            'disaggregation_dimensions.*' => ['string', 'max:100', 'distinct'],
            'calculation_formula' => ['nullable', 'array'],
            'data_source' => ['required', 'string', 'max:2000'],
            'verification_method' => ['required', 'string', 'max:2000'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
