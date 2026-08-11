<?php

namespace App\Http\Requests;

use App\Actions\StageReferenceDataImport;
use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferenceDataImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->input('dataset_type') === 'users') {
            return $this->user()?->can(ProgrammePermission::ManageUserAccess->value) === true;
        }

        return $this->user()?->can(ProgrammePermission::ManageReferenceData->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,xlsx', 'mimes:csv,txt,xlsx', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/zip,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'max:20480'],
            'dataset_type' => ['required', Rule::in(array_keys(StageReferenceDataImport::HEADERS))],
            'source_name' => ['required', 'string', 'min:5', 'max:255'],
            'source_reference' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
