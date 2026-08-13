<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHistoricalDataMigrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageReferenceData->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,xlsx', 'mimes:csv,txt,xlsx', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/zip,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'max:20480'],
            'dataset_type' => ['required', Rule::in(['acpa_scores', 'acpa_reconstruction', 'performance_metrics', 'evaluation_baselines'])],
            'source_name' => ['required', 'string', 'min:5', 'max:255'],
            'source_reference' => ['required', 'string', 'min:3', 'max:255'],
            'period_from' => ['required', 'date_format:Y-m-d'],
            'period_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_from'],
        ];
    }
}
