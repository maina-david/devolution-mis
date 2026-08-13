<?php

namespace App\Http\Requests;

use App\Services\LegacyReferenceInventory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HistoricalDataMigrationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['validated', 'validation_failed', 'approved', 'rejected', 'applied'])],
            'type' => ['nullable', Rule::in(['acpa_scores', 'acpa_reconstruction', 'performance_metrics', 'evaluation_baselines', 'counties', 'organizations', 'sectors', 'programmes', 'programme_county_coverages', 'users', 'sub_counties', 'wards'])],
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'legacy_status' => ['nullable', 'string', 'max:50'],
            'legacy_type' => ['nullable', Rule::in(LegacyReferenceInventory::TYPE_KEYS)],
            'disposition_page' => ['nullable', 'integer', 'min:1'],
            'disposition_per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'legacy_page' => ['nullable', 'integer', 'min:1'],
            'legacy_per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ];
    }
}
