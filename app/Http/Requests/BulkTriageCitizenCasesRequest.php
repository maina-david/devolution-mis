<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\CitizenCase;
use App\Models\Organization;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTriageCitizenCasesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing(['selection_mode' => 'selected']);
    }

    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageCitizenCases->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'selection_mode' => ['required', Rule::in(['selected', 'filtered'])],
            'ids' => [Rule::requiredIf($this->input('selection_mode') === 'selected'), 'nullable', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'uuid', 'distinct', Rule::exists((new CitizenCase)->getTable(), 'id')],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
            'assigned_to' => ['required', 'uuid', Rule::exists((new User)->getTable(), 'id')],
            'assigned_organization_id' => ['nullable', 'uuid', Rule::exists((new Organization)->getTable(), 'id')],
            'sector_id' => ['nullable', 'uuid', Rule::exists((new Sector)->getTable(), 'id')],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'is_sensitive' => ['required', 'boolean'],
            'triage_note' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
