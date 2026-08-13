<?php

namespace App\Http\Requests;

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
            'type' => ['nullable', Rule::in(['acpa_scores', 'acpa_reconstruction', 'performance_metrics', 'evaluation_baselines'])],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
