<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectProgressUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::SubmitProjectUpdates->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reporting_date' => ['required', 'date', 'before_or_equal:today'],
            'physical_progress' => ['required', 'numeric', 'between:0,100'],
            'financial_progress' => ['required', 'numeric', 'between:0,100'],
            'narrative' => ['required', 'string', 'max:10000'],
            'achievements' => ['nullable', 'string', 'max:5000'],
            'challenges' => ['nullable', 'string', 'max:5000'],
            'next_steps' => ['nullable', 'string', 'max:5000'],
            'provenance' => ['required', 'array'],
            'provenance.source_system' => ['required', 'string', 'max:255'],
            'provenance.captured_at' => ['required', 'date'],
            'indicator_results' => ['nullable', 'array', 'max:100'],
            'indicator_results.*.indicator_definition_id' => ['required', 'uuid', 'exists:indicator_definitions,id'],
            'indicator_results.*.county_id' => ['required', 'uuid', 'exists:counties,id'],
            'indicator_results.*.period_start' => ['required', 'date'],
            'indicator_results.*.period_end' => ['required', 'date', 'after_or_equal:indicator_results.*.period_start'],
            'indicator_results.*.dimension_key' => ['required', 'string', 'max:150', 'regex:/^[a-zA-Z0-9_.:-]+$/'],
            'indicator_results.*.disaggregation' => ['nullable', 'array', 'max:20'],
            'indicator_results.*.disaggregation.*' => ['required', 'string', 'max:150'],
            'indicator_results.*.numeric_value' => ['nullable', 'numeric'],
            'indicator_results.*.narrative_value' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
