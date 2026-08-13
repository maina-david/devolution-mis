<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepositoryDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::UploadEvidence->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'folder_id' => ['required', 'uuid', 'exists:document_folders,id'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'source_type' => ['required', Rule::in(['scanned', 'digital'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'document_date' => ['nullable', 'date', 'before_or_equal:today'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,tif,tiff,doc,docx,xls,xlsx,csv,txt,mp3,mp4,webm,ogg,wav', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp,image/tiff,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain,audio/mpeg,audio/mp4,audio/ogg,audio/wav,video/mp4,video/webm', 'max:51200'],
        ];
    }
}
