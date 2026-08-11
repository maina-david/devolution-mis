<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::UploadEvidence->value) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'source_type' => ['required', Rule::in(['scanned', 'digital'])],
            'assessment_criterion_id' => ['nullable', 'uuid', 'exists:assessment_criteria,id'],
            'criterion_evidence_requirement_id' => ['nullable', 'uuid', 'required_with:assessment_criterion_id', 'exists:criterion_evidence_requirements,id'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,tif,tiff,doc,docx,xls,xlsx,csv,txt', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp,image/tiff,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain', 'max:20480'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'document.mimes' => 'Upload a PDF, scanned image, Word document, spreadsheet, CSV, or text file.',
            'document.mimetypes' => 'The document content does not match a supported evidence format.',
            'document.max' => 'The evidence document must not exceed 20 MB.',
        ];
    }
}
