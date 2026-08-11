<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIndicatorObservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::SubmitIndicatorData->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'indicator_definition_id' => ['required', 'uuid', 'exists:indicator_definitions,id'],
            'county_id' => ['required', 'uuid', 'exists:counties,id'],
            'programme_id' => ['required', 'uuid', 'exists:programmes,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'measure_type' => ['required', Rule::in(['baseline', 'target', 'actual'])],
            'dimension_key' => ['nullable', 'string', 'max:255'],
            'disaggregation' => ['nullable', 'array'],
            'numeric_value' => ['nullable', 'numeric'],
            'narrative_value' => ['nullable', 'string', 'max:10000'],
            'source_reference' => ['required', 'string', 'max:2000'],
            'provenance' => ['required', 'array'],
            'provenance.source_system' => ['required', 'string', 'max:255'],
            'provenance.captured_at' => ['required', 'date'],
            'provenance.import_batch' => ['nullable', 'string', 'max:255'],
        ];
    }
}
