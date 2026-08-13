<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentFolderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageRecords->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'uuid', 'exists:document_folders,id'],
        ];
    }
}
